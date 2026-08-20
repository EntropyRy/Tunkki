import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["indicator"];
  static values = {
    serverTime: String,
    eventStart: String,
    eventEnd: String,
    slotTimes: String,
    // Position recalculation interval
    updateInterval: { type: Number, default: 30000 }, // 30 seconds
  };

  connect() {
    if (!this.hasIndicatorTarget) return;

    this.eventStartDate = this.hasEventStartValue
      ? new Date(this.eventStartValue)
      : null;
    this.eventEndDate = this.hasEventEndValue
      ? new Date(this.eventEndValue)
      : null;

    this.parseSlotTimes();
    this.calculateTimeOffset();
    this.initializeWhenReady();

    this.intervalId = setInterval(() => {
      this.updateIndicatorPosition();
    }, this.updateIntervalValue);
  }

  initializeWhenReady() {
    requestAnimationFrame(() => {
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => this.updateIndicatorPosition());
      } else {
        this.updateIndicatorPosition();
      }
    });
  }

  disconnect() {
    if (this.intervalId) clearInterval(this.intervalId);
  }

  hideIndicator() {
    if (this.hasIndicatorTarget) {
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
    const now = this.getCurrentTime();
    if (!this.slotTimestampsMs || this.slotTimestampsMs.length === 0) {
      this.hideIndicator();
      return;
    }

    const firstSlotMs = this.slotTimestampsMs[0];
    const lastSlotMs = this.slotTimestampsMs[this.slotTimestampsMs.length - 1];

    // Window start = event start (if provided) else first slot
    const windowStart = this.eventStartDate
      ? this.eventStartDate.getTime()
      : firstSlotMs;

    // Vanish rule: half of the gap between the last two slots (fallback 60m if only one slot)
    let vanishMs;
    if (this.slotTimestampsMs.length >= 2) {
      const prevSlotMs =
        this.slotTimestampsMs[this.slotTimestampsMs.length - 2];
      const gap = lastSlotMs - prevSlotMs;
      vanishMs = lastSlotMs + gap * 0.5;
    } else {
      vanishMs = lastSlotMs + 60 * 60 * 1000;
    }

    // Hide outside window
    if (now.getTime() < windowStart || now.getTime() > vanishMs) {
      this.hideIndicator();
      return;
    }

    // Recompute slot layout
    const timeSlots = this.recalculateRowPositions();
    if (timeSlots.length === 0) {
      this.hideIndicator();
      return;
    }

    this.showIndicator();

    const position = this.calculatePosition(now.getTime(), timeSlots);
    if (position !== null) {
      this.indicatorTarget.style.transform = `translateY(${position}px)`;
    }
  }

  // Always-accurate crawl: the indicator's position is a straight-line
  // interpolation between the two real slot timestamps bracketing `nowMs` -
  // no invented "active window" or settle threshold, just the actual times.
  calculatePosition(nowMs, timeSlots) {
    const baseOffset = -10;

    if (nowMs <= timeSlots[0].timestampMs) {
      return timeSlots[0].top + baseOffset;
    }

    for (let i = 0; i < timeSlots.length - 1; i++) {
      const current = timeSlots[i];
      const next = timeSlots[i + 1];
      if (nowMs >= current.timestampMs && nowMs < next.timestampMs) {
        const pct =
          (nowMs - current.timestampMs) /
          (next.timestampMs - current.timestampMs);
        return current.top + baseOffset + (next.top - current.top) * pct;
      }
    }

    // Past the last slot's own start - nothing further to interpolate
    // toward, settle there.
    const last = timeSlots[timeSlots.length - 1];
    return last.top + baseOffset;
  }

  recalculateRowPositions() {
    this.element.offsetHeight; // force reflow
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
}
