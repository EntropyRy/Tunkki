import { Controller } from "@hotwired/stimulus";
import moment from "moment/min/moment-with-locales.min.js";

stimulusFetch: "eager";
export default class extends Controller {
  static targets = ["pic", "badge", "progress"];
  static values = {
    url: String,
    refreshInterval: Number,
    defaultPic: String,
  };

  connect() {
    this.defaultPicValue = this.picTarget.getAttribute("src");
    this.isVisible = false;
    this.setupIntersectionObserver();
  }

  disconnect() {
    this.stopRefreshing();
    if (this.observer) {
      this.observer.disconnect();
    }
  }

  changePic() {
    fetch(this.urlValue)
      .then((response) => response.json())
      .then((data) => this.setPic(data))
      .catch((error) => {
        // Silent error handling
      });
  }

  startRefreshing() {
    if (this.isVisible && !document.hidden && this.hasRefreshIntervalValue) {
      this.refreshTimer = setInterval(() => {
        this.changePic();
      }, this.refreshIntervalValue);
    } else {
      this.stopRefreshing();
    }
  }

  stopRefreshing() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    if (this.fadeOutTimer) {
      clearTimeout(this.fadeOutTimer);
      this.fadeOutTimer = null;
    }
  }

  setupIntersectionObserver() {
    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            this.isVisible = true;
            this.changePic();
            this.startRefreshing();
          } else {
            this.isVisible = false;
            this.stopRefreshing();
          }
        });
      },
      {
        threshold: 0.1, // Trigger when 10% of the element is visible
      },
    );

    this.observer.observe(this.element);
  }

  setPic(data) {
    if (data["taken"]) {
      let taken = moment(data["taken"]);
      this.badgeTarget.innerText = taken.format("D.M.yyyy, HH:mm");
    } else {
      this.badgeTarget.innerText = "";
    }

    if (data["url"]) {
      // Remove shimmer class if it exists when showing actual image
      this.picTarget.classList.remove("shimmer");

      // Create a new image element to preload
      const newImage = new Image();

      // Get the original URL from the data and use it directly
      // Cloudflare will handle the caching
      const imageUrl = data["url"];

      // Set the image source
      newImage.src = imageUrl;

      // Simple error handling
      newImage.onerror = () => {
        console.error("Failed to load image");
      };

      newImage.onload = () => {
        // Start fade out of current image
        const fadeOutAnimation = this.picTarget.animate(
          [{ opacity: 1 }, { opacity: 0 }],
          {
            duration: 500,
            easing: "ease",
            fill: "forwards",
          },
        );

        fadeOutAnimation.onfinish = () => {
          // Set the image source directly
          this.picTarget.setAttribute("src", imageUrl);

          // Fade in new image
          this.picTarget.animate([{ opacity: 0 }, { opacity: 1 }], {
            duration: 500,
            easing: "ease",
            fill: "forwards",
          });

          // Animate progress bar
          this.progressTarget.animate([{ width: "100%" }, { width: "0%" }], {
            duration: this.refreshIntervalValue,
            easing: "linear",
            fill: "forwards",
          });

          // Set up the fade out for next change
          if (this.fadeOutTimer) {
            clearTimeout(this.fadeOutTimer);
          }

          this.fadeOutTimer = setTimeout(() => {
            this.picTarget.animate([{ opacity: 1 }, { opacity: 0 }], {
              duration: 500,
              easing: "ease",
            });
          }, this.refreshIntervalValue - 500);
        };
      };
    } else {
      // Only add shimmer class when showing the header logo
      this.picTarget.setAttribute("src", this.defaultPicValue);
      this.picTarget.classList.add("shimmer");
    }
  }
}
