import { Controller } from "@hotwired/stimulus";

stimulusFetch: "lazy";
export default class extends Controller {
  static targets = [
    "plus",
    "minus",
    "quantity",
    "formquantity",
    "callopse",
    "chevron",
  ];
  static values = {
    quantity: Number,
    max: Number,
  };
  connect() {
    this.formquantityTarget.value = 0;
    this.minusTarget.classList.add("disabled");
  }
  plus(event) {
    event.preventDefault();
    if (this.quantityTarget.innerText < this.maxValue) {
      this.quantityTarget.innerText++;
      this.formquantityTarget.value++;
      this.minusTarget.classList.remove("disabled");
      if (this.quantityTarget.innerText == this.maxValue) {
        this.plusTarget.classList.add("disabled");
      }
    }
  }
  minus(event) {
    event.preventDefault();
    if (this.quantityTarget.innerText > 0) {
      this.quantityTarget.innerText--;
      this.formquantityTarget.value--;
      this.plusTarget.classList.remove("disabled");
      if (this.quantityTarget.innerText == "0") {
        this.minusTarget.classList.add("disabled");
      }
    }
  }
  callopse(event) {
    event.preventDefault();
    this.callopseTarget.classList.toggle("callopsed");
    // Toggle the chevron icon
    // go through children of the chevronTarget and toggle d-none class
    const icons = this.chevronTarget.children;
    Array.from(icons).forEach((child) => {
      child.classList.toggle("d-none");
    });
  }
}
