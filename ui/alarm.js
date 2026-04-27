document.addEventListener("DOMContentLoaded", function () {
  let timerIntervalId = null; // To hold the ID of the timer interval
  let limitSoundHasPlayed = false; // Flag to ensure limit sound plays only once per event
  let lastTimerStartValue = null; // To track the current event

  /**
   * Initializes and runs the countdown timer.
   * Finds all necessary elements and sets up the per-second update.
   */
  function initializeTimer() {
    const timerElement = document.getElementById("timer");
    const timeDisplay = document.getElementById("time");
    const alarmSoundStart = document.getElementById("alarm-sound-start");
    const alarmSoundLimit = document.getElementById("alarm-sound-limit");

    if (!timerElement || !timeDisplay) return;

    // Check if it's a new event by comparing timer start time
    const currentTimerStart = timerElement.dataset.timerStart;
    if (lastTimerStartValue !== currentTimerStart) {
      limitSoundHasPlayed = false; // Reset the flag for the new event
      lastTimerStartValue = currentTimerStart;
    }

    const startTime = new Date(timerElement.dataset.timerStart);
    const timerLimit = parseInt(timerElement.dataset.timerLimit, 10);
    let limitExceeded = false;
    let startSoundPlayed = false;

    if (isNaN(startTime.getTime())) {
      timeDisplay.textContent = "Error";
      return;
    }

    const playSound = (soundElement) => {
      if (soundElement) {
        soundElement.play().catch((error) => console.error("Audio playback failed:", error));
      }
    };

    const updateTimer = () => {
      const elapsedSeconds = Math.floor((new Date() - startTime) / 1000);

      if (elapsedSeconds < 0) {
        timeDisplay.textContent = "00:00";
        return;
      }

      if (!startSoundPlayed && elapsedSeconds < 11) {
        playSound(alarmSoundStart);
        startSoundPlayed = true;
      }

      const minutes = Math.floor(elapsedSeconds / 60);
      const seconds = elapsedSeconds % 60;
      timeDisplay.textContent = `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;

      if (!limitExceeded && !isNaN(timerLimit) && elapsedSeconds > timerLimit) {
        timerElement.classList.remove("bg-success");
        timerElement.classList.add("bg-danger");
        limitExceeded = true;
        if (!limitSoundHasPlayed) {
          playSound(alarmSoundLimit);
          limitSoundHasPlayed = true;
        }
      }
    };

    updateTimer();
    timerIntervalId = setInterval(updateTimer, 1000);
  }

  /**
   * Fetches the latest version of the page in the background and
   * swaps the content without a full page reload.
   */
  async function refreshContentSeamlessly() {
    const indicator = document.getElementById("refresh-indicator");

    const hideIndicator = () => {
      if (indicator) {
        setTimeout(() => {
          indicator.style.display = "none";
        }, 500); // Keep visible for 0.5s after refresh
      }
    };

    try {
      const response = await fetch(window.location.href, { cache: "no-store" });
      if (!response.ok) {
        // Don't show indicator if fetch was not successful
        return;
      }

      // Show indicator only on successful fetch
      if (indicator) {
        indicator.style.display = "block";
      }

      const htmlText = await response.text();
      const parser = new DOMParser();
      const newDoc = parser.parseFromString(htmlText, "text/html");
      const currentContent = document.querySelector(".row.g-0.h-100");
      const newContent = newDoc.querySelector(".row.g-0.h-100");
      if (currentContent && newContent) {
        clearInterval(timerIntervalId); // Stop the old timer
        currentContent.innerHTML = newContent.innerHTML; // Swap content
        initializeTimer(); // Start a new timer with the new elements
      }
      hideIndicator();
    } catch (error) {
      console.error("Seamless refresh failed, falling back to hard reload.", error);
      window.location.reload(); // Fallback to a hard reload on error
    }
  }

  /**
   * Updates the clock in the header every second.
   */
  function updateClock() {
    const clockElement = document.getElementById("clock");
    if (!clockElement) return;

    const now = new Date();
    // Use cs-CZ locale for correct date and time formatting
    const date = now.toLocaleDateString("cs-CZ");
    const time = now.toLocaleTimeString("cs-CZ");

    clockElement.textContent = `${date} ${time}`;
  }

  // Start the timer on initial page load
  initializeTimer();

  updateClock();
  setInterval(updateClock, 1000);
  // Set up the seamless refresh to run every 10 seconds
  setInterval(refreshContentSeamlessly, 10000);
});
