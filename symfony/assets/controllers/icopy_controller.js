import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["input"];
  connect() {}
  copyClass({ params: { iclass } }) {
    this.inputTarget.value = iclass;
  }
}
