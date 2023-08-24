import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["pic", "source", "badge", "player"];
  static values = {
    url: String,
    refreshInterval: Number,
    onlineImg: String,
    offlineImg: String,
  };
  connect() {
    this.streamStatus();

    if (this.hasRefreshIntervalValue) {
      this.startRefreshing();
    }
  }
  disconnect() {
    this.stopRefreshing();
  }
  streamStatus() {
    fetch(this.urlValue)
      .then((response) => response.text())
      .then((data) => {
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(data, "text/xml");
        const sourceNode = xmlDoc.querySelector("source");

        if (sourceNode) {
          const sourceMount = sourceNode.getAttribute("src");
          const sourceListeners = xmlDoc.querySelectorAll("listeners");
          let listeners = 0;
          sourceListeners.forEach((node) => {
            listeners += Number(node.innerText);
          });
          if (sourceMount) {
            console.log("Icecast server is streaming on mount:", sourceMount);
            this.setStream(200, listeners);
          } else {
            console.log("Icecast server is not currently streaming");
            this.setStream(404, 0);
          }
        } else {
          this.setStream(404, 0);
        }
      })
      .catch((error) => {
        this.setStream(500, 0);
        this.stopRefreshing();
      });
  }
  startRefreshing() {
    if (!document.hidden) {
      this.refreshTimer = setInterval(() => {
        this.streamStatus();
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
  setStream(code, listeners) {
    if (code == 200) {
      this.badgeTarget.innerText = "ONLINE: " + listeners;
      this.badgeTarget.classList.add("bg-success");
      this.playerTarget.classList.remove("d-none");
      this.picTarget.setAttribute("src", this.onlineImgValue);
      this.sourceTarget.setAttribute("src", this.urlValue);
    } else {
      this.badgeTarget.innerText = "OFFLINE";
      this.badgeTarget.classList.remove("bg-success");
      this.playerTarget.classList.add("d-none");
      this.picTarget.setAttribute("src", this.offlineImgValue);
      this.sourceTarget.setAttribute("src", this.urlValue);
    }
  }
}
