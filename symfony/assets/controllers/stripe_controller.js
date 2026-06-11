/* stimulusFetch: 'lazy' */
import { Controller } from "@hotwired/stimulus";
import { loadStripe } from '@stripe/stripe-js';

export default class extends Controller {
  static values  = { publicKey: String, clientSecret: String, time: Number };
  static targets = ['payment', 'submit', 'error', 'total', 'express', 'divider'];

  async connect() {
    this.stripe = await loadStripe(this.publicKeyValue);

    // initCheckoutElementsSdk is synchronous — data loads in background
    this.checkout = this.stripe.initCheckoutElementsSdk({
      clientSecret: this.clientSecretValue,
      elementsOptions: { appearance: this.buildAppearance() },
    });

    this.checkout.on('change', (session) => {
      this.submitTarget.disabled = !session.canConfirm;
      const amount = typeof session.total === 'number'
        ? session.total
        : session.total?.total;
      if (Number.isFinite(amount) && amount > 0) {
        this.totalTarget.textContent =
          (amount / 100).toLocaleString(document.documentElement.lang, {
            style: 'currency',
            currency: session.currency,
          });
      }
    });

    const expressElement = this.checkout.createExpressCheckoutElement();
    expressElement.on('ready', ({ availablePaymentMethods }) => {
      if (availablePaymentMethods) {
        this.dividerTarget.classList.remove('d-none');
      } else {
        this.expressTarget.remove();
      }
    });
    expressElement.on('confirm', async () => {
      const { actions } = await this.checkout.loadActions();
      const { error }   = await actions.confirm();
      if (error) {
        this.errorTarget.textContent = error.message;
      }
    });
    expressElement.mount(this.expressTarget);

    this.checkout.createPaymentElement().mount(this.paymentTarget);

    // Expire after 30 minutes — same as before
    setTimeout(() => window.history.back(), 1800000);
  }

  async pay(event) {
    event.preventDefault();
    this.submitTarget.disabled = true;
    this.errorTarget.textContent = '';

    const { actions } = await this.checkout.loadActions();
    const { error }   = await actions.confirm();

    if (error) {
      this.errorTarget.textContent = error.message;
      this.submitTarget.disabled = false;
    }
    // On success Stripe redirects to return_url automatically
  }

  disconnect() {
    this.checkout.destroy();
  }

  buildAppearance() {
    const isDark =
      document.documentElement.getAttribute('data-bs-theme') === 'dark';
    return { theme: isDark ? 'night' : 'stripe' };
  }
}
