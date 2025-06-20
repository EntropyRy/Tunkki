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
    // console.log("Progressive image controller connected");
    // console.log("Targets found:", {
    //   placeholder: this.hasPlaceholderTarget,
    //   picture: this.hasPictureTarget,
    //   image: this.hasImageTarget,
    // });
    //
    this.placeholder = this.placeholderTarget;
    this.picture = this.pictureTarget;
    this.mainImage = this.imageTarget;
    this.observer = null;

    // If lazy is false, load immediately, otherwise use intersection observer
    if (this.lazyValue === false) {
      this.loadProgressiveImage();
    } else {
      this.setupIntersectionObserver();
    }
  }

  disconnect() {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
  }

  setupIntersectionObserver() {
    if (!window.IntersectionObserver) {
      this.loadProgressiveImage();
      return;
    }

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            this.loadProgressiveImage();
            this.observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.1,
        rootMargin: "50px",
      },
    );

    this.observer.observe(this.element);
  }

  loadProgressiveImage() {
    const sources = this.picture.querySelectorAll("source[data-srcset]");
    const img = this.mainImage;

    sources.forEach((source) => {
      const dataSrcset = source.getAttribute("data-srcset");
      if (dataSrcset) {
        source.setAttribute("srcset", dataSrcset);
        source.removeAttribute("data-srcset");
      }
    });

    const dataSrc = img.getAttribute("data-src");
    if (dataSrc) {
      const tempImg = new Image();

      tempImg.onload = () => {
        img.src = dataSrc;
        img.removeAttribute("data-src");

        this.picture.style.opacity = "1";
        this.picture.classList.add("loaded");

        setTimeout(() => {
          this.placeholder.style.opacity = "0";
          this.placeholder.classList.add("faded");
        }, 50);

        this.dispatch("loaded", {
          detail: {
            mediaId: this.mediaIdValue,
            element: this.element,
          },
        });
      };

      tempImg.onerror = () => {
        console.error(`Failed to load progressive image: ${dataSrc}`);
        this.handleLoadError();
      };

      tempImg.src = dataSrc;
    }
  }

  handleLoadError() {
    this.placeholder.style.filter = "none";
    this.placeholder.style.opacity = "1";
    this.element.classList.add("load-error");

    this.dispatch("error", {
      detail: {
        mediaId: this.mediaIdValue,
        element: this.element,
      },
    });
  }
}
