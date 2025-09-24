import { Controller } from "@hotwired/stimulus";

/**
 * Simplified theme switcher for guests.
 * - Uses localStorage key: 'user-theme'
 * - Applies stored preference on connect (if any)
 * - Toggles immediately without delays or extra attributes
 *
 * Logged-in users should not have this controller attached; they use profile edit to change theme.
 */
export default class extends Controller {
  connect() {
    const stored = localStorage.getItem("user-theme");
    if (
      stored &&
      stored !== document.documentElement.getAttribute("data-bs-theme")
    ) {
      document.documentElement.setAttribute("data-bs-theme", stored);
    }
  }

  toggle() {
    const html = document.documentElement;
    const current = html.getAttribute("data-bs-theme") || "light";
    const next = current === "dark" ? "light" : "dark";

    html.setAttribute("data-bs-theme", next);
    try {
      localStorage.setItem("user-theme", next);
    } catch (_e) {
      // Ignore storage failures (private mode, quota, etc.)
    }
  }
}
