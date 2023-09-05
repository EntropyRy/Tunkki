import Masonry from "masonry-layout";
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["masonry"];
  connect() {
    const msnry = new Masonry(this.masonryTarget, {
      percentPosition: true,
    });
  }
}
