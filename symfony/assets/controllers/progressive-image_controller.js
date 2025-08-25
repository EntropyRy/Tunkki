// assets/controllers/progressive-image_controller.js
//
// Opacity / reveal is now handled ONLY via CSS classes:
// - .progressive-picture (initial opacity 0) -> add .loaded (opacity 1)
// - .progressive-placeholder (initial opacity 1) -> add .faded (opacity 0)
// JS no longer writes inline opacity styles to avoid flashes or race conditions.
// Blend / background attributes are provided from Twig; this controller does
// not inject style attributes (except filter removal on error).

import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static values = {
    mediaId: String,
    sizes: Object,
    lazy: Boolean,
  };

  static targets = ["placeholder", "picture", "image"];

  connect() {
    this.observer = null;
    this.loadingPromise = null;
    this.isLoaded = false;
    this.setupLoadingStrategy();
  }

  disconnect() {
    this.cleanup();
  }

  setupLoadingStrategy() {
    const dataSrc = this.imageTarget.getAttribute("data-src");

    if (!dataSrc) {
      return;
    }

    if (this.isImageCached(dataSrc)) {
      this.loadImageImmediately();
    } else if (this.lazyValue === false) {
      this.loadProgressiveImage();
    } else {
      this.setupIntersectionObserver();
    }
  }

  isImageCached(src) {
    const testImg = new Image();
    testImg.src = src;
    return testImg.complete && testImg.naturalWidth > 0;
  }

  setupIntersectionObserver() {
    if (!window.IntersectionObserver) {
      this.loadProgressiveImage();
      return;
    }

    this.observer = new IntersectionObserver(
      this.handleIntersection.bind(this),
      {
        threshold: 0.1,
        rootMargin: "50px",
      },
    );

    this.observer.observe(this.element);
  }

  handleIntersection(entries) {
    const entry = entries[0];
    if (entry.isIntersecting && !this.isLoaded) {
      this.loadProgressiveImage();
      this.observer.unobserve(entry.target);
    }
  }

  loadImageImmediately() {
    if (this.isLoaded) return;

    // Swap in sources immediately
    this.updateImageSources();

    const finalize = () => {
      this.applyLoadedStyles(true);
      this.markAsLoaded(true);
    };

    // Decode to ensure the first painted frame already has correct colors/blending
    if (this.imageTarget && this.imageTarget.decode) {
      this.imageTarget.decode().then(finalize).catch(finalize);
    } else {
      finalize();
    }
  }

  async loadProgressiveImage() {
    if (this.isLoaded || this.loadingPromise) return;

    try {
      this.loadingPromise = this.preloadImage();
      await this.loadingPromise;

      // Swap in actual sources
      this.updateImageSources();

      // Ensure browser fully decodes before we cross-fade; prevents a single-frame flash
      await this.decodeVisibleImage();

      this.animateImageReveal();
      this.markAsLoaded(false);
    } catch (error) {
      console.error(`Failed to load progressive image:`, error);
      this.handleLoadError();
    } finally {
      this.loadingPromise = null;
    }
  }

  async decodeVisibleImage() {
    if (this.imageTarget && this.imageTarget.decode) {
      try {
        await this.imageTarget.decode();
      } catch (_) {
        // Ignore decode errors; browser will still attempt to paint.
      }
    }
  }

  preloadImage() {
    const dataSrc = this.imageTarget.getAttribute("data-src");

    return new Promise((resolve, reject) => {
      const tempImg = new Image();

      const cleanup = () => {
        tempImg.onload = null;
        tempImg.onerror = null;
      };

      tempImg.onload = () => {
        cleanup();
        resolve();
      };

      tempImg.onerror = () => {
        cleanup();
        reject(new Error(`Failed to load image: ${dataSrc}`));
      };

      tempImg.src = dataSrc;
    });
  }

  updateImageSources() {
    const sources = this.pictureTarget.querySelectorAll("source[data-srcset]");
    sources.forEach((source) => {
      const dataSrcset = source.getAttribute("data-srcset");
      if (dataSrcset) {
        source.setAttribute("srcset", dataSrcset);
        source.removeAttribute("data-srcset");
      }
    });

    const dataSrc = this.imageTarget.getAttribute("data-src");
    if (dataSrc) {
      this.imageTarget.src = dataSrc;
      this.imageTarget.removeAttribute("data-src");
    }
  }

  animateImageReveal() {
    this.batchStyleUpdates(() => {
      this.pictureTarget.classList.add("loaded");
      this.placeholderTarget.classList.add("faded");
    });
  }

  applyLoadedStyles(isInstant = false) {
    if (isInstant) {
      this.pictureTarget.classList.add("loaded");
      this.placeholderTarget.classList.add("faded");
    }
  }

  batchStyleUpdates(updateFunction) {
    requestAnimationFrame(() => {
      updateFunction();
    });
  }

  markAsLoaded(cached) {
    this.isLoaded = true;
    this.dispatch("loaded", {
      detail: {
        mediaId: this.mediaIdValue,
        element: this.element,
        cached,
      },
    });
  }

  handleLoadError() {
    this.batchStyleUpdates(() => {
      // Keep placeholder visible; base class has opacity:1
      this.placeholderTarget.style.filter = "none";
      this.element.classList.add("load-error");
    });

    this.dispatch("error", {
      detail: {
        mediaId: this.mediaIdValue,
        element: this.element,
      },
    });
  }

  cleanup() {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }

    if (this.loadingPromise) {
      this.loadingPromise = null;
    }

    this.isLoaded = false;
  }
}
