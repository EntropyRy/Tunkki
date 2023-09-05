import { Controller } from "@hotwired/stimulus";
import moment from "moment";

export default class extends Controller {
  static targets = ["badge"];
  static values = { locale: String, date: String, refreshInterval: Number };
  connect() {
    this.changeTime();
    if (this.hasRefreshIntervalValue) {
      this.startRefreshing();
    }
  }
  disconnect() {
    this.stopRefreshing();
  }
  changeTime() {
    moment.locale(this.localeValue);
    let date = moment(this.dateValue);
    this.badgeTarget.innerText = date.fromNow();
  }
  startRefreshing() {
    this.refreshTimer = setInterval(() => {
      this.changeTime();
    }, this.refreshIntervalValue);
  }

  stopRefreshing() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
    }
  }
}
