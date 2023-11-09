import { Controller } from "@hotwired/stimulus";

stimulusFetch: "lazy";
export default class extends Controller {
  static targets = ["product", "quantity", "formquantity"];
  static values = {
    quantity: Number,
    max: Number,
  };
  connect() {
    this.quantityTarget.innerText = 0;
    // console.log(this.formquantityTarget);
    this.formquantityTarget.value = 0;
  }
  plus(event) {
    event.preventDefault();
    if (this.quantityTarget.innerText < this.maxValue) {
      this.quantityTarget.innerText++;
      this.formquantityTarget.value++;
    }
  }
  minus(event) {
    event.preventDefault();
    if (this.quantityTarget.innerText > 0) {
      this.quantityTarget.innerText--;
      this.formquantityTarget.value--;
    }
  }
}
