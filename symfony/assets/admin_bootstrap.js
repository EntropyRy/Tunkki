// Admin-specific Stimulus bootstrap: starts a fresh application without auto-loading
// controllers.json (so only explicitly registered controllers run in admin).
import { Application } from "@hotwired/stimulus";

export const app = Application.start();
