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
    this.changePic();
    if (this.hasRefreshIntervalValue) {
      this.startRefreshing();
    }
  }

  disconnect() {
    this.stopRefreshing();
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
    if (!document.hidden) {
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
    }
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
      
      // Process the URL to create a cacheable URL
      // First check if it's from epics.entropy.fi
      const originalUrl = data["url"];
      let cachedUrl;
      
      // Only apply the proxy to epics.entropy.fi URLs
      if (originalUrl.includes("epics.entropy.fi")) {
        // Extract the path part after the domain
        const urlObj = new URL(originalUrl);
        const pathPart = urlObj.pathname.replace(/^\/+/, ""); // Remove leading slashes
        cachedUrl = "/epics-proxy/" + pathPart;
      } else {
        // For other URLs, use as is
        cachedUrl = originalUrl;
      }
      
      // Set the image source to the cachedUrl
      newImage.src = cachedUrl;

      newImage.onerror = (error) => {
        // Fallback to direct URL if proxy fails
        if (cachedUrl !== originalUrl) {
          newImage.src = originalUrl;
        }
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
          this.picTarget.setAttribute("src", cachedUrl);

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
