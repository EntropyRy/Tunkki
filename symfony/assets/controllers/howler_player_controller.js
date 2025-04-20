import { Controller } from "@hotwired/stimulus";
import { Howl } from "howler";

export default class extends Controller {
  static targets = ["playButton", "pauseButton", "loadingButton"];
  static values = {
    mp3Url: String,
    opusUrl: String,
    format: String,
  };

  connect() {
    this.isPlaying = false;
    this.isLoading = false;
    this.wakeLock = null; // To store the wake lock instance

    // Load format from localStorage if available
    const savedFormat = localStorage.getItem("audioPlayerFormat");
    if (savedFormat && (savedFormat === "mp3" || savedFormat === "opus")) {
      this.formatValue = savedFormat; // Override initial format value
    }

    this.setupPlayer();

    this.element.addEventListener(
      "stream:format-changed",
      this.onFormatChanged.bind(this),
    );
    this.element.addEventListener(
      "stream:started",
      this.onStreamStarted.bind(this),
    );
    this.element.addEventListener(
      "stream:stopped",
      this.onStreamStopped.bind(this),
    );
  }

  disconnect() {
    this.stopPlayback();
    this.releaseWakeLock(); // Release wake lock when the controller is disconnected
    if (this.sound) {
      this.sound.unload();
    }
  }

  setupPlayer() {
    this.sound = new Howl({
      src: [this.getCurrentStreamUrl()],
      html5: true, // Use HTML5 audio for streaming
      preload: "none", // Ensure the stream doesn't buffer until play is triggered
      format: [this.formatValue],
      autoplay: false,
      volume: 0.7,
      onload: () => {
        this.isLoading = false;
        this.updatePlaybackUI(false, false);
      },
      onplay: async () => {
        this.isPlaying = true;
        this.isLoading = false;
        this.updatePlaybackUI(true, false);
        await this.requestWakeLock(); // Request wake lock when the stream starts playing
      },
      onpause: () => {
        this.isPlaying = false;
        this.updatePlaybackUI(false, false);
        this.releaseWakeLock(); // Release wake lock when the stream is paused
      },
      onstop: () => {
        this.isPlaying = false;
        this.updatePlaybackUI(false, false);
        this.releaseWakeLock(); // Release wake lock when the stream is stopped
      },
      onloaderror: () => {
        console.error("Error loading stream");
        this.isLoading = false;
        this.updatePlaybackUI(false, false);
      },
      onplayerror: () => {
        console.error("Error playing stream");
        this.isLoading = false;
        this.updatePlaybackUI(false, false);

        // Attempt recovery
        this.sound.unload();
        this.setupPlayer();
      },
    });
  }

  getCurrentStreamUrl() {
    return this.formatValue === "mp3" ? this.mp3UrlValue : this.opusUrlValue;
  }

  async play() {
    if (!this.sound.playing()) {
      this.isLoading = true;
      this.updatePlaybackUI(false, true);
      this.sound.play();
    }
  }

  pause() {
    if (this.sound.playing()) {
      this.sound.pause();
    }
  }

  stopPlayback() {
    if (this.sound && this.sound.playing()) {
      this.sound.stop();
    }
    this.updatePlaybackUI(false, false);
  }

  updatePlaybackUI(isPlaying, isLoading) {
    if (this.hasPlayButtonTarget) {
      this.playButtonTarget.classList.toggle("d-none", isPlaying || isLoading);
    }
    if (this.hasPauseButtonTarget) {
      this.pauseButtonTarget.classList.toggle(
        "d-none",
        !isPlaying || isLoading,
      );
    }
    if (this.hasLoadingButtonTarget) {
      this.loadingButtonTarget.classList.toggle("d-none", !isLoading);
    }
  }

  async requestWakeLock() {
    if ("wakeLock" in navigator) {
      try {
        this.wakeLock = await navigator.wakeLock.request("screen");
        console.log("Wake Lock is active");
      } catch (err) {
        console.error("Failed to acquire wake lock:", err);
      }
    } else {
      console.warn("Wake Lock API is not supported in this browser.");
    }
  }

  releaseWakeLock() {
    if (this.wakeLock) {
      this.wakeLock
        .release()
        .then(() => {
          this.wakeLock = null;
          console.log("Wake Lock released");
        })
        .catch((err) => {
          console.error("Failed to release wake lock:", err);
        });
    }
  }

  onFormatChanged(event) {
    const newFormat = event.detail.format;

    if (this.formatValue !== newFormat) {
      const wasPlaying = this.sound.playing();
      this.formatValue = newFormat;

      // Save the selected format to localStorage
      localStorage.setItem("audioPlayerFormat", newFormat);

      this.sound.unload();
      this.setupPlayer();

      if (wasPlaying) {
        this.play();
      }
    }
  }

  onStreamStarted() {
    if (this.sound) {
      this.sound.unload();
    }
    this.setupPlayer();
  }

  onStreamStopped() {
    this.stopPlayback();
  }
}
