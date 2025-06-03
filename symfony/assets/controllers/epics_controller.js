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
    document.removeEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
  }
  
  handleVisibilityChange() {
    if (document.hidden) {
      this.stopRefreshing();
    } else if (this.hasRefreshIntervalValue) {
      this.startRefreshing();
    }
  }

  changePic() {
    // Add cache-busting for API responses
    const cacheBuster = `?_=${Date.now()}`;
    
    fetch(`${this.urlValue}${cacheBuster}`)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => this.setPic(data))
      .catch((error) => {
        // Silent error handling
      });
  }

  startRefreshing() {
    if (!document.hidden) {
      // Clear any existing timer first
      this.stopRefreshing();
      
      this.refreshTimer = setInterval(() => {
        this.changePic();
      }, this.refreshIntervalValue);
      
      // Add event listener for visibility changes
      document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
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
      
      // Get the original URL from the data
      const originalUrl = data["url"];
      let cachedUrl = originalUrl;
      
      // Use the proxy for epics.entropy.fi URLs
      if (originalUrl.includes("epics.entropy.fi")) {
        try {
          // For full URLs from epics.entropy.fi, extract everything after the domain
          const parts = originalUrl.split("epics.entropy.fi/");
          if (parts.length > 1) {
            cachedUrl = "/epics-proxy/" + parts[1];
          }
        } catch (e) {
          // If any error in URL processing, use the original
          cachedUrl = originalUrl;
        }
      }
      
      // Set the image source
      newImage.src = cachedUrl;

      // Handle loading errors
      newImage.onerror = () => {
        // If the proxy URL fails and we're not already using the direct URL
        if (cachedUrl !== originalUrl) {
          // Try the direct URL
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
          // Set the image source to cached URL
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
