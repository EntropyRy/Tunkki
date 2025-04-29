import { Controller } from "@hotwired/stimulus";
import { Howl } from "howler";

export default class extends Controller {
  static targets = [
    "playButton",
    "pauseButton",
    "loadingButton",
    "volumeSlider",
  ];
  static values = {
    mp3Url: String, // Your Icecast stream URL(s)
    opusUrl: String,
    format: String,
    title: { type: String, default: "Live Stream" },
    artist: { type: String, default: "Entropy ry" },
    album: { type: String, default: "" },
    artwork: { type: Array, default: [] },
  };

  connect() {
    // console.log("Connecting Simplified Howler Player Controller...");
    this.isPlaying = false;
    this.isLoading = false;
    this.wakeLock = null;
    this.sound = null;

    this.loadFormatFromLocalStorage();

    // Add event listeners (keep these)
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

    // Setup MediaSession if supported
    this.setupMediaSession();

    // console.log("Initial format:", this.formatValue);
  }

  disconnect() {
    // console.log("Disconnecting Simplified Howler Player Controller...");
    this.stopPlayback(); // Stop sound and unload
    this.releaseWakeLock();
    this.clearMediaSession();
  }

  // --- MediaSession API Integration ---
  setupMediaSession() {
    if ("mediaSession" in navigator) {
      // Set initial metadata (will be updated when playback starts)
      this.updateMediaSessionMetadata();

      // Set action handlers for media controls
      navigator.mediaSession.setActionHandler("play", () => {
        this.play();
      });

      navigator.mediaSession.setActionHandler("pause", () => {
        this.pause();
      });

      navigator.mediaSession.setActionHandler("stop", () => {
        this.stopPlayback();
      });

      // Optional: implement seeking handlers if needed for specific use cases
      // Since this is a stream, seeking is typically not applicable
    }
  }

  updateMediaSessionMetadata() {
    if (!("mediaSession" in navigator)) return;

    try {
      const defaultArtwork = [
        {
          src: "https://entropy.fi/images/header-logo.svg",
          sizes: "512x512",
          type: "image/svg+xml",
        },
      ];

      const artwork =
        this.artworkValue.length > 0 ? this.artworkValue : defaultArtwork;

      navigator.mediaSession.metadata = new MediaMetadata({
        title: this.titleValue,
        artist: this.artistValue,
        album: this.albumValue,
        artwork: artwork,
      });

      // Update playback state
      navigator.mediaSession.playbackState = this.isPlaying
        ? "playing"
        : "paused";
    } catch (error) {
      // console.error("Error setting MediaSession metadata:", error);
    }
  }

  clearMediaSession() {
    if ("mediaSession" in navigator) {
      navigator.mediaSession.playbackState = "none";
      // Clear handlers
      navigator.mediaSession.setActionHandler("play", null);
      navigator.mediaSession.setActionHandler("pause", null);
      navigator.mediaSession.setActionHandler("stop", null);
    }
  }

  // --- Player Setup & Control (No Web Audio API) ---

  loadFormatFromLocalStorage() {
    const savedFormat = localStorage.getItem("audioPlayerFormat");
    if (savedFormat && (savedFormat === "mp3" || savedFormat === "opus")) {
      this.formatValue = savedFormat;
    } else {
      this.formatValue = this.mp3UrlValue
        ? "mp3"
        : this.opusUrlValue
          ? "opus"
          : "mp3";
    }
    // console.log("Format loaded/determined:", this.formatValue);
  }

  getCurrentStreamUrl() {
    const url =
      this.formatValue === "mp3" ? this.mp3UrlValue : this.opusUrlValue;
    if (!url) {
      // console.warn("Stream URL is missing for format:", this.formatValue);
    }
    return url;
  }

