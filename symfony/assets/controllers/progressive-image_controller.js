// assets/controllers/progressive-image_controller.js

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

    // Check cache first, then determine loading strategy
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

    // Batch DOM operations for better performance
    this.batchStyleUpdates(() => {
      this.disableTransitions();
      this.updateImageSources();
      this.applyLoadedStyles(true);
    });

    this.markAsLoaded(true);
  }

  async loadProgressiveImage() {
    if (this.isLoaded || this.loadingPromise) return;

    try {
      this.loadingPromise = this.preloadImage();
      await this.loadingPromise;

      this.updateImageSources();
      this.animateImageReveal();
      this.markAsLoaded(false);
    } catch (error) {
      console.error(`Failed to load progressive image:`, error);
      this.handleLoadError();
    } finally {
      this.loadingPromise = null;
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
    // Update all source elements
    const sources = this.pictureTarget.querySelectorAll("source[data-srcset]");
    sources.forEach((source) => {
      const dataSrcset = source.getAttribute("data-srcset");
      if (dataSrcset) {
        source.setAttribute("srcset", dataSrcset);
        source.removeAttribute("data-srcset");
      }
    });

    // Update main image
    const dataSrc = this.imageTarget.getAttribute("data-src");
    if (dataSrc) {
      this.imageTarget.src = dataSrc;
      this.imageTarget.removeAttribute("data-src");
    }
  }

  disableTransitions() {
    const elements = [this.placeholderTarget, this.pictureTarget];
    elements.forEach((el) => {
      el.style.transition = "none";
      // Force reflow
      el.offsetHeight;
    });
  }

  animateImageReveal() {
    this.batchStyleUpdates(() => {
      this.pictureTarget.style.opacity = "1";
      this.pictureTarget.classList.add("loaded");
    });

    // Delay placeholder fade for smoother transition
    setTimeout(() => {
      this.batchStyleUpdates(() => {
        this.placeholderTarget.style.opacity = "0";
        this.placeholderTarget.classList.add("faded");
      });
    }, 50);
  }

  applyLoadedStyles(isInstant = false) {
    if (isInstant) {
      this.placeholderTarget.style.opacity = "0";
      this.pictureTarget.style.opacity = "1";
      this.pictureTarget.classList.add("loaded");
    }
  }

  batchStyleUpdates(updateFunction) {
    // Use requestAnimationFrame to batch DOM updates
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
      this.placeholderTarget.style.filter = "none";
      this.placeholderTarget.style.opacity = "1";
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
