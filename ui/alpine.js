function alarmSystem(apiUrl) {
  return {
    data: null,
    loading: false,
    currentTime: "00:00:00",
    timerSeconds: 0,
    timerDisplay: "00:00",
    lastEventId: null,
    audioEnabled: true,
    isOnline: true,
    isSyncing: false,

    init() {
      this.updateClock();
      setInterval(() => this.updateClock(), 1000);

      // Check audio permissions on load
      this.checkAudioAutoplay();

      this.fetchData();
      // Poll for updates every 60 seconds
      setInterval(() => this.fetchData(), 60000);

      setInterval(() => this.updateTimer(), 1000);
    },

    checkAudioAutoplay() {
      try {
        // Use AudioContext to check if the browser allows audio
        // If autoplay is blocked by the browser, the context starts in a 'suspended' state
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContext();
        this.audioEnabled = ctx.state === "running";

        // Clean up the context to free up memory resources
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
      this.loading = true;
      this.isSyncing = true;
      try {
        const response = await fetch(apiUrl);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const newData = await response.json();

        // Check if it's a new incident (assuming unit_id and dispatched_at_ts form a unique combo)
        const eventId = newData.dispatch_identification || newData.dispatched_at_ts;
        if (eventId !== this.lastEventId) {
          if (this.lastEventId !== null) {
            this.playAlert("start");
          }
          this.lastEventId = eventId;
        }

        this.data = newData;
        this.isOnline = true;
      } catch (error) {
        console.error("API Error:", error);
        this.isOnline = false;
      } finally {
        this.loading = false;
        // Keep the sync indicator visible for 1 second
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
        const mins = Math.floor(diff / 60)
          .toString()
          .padStart(2, "0");
        const secs = (diff % 60).toString().padStart(2, "0");
        this.timerDisplay = `${mins}:${secs}`;

        // Play limit sound exactly at 10 minutes
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
          this.audioEnabled = false; // Disable toggle state if dynamically blocked by browser
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
