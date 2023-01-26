import { Controller } from "@hotwired/stimulus";
import moment from "moment";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["pic", "badge"];
  static values = { url: String, refreshInterval: Number };
  connect() {
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
      .then((data) => this.setPic(data));
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
    //console.log(data['taken']);
    if (data["taken"]) {
      let taken = moment(data["taken"]);
      this.badgeTarget.innerText = taken.format("D.M.yyyy, HH:mm");
    } else {
      this.badgeTarget.innerText = "";
    }
    this.picTarget.classList.remove('shimmer');
    this.picTarget.setAttribute("src", data["url"]);
  }
}