  setupPlayerAndPlay() {
    if (this.sound) {
      this.sound.unload();
      this.sound = null;
    }

    const streamUrl = this.getCurrentStreamUrl();
    if (!streamUrl) {
      console.error(
        "Cannot play: Stream URL is not defined for format",
        this.formatValue,
      );
      this.updatePlaybackUI(false, false);
      return;
    }

    // console.log(
    //   `Initiating Howl setup (HTML5 only) for: ${streamUrl} (Format: ${this.formatValue})`,
    // );
    this.isLoading = true;
    this.updatePlaybackUI(false, true);

    this.sound = new Howl({
      src: [streamUrl],
      html5: true, // ESSENTIAL for Icecast streams, uses <audio> element
      format: [this.formatValue],
      autoplay: false, // Control via .play()
      volume: this.hasVolumeSliderTarget
        ? parseFloat(this.volumeSliderTarget.value)
        : 1.0,
      // No Web Audio API connection needed or attempted
      onload: () => {
        // console.log("Howler onload (HTML5 Stream)");
      },
      onplay: async () => {
        // console.log("Howler onplay: Playback started (HTML5 Stream)");
        this.isPlaying = true;
        this.isLoading = false;
        this.updatePlaybackUI(true, false);
        // Update MediaSession metadata and state
        this.updateMediaSessionMetadata();
        // Request wake lock when playback starts
        await this.requestWakeLock();
      },
      onpause: () => {
        // console.log("Howler onpause: Playback paused");
        this.isPlaying = false;
        this.updatePlaybackUI(false, false);
        // Update MediaSession state
        if ("mediaSession" in navigator) {
          navigator.mediaSession.playbackState = "paused";
        }
        // Release wake lock when playback pauses
        this.releaseWakeLock();
      },
      onstop: () => {
        // console.log("Howler onstop: Playback stopped/unloaded");
        this.isPlaying = false;
        this.isLoading = false;
        this.updatePlaybackUI(false, false);
        // Update MediaSession state
        if ("mediaSession" in navigator) {
          navigator.mediaSession.playbackState = "none";
        }
        // Release wake lock when playback stops
        this.releaseWakeLock();
        this.sound = null;
      },
      onloaderror: (id, err) => {
        // console.error("Howler onloaderror (HTML5 Stream):", id, err);
        // alert(`Error loading audio stream: ${err}`);
        this.isLoading = false;
        this.updatePlaybackUI(false, false);
        this.releaseWakeLock(); // Release lock on error
      },
      onplayerror: (id, err) => {
        // console.error("Howler onplayerror (HTML5 Stream):", id, err);
        // alert(`Error playing audio stream: ${err}`);
        this.isLoading = false;
        this.updatePlaybackUI(false, false);
        this.releaseWakeLock(); // Release lock on error
        if (this.sound) this.sound.unload();
      },
    });

    // console.log("Calling Howler play()...");
    this.sound.play();
  }

  play() {
    if (!this.isPlaying && !this.isLoading) {
      // console.log("Play action triggered.");
      this.setupPlayerAndPlay();
    } else {
      // console.log("Play action ignored: Already playing or loading.");
    }
  }

  pause() {
    if (this.sound && this.sound.playing()) {
      // console.log("Pause action triggered.");
      this.sound.pause(); // Triggers onpause
    }
  }

  stopPlayback() {
    if (this.sound) {
      // console.log("Stop action triggered.");
      this.sound.stop(); // Triggers onstop
    } else {
      this.isPlaying = false;
      this.isLoading = false;
      this.updatePlaybackUI(false, false);
      this.releaseWakeLock();
    }
  }

  updateVolume() {
    if (this.hasVolumeSliderTarget && this.sound) {
      const volume = parseFloat(this.volumeSliderTarget.value);
      this.sound.volume(volume);
    }
  }

  // --- UI Update ---
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

  // --- Wake Lock (Keep as is - This is what prevents sleep) ---
  async requestWakeLock() {
    if ("wakeLock" in navigator) {
      try {
        // Release existing lock if any before requesting new one
        if (this.wakeLock) {
          await this.releaseWakeLock(); // Ensure previous lock is released
        }
        this.wakeLock = await navigator.wakeLock.request("screen");
        // console.log("Screen Wake Lock is active");
        // Listen for unexpected releases (e.g., tab backgrounded)
        this.wakeLock.addEventListener("release", () => {
          // console.log("Screen Wake Lock was released");
          // Check if we are still supposed to be playing; if so, maybe re-request?
          // For now, just nullify the reference.
          this.wakeLock = null;
        });
      } catch (err) {
        // Log error name and message for better debugging
        console.error(
          `Failed to acquire screen wake lock: ${err.name}, ${err.message}`,
        );
        this.wakeLock = null; // Ensure wakeLock is null if request fails
      }
    } else {
      // console.warn("Screen Wake Lock API is not supported in this browser.");
    }
  }

  releaseWakeLock() {
    // Check if wakeLock exists and has not already been released
    if (this.wakeLock) {
      const lock = this.wakeLock;
      this.wakeLock = null; // Clear reference immediately
      return lock
        .release()
        .then(() => {
          console.log("Screen Wake Lock released");
        })
        .catch((err) => {
          // Log potential errors during release
          console.error(
            `Failed to release screen wake lock: ${err.name}, ${err.message}`,
          );
        });
    }
    // Return a resolved promise if no lock exists or it was already released
    return Promise.resolve();
  }

  // --- Event Handlers (Keep as is) ---
  onFormatChanged(event) {
    const newFormat = event.detail.format;
    // console.log(`External event: Format changed to ${newFormat}`);
    if (
      this.formatValue !== newFormat &&
      (newFormat === "mp3" || newFormat === "opus")
    ) {
      const wasPlaying = this.isPlaying;
      this.stopPlayback();
      this.formatValue = newFormat;
      localStorage.setItem("audioPlayerFormat", newFormat);
      if (wasPlaying) {
        this.play();
      }
    }
  }

  onStreamStarted() {
    console.log("External event: Stream started");
    if (!this.isPlaying && !this.isLoading) {
      // console.log("Stream started externally, initiating playback.");
      this.play();
    } else {
      // console.log("Stream started externally, but player is already active/loading.");
    }
  }

  onStreamStopped() {
    console.log("External event: Stream stopped");
    if (this.isPlaying || this.isLoading) {
      // console.log("Stopping player because external stream stopped.");
      this.stopPlayback();
      // alert("The audio stream has stopped."); // Optional user feedback
    }
  }
}
