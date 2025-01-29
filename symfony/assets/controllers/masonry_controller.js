// masonry_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["container", "item"];

  connect() {
    this.resizeObserver = new ResizeObserver(() => {
      requestAnimationFrame(() => this.updateLayout());
    });

    this.itemTargets.forEach((item) => {
      this.resizeObserver.observe(item);
    });

    window.addEventListener("resize", () => {
      requestAnimationFrame(() => this.updateLayout());
    });
    this.updateLayout();
  }

  disconnect() {
    this.resizeObserver.disconnect();
    window.removeEventListener("resize", this.updateLayout.bind(this));
  }

  updateLayout() {
    // Reset transforms and set fit-content height
    this.itemTargets.forEach((item) => {
      item.style.transform = "";
      item.style.height = "fit-content";
    });

    const columnCount = this.getColumnCount();

    // Reset container height
    this.containerTarget.style.height = "";

    // If single column, return early without setting any transforms or height
    if (columnCount <= 1) {
      return;
    }

    // Group items by columns
    const columns = new Array(columnCount).fill().map(() => []);
    this.itemTargets.forEach((item, index) => {
      columns[index % columnCount].push(item);
    });

    // Track the maximum bottom position
    let maxBottom = 0;

    // Process each column
    columns.forEach((column) => {
      let expectedTop = 0;

      column.forEach((item, index) => {
        const itemRect = item.getBoundingClientRect();
        const containerRect = this.containerTarget.getBoundingClientRect();
        const currentTop = itemRect.top - containerRect.top;
        let moveUp = 0;

        if (index === 0) {
          expectedTop = currentTop;
        } else {
          moveUp = currentTop - expectedTop;
          if (moveUp > 0) {
            item.style.transform = `translateY(-${moveUp}px)`;
          }
        }

        expectedTop += item.offsetHeight;
        // Track the bottom position of this item after transform
        const itemBottom =
          itemRect.top - containerRect.top + item.offsetHeight - moveUp;
        maxBottom = Math.max(maxBottom, itemBottom);
      });
    });

    // Only set container height if we're not in single column mode
    this.containerTarget.style.height = `${maxBottom}px`;
  }

  getColumnCount() {
    const containerWidth = this.containerTarget.offsetWidth;
    const itemWidth = this.itemTargets[0]?.offsetWidth || 0;
    return itemWidth ? Math.floor(containerWidth / itemWidth) : 1;
  }

  refresh() {
    requestAnimationFrame(() => this.updateLayout());
  }
}
