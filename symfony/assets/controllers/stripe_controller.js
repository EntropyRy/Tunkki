/* stimulusFetch: 'lazy' */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static values = { publicKey: String, clientSecret: String, time: Number };
  static stripe;

  async connect() {
    this.stripe = Stripe(this.publicKeyValue);
    this.checkout = await this.stripe.initEmbeddedCheckout({
      clientSecret: this.clientSecretValue,
    });
    this.checkout.mount(this.element);
    setTimeout(function () {
      window.history.back();
    }, 1800000);
  }

  disconnect() {
    this.checkout.destroy();
  }
}
