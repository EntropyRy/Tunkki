import { Controller } from "@hotwired/stimulus";

stimulusFetch: "lazy";

export default class extends Controller {
  static targets = ["input"];
  connect() {}
  copyClass({ params: { iclass } }) {
    this.inputTarget.value = iclass;
  }
}
