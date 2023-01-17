import { Controller } from "@hotwired/stimulus";
import moment from "moment";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["pic", "badge"];
  static values = { url: String, refreshInterval: Number };
  connect() {
    this.changePic();

    if (this.hasRefreshIntervalValue) {
      if (!document.hidden) {
        this.startRefreshing();
      } else {
        this.stopRefreshing();
      }
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
    this.refreshTimer = setInterval(() => {
      this.changePic();
    }, this.refreshIntervalValue);
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
    this.picTarget.setAttribute("src", data["url"]);
  }
}
