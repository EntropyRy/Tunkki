import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["indicator"];
  static values = {
    serverTime: String,
    eventStart: String,
    eventEnd: String,
    slotTimes: String,
    // Position recalculation interval (less frequent to reduce layout work)
    updateInterval: { type: Number, default: 30000 }, // 30 seconds
  };

  connect() {
    if (!this.hasIndicatorTarget) return;

    // Parse event bounds (optional)
    this.eventStartDate = this.hasEventStartValue
      ? new Date(this.eventStartValue)
      : null;
    this.eventEndDate = this.hasEventEndValue
      ? new Date(this.eventEndValue)
      : null;

    // Parse slot times
    this.parseSlotTimes();
    // Calculate the time difference between server and client
    this.calculateTimeOffset();

    // Wait for fonts and layout to be ready, then initialize
    this.initializeWhenReady();

    // Set up periodic position updates (heavier work)
    this.intervalId = setInterval(() => {
      this.updateIndicatorPosition();
    }, this.updateIntervalValue);
  }

  initializeWhenReady() {
    // Use requestAnimationFrame to ensure DOM is fully rendered
    requestAnimationFrame(() => {
      // Double-check fonts are loaded if possible
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => {
          this.updateIndicatorPosition();
        });
      } else {
        this.updateIndicatorPosition();
      }
    });
  }

  disconnect() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  }

  hideIndicator() {
    if (this.hasIndicatorTarget) {
      // Keep layout space; just visually hide
      this.indicatorTarget.style.visibility = "hidden";
      this.indicatorTarget.style.opacity = "0";
    }
  }

  showIndicator() {
    if (this.hasIndicatorTarget) {
      this.indicatorTarget.style.visibility = "visible";
      this.indicatorTarget.style.opacity = "1";
    }
  }

  updateIndicatorPosition() {
    const currentTime = this.getCurrentTime();

    // Need parsed slot timestamps to decide visibility/position
    if (!this.slotTimestampsMs || this.slotTimestampsMs.length === 0) {
      this.hideIndicator();
      return;
    }

    const firstSlotMs = this.slotTimestampsMs[0];
    const lastSlotMs = this.slotTimestampsMs[this.slotTimestampsMs.length - 1];
    // Assume a slot lasts up to 60 minutes after its start for end boundary if no explicit eventEnd
    const inferredEndMs = lastSlotMs + 60 * 60 * 1000;

    const windowStart = this.eventStartDate
      ? this.eventStartDate.getTime()
      : firstSlotMs;
    const windowEnd = this.eventEndDate
      ? this.eventEndDate.getTime()
      : inferredEndMs;

    if (
      currentTime.getTime() < windowStart ||
      currentTime.getTime() > windowEnd
    ) {
      this.hideIndicator();
      return;
    }

    this.showIndicator();

    const position = this.calculatePosition(currentTime);
    if (position !== null) {
      this.indicatorTarget.style.transform = `translateY(${position}px)`;
    }
  }

  recalculateRowPositions() {
    // Force a reflow to get accurate measurements
    this.element.offsetHeight;

    // Get fresh row positions mapped against provided slotTimes
    const rows = Array.from(this.element.querySelectorAll("tr")).filter(
      (row) => row.querySelector(".time") !== null,
    );

    const slots = [];
    rows.forEach((row, idx) => {
      const tsMs = this.slotTimestampsMs && this.slotTimestampsMs[idx];
      if (typeof tsMs === "number") {
        slots.push({
          row,
          timestampMs: tsMs,
          top: row.offsetTop,
        });
      }
    });

    // If counts differ, we still sort by timestamp to be safe
    slots.sort((a, b) => a.timestampMs - b.timestampMs);
    return slots;
  }

  parseSlotTimes() {
    this.slotTimestampsMs = [];
    if (!this.hasSlotTimesValue || !this.slotTimesValue) return;
    this.slotTimestampsMs = this.slotTimesValue
      .split(",")
      .map((s) => parseInt(s.trim(), 10))
      .filter((n) => !isNaN(n))
      .map((sec) => sec * 1000)
      .sort((a, b) => a - b);
  }

  calculateTimeOffset() {
    try {
      const serverTime = new Date(this.serverTimeValue);
      const clientTime = new Date();
      this.timeOffset = serverTime.getTime() - clientTime.getTime();
    } catch (e) {
      console.error("Error parsing server time:", e);
      this.timeOffset = 0;
    }
  }

  getCurrentTime() {
    const now = new Date();
    return new Date(now.getTime() + (this.timeOffset || 0));
  }

  calculatePosition(currentTime) {
    const timeSlots = this.recalculateRowPositions();
    if (timeSlots.length === 0) return null;

    const currentMs = currentTime.getTime();
    const baseOffset = -10;

    // Before first slot or after last slot is handled by event bounds; still position at extremes
    if (currentMs <= timeSlots[0].timestampMs) {
      return baseOffset;
    }

    // Find current slot index
    let currentSlotIndex = -1;
    for (let i = 0; i < timeSlots.length; i++) {
      if (i === timeSlots.length - 1) {
        if (currentMs >= timeSlots[i].timestampMs) {
          currentSlotIndex = i;
        }
      } else if (
        currentMs >= timeSlots[i].timestampMs &&
        currentMs < timeSlots[i + 1].timestampMs
      ) {
        currentSlotIndex = i;
        break;
      }
    }

    if (currentSlotIndex === -1) {
      return baseOffset;
    }

    const currentSlot = timeSlots[currentSlotIndex];

    // Last slot logic
    if (currentSlotIndex === timeSlots.length - 1) {
      const minutesIntoSlot =
        (currentMs - currentSlot.timestampMs) / (60 * 1000);
      if (minutesIntoSlot > 60) {
        return currentSlot.top + 30 + baseOffset;
      }
      const percentage = minutesIntoSlot / 60;
      return currentSlot.top + baseOffset + 30 * percentage;
    }

    // Interpolate between current and next slot
    const nextSlot = timeSlots[currentSlotIndex + 1];
    const slotDurationMs = nextSlot.timestampMs - currentSlot.timestampMs;
    const elapsedMs = currentMs - currentSlot.timestampMs;
    const percentage = slotDurationMs > 0 ? elapsedMs / slotDurationMs : 0;
    const rowHeight = nextSlot.top - currentSlot.top;

    return currentSlot.top + baseOffset + rowHeight * percentage;
  }
}
