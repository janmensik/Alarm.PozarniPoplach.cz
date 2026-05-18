function alarmSystem(apiUrl, authBaseUrl) {
  return {
    data: null,
    loading: false,
    currentTime: "00:00:00",
    timerSeconds: 0,
    timerDisplay: "00:00",
    lastEventId: null,
    audioEnabled: true,
    isOnline: true,
    connectionError: null,
    isSyncing: false,
    appVersion: null,

    // Auth State
    isAuthorized: false,
    deviceUuid: null,
    refreshToken: null,
    qrCodeData: null,
    deviceCode: null,
    verificationUrl: null,
    authStatus: 'initializing', // initializing, pending, authorized, error
    pollInterval: null,

    async init() {
      this.updateClock();
      setInterval(() => this.updateClock(), 1000);
      this.checkAudioAutoplay();

      // Load or generate device UUID
      this.deviceUuid = localStorage.getItem("alarm_device_uuid");
      if (!this.deviceUuid) {
        this.deviceUuid = crypto.randomUUID();
        localStorage.setItem("alarm_device_uuid", this.deviceUuid);
      }

      this.refreshToken = localStorage.getItem("alarm_refresh_token");

      if (this.refreshToken) {
        await this.validateAndStart();
      } else {
        await this.startAuthFlow();
      }

      setInterval(() => this.updateTimer(), 1000);

      // Check for updates every 10 minutes (600,000 ms)
      this.checkUpdate();
      setInterval(() => this.checkUpdate(), 600000);
    },

    async checkUpdate() {
      try {
        const response = await fetch(`${window.location.origin}/api/version?t=${Date.now()}`);
        const result = await response.json();

        if (result.success) {
          if (!this.appVersion) {
            this.appVersion = result.version;
          } else if (this.appVersion !== result.version) {
            console.log("New version detected, reloading...");
            window.location.reload(true);
          }
        }
      } catch (e) {
        // Ignore network errors
      }
    },

    async validateAndStart() {
      this.loading = true;
      try {
        const response = await fetch(`${authBaseUrl}/validate`, {
          headers: {
            'X-Device-Uuid': this.deviceUuid,
            'X-Device-Token': this.refreshToken
          }
        });
        const result = await response.json();

        if (result.success) {
          this.isAuthorized = true;
          this.authStatus = 'authorized';
          this.fetchData();
          if (this.dataInterval) clearInterval(this.dataInterval);
          this.dataInterval = setInterval(() => this.fetchData(), 60000);
        } else {
          // Token invalid or expired
          this.refreshToken = null;
          localStorage.removeItem("alarm_refresh_token");
          await this.startAuthFlow();
        }
      } catch (e) {
        console.error("Validation failed", e);
        this.authStatus = 'error';
        this.isOnline = false;
        // Retry validation later if it was a network error
        setTimeout(() => this.validateAndStart(), 10000);
      } finally {
        this.loading = false;
      }
    },

    async startAuthFlow() {
      this.authStatus = 'pending';
      try {
        const response = await fetch(`${authBaseUrl}/init?uuid=${this.deviceUuid}`);
        const result = await response.json();

        if (result.success) {
          this.qrCodeData = result.qr_code_data;
          this.deviceCode = result.device_code;
          this.verificationUrl = result.verification_url;
          this.startPolling(result.device_code);
        } else {
          this.authStatus = 'error';
        }
      } catch (e) {
        console.error("Auth init failed", e);
        this.authStatus = 'error';
        setTimeout(() => this.startAuthFlow(), 10000);
      }
    },

    startPolling(deviceCode) {
      if (this.pollInterval) clearInterval(this.pollInterval);

      this.pollInterval = setInterval(async () => {
        try {
          const response = await fetch(`${authBaseUrl}/poll?code=${deviceCode}`);
          const result = await response.json();

          if (result.success && result.status === 'linked') {
            clearInterval(this.pollInterval);
            await this.finalizeAuthorization(deviceCode);
          } else if (result.status === 'expired') {
            clearInterval(this.pollInterval);
            this.startAuthFlow();
          }
        } catch (e) {
          console.error("Polling error", e);
        }
      }, 5000);
    },

    async finalizeAuthorization(deviceCode) {
      try {
        const response = await fetch(`${authBaseUrl}/authorize?code=${deviceCode}`);
        const result = await response.json();

        if (result.success) {
          this.refreshToken = result.refresh_token;
          localStorage.setItem("alarm_refresh_token", this.refreshToken);
          this.isAuthorized = true;
          this.authStatus = 'authorized';

          // Cleanup URL if we are on /login or other non-root path
          if (window.location.pathname !== '/' && window.location.pathname !== '') {
            window.location.href = '/';
          } else {
            this.fetchData();
            setInterval(() => this.fetchData(), 60000);
          }
        }
      } catch (e) {
        console.error("Finalization failed", e);
      }
    },

    checkAudioAutoplay() {
      try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContext();
        this.audioEnabled = ctx.state === "running";
        if (ctx.state !== "closed") ctx.close().catch(() => {});
      } catch (e) {
        this.audioEnabled = false;
      }
    },

    toggleAudio() {
      this.audioEnabled = !this.audioEnabled;
    },

    updateClock() {
      const now = new Date();
      this.currentTime = now.getHours().toString().padStart(2, "0") + ":" + now.getMinutes().toString().padStart(2, "0") + ":" + now.getSeconds().toString().padStart(2, "0");
    },

    async fetchData() {
      if (!this.isAuthorized) return;

      this.loading = true;
      this.isSyncing = true;
      try {
        // We pass the auth credentials in headers
        const response = await fetch(apiUrl, {
          headers: {
            'X-Device-Uuid': this.deviceUuid,
            'X-Device-Token': this.refreshToken
          }
        });

        if (response.status === 401) {
          // Device was likely deleted or token invalidated
          console.warn("Authorization revoked by server. Restarting auth flow.");
          this.refreshToken = null;
          localStorage.removeItem("alarm_refresh_token");
          this.isAuthorized = false;
          await this.startAuthFlow();
          return;
        }

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const newData = await response.json();

        const eventId = newData.dispatch_identification || newData.dispatched_at_ts;
        if (eventId !== this.lastEventId) {
          if (this.lastEventId !== null) {
            this.playAlert("start");
          }
          this.lastEventId = eventId;
        }

        this.data = newData;
        this.isOnline = true;
        this.connectionError = null;
      } catch (error) {
        console.error("API Error:", error);
        this.isOnline = false;

        // Check if it is a general internet issue or specifically our API
        if (!navigator.onLine) {
          this.connectionError = 'no_internet';
        } else {
          this.connectionError = 'api_unreachable';
        }
      } finally {
        this.loading = false;
        setTimeout(() => {
          this.isSyncing = false;
        }, 1000);
      }
    },

    updateTimer() {
      if (!this.data || !this.data.dispatched_at_ts) return;

      const start = new Date(this.data.dispatched_at_ts * 1000);
      const now = new Date();
      const diff = Math.floor((now - start) / 1000);

      if (diff >= 0) {
        this.timerSeconds = diff;
        const mins = Math.floor(diff / 60).toString().padStart(2, "0");
        const secs = (diff % 60).toString().padStart(2, "0");
        this.timerDisplay = `${mins}:${secs}`;

        if (diff === 600) {
          this.playAlert("limit");
        }
      }
    },

    playAlert(type) {
      if (!this.audioEnabled) return;
      const el = document.getElementById(`alarm-sound-${type}`);
      if (el) {
        el.play().catch((e) => {
          console.log("Audio blocked:", e);
          this.audioEnabled = false;
        });
      }
    },

    formatDate(ts) {
      if (!ts) return "";
      const d = new Date(ts * 1000);
      return d.toLocaleString("cs-CZ");
    },
  };
}

