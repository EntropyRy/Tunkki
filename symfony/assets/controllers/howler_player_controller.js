import { Controller } from "@hotwired/stimulus";
import { Howl } from "howler";

export default class extends Controller {
  static targets = ["playButton", "pauseButton", "volumeSlider"];
  static values = {
    mp3Url: String,
    opusUrl: String,
    format: String,
  };

  connect() {
    this.isPlaying = false;
    this.setupPlayer();

    // Listen for events from the Live Component
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
    if (this.sound) {
      this.sound.unload();
    }
  }

  setupPlayer() {
    // Create the Howl instance
    this.sound = new Howl({
      src: [this.getCurrentStreamUrl()],
      html5: true, // Force HTML5 Audio for streaming
      format: [this.formatValue], // Use the format from the component
      autoplay: false,
      volume: 0.7,
      onplay: () => {
        this.isPlaying = true;
        this.updatePlaybackUI(true);
      },
      onpause: () => {
        this.isPlaying = false;
        this.updatePlaybackUI(false);
      },
      onstop: () => {
        this.isPlaying = false;
        this.updatePlaybackUI(false);
      },
      onloaderror: () => {
        console.error("Error loading stream");
        this.updatePlaybackUI(false);
      },
      onplayerror: () => {
        console.error("Error playing stream");
        this.updatePlaybackUI(false);

        // Try to recover by recreating the player
        this.sound.unload();
        this.setupPlayer();
      },
    });
  }

  getCurrentStreamUrl() {
    return this.formatValue === "mp3" ? this.mp3UrlValue : this.opusUrlValue;
  }

  // Actions
  play() {
    if (this.sound && !this.sound.playing()) {
      this.sound.play();
    }
  }

  pause() {
    if (this.sound && this.sound.playing()) {
      this.sound.pause();
    }
  }

  updateVolume() {
    if (this.hasVolumeSliderTarget && this.sound) {
      const volume = parseFloat(this.volumeSliderTarget.value);
      this.sound.volume(volume);
    }
  }

  // Event handlers
  onFormatChanged(event) {
    const newFormat = event.detail.format;

    // Only reload if format actually changed
    if (this.formatValue !== newFormat) {
      const wasPlaying = this.sound && this.sound.playing();
      this.formatValue = newFormat;

      // Unload current stream
      if (this.sound) {
        this.sound.unload();
      }

      // Create new player with new format
      this.setupPlayer();

      // Resume playback if it was playing before
      if (wasPlaying) {
        this.sound.play();
      }
    }
  }

  onStreamStarted() {
    // Stream became available
    if (this.sound) {
      this.sound.unload();
    }
    this.setupPlayer();
  }

  onStreamStopped() {
    // Stream went offline
    this.stopPlayback();
  }

  stopPlayback() {
    if (this.sound && this.sound.playing()) {
      this.sound.stop();
    }
    this.updatePlaybackUI(false);
  }

  updatePlaybackUI(isPlaying) {
    if (this.hasPlayButtonTarget && this.hasPauseButtonTarget) {
      this.playButtonTarget.classList.toggle("d-none", isPlaying);
      this.pauseButtonTarget.classList.toggle("d-none", !isPlaying);
    }
  }
}
