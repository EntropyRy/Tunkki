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

    // Identify current slot index (standard linear interpolation across all gaps)
    let idx = -1;
    for (let i = 0; i < timeSlots.length; i++) {
      if (i === timeSlots.length - 1) {
        if (now.getTime() >= timeSlots[i].timestampMs) idx = i;
      } else if (
        now.getTime() >= timeSlots[i].timestampMs &&
        now.getTime() < timeSlots[i + 1].timestampMs
      ) {
        idx = i;
        break;
      }
    }
    if (idx === -1) idx = 0;

    this.showIndicator();

    // Position calculation
    const position = this.calculatePositionWithSlots(now, timeSlots);
    if (position !== null) {
      this.indicatorTarget.style.transform = `translateY(${position}px)`;
    }

    // Dynamic label state
    this.updateNowLabel(now, idx, timeSlots);
  }

  updateNowLabel(now, idx, timeSlots) {
    if (!this.indicatorTarget) return;
    // We only keep the original innerHTML (NOW label + icon) when "active"
    // Determine active range: from slot start to either next slot start or slot start + 60m if last.
    const slotStart = timeSlots[idx].timestampMs;
    let slotEnd;
    if (idx === timeSlots.length - 1) {
      slotEnd = slotStart + 60 * 60 * 1000;
    } else {
      slotEnd = timeSlots[idx + 1].timestampMs;
    }
    const active = now.getTime() >= slotStart && now.getTime() < slotEnd;
    if (active) {
      this.indicatorTarget.classList.add("is-active");
    } else {
      this.indicatorTarget.classList.remove("is-active");
    }
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

  calculatePositionWithSlots(currentTime, timeSlots) {
    if (!timeSlots.length) return null;
    const currentMs = currentTime.getTime();
    const baseOffset = -10;

    if (currentMs <= timeSlots[0].timestampMs) return baseOffset;

    let currentSlotIndex = -1;
    for (let i = 0; i < timeSlots.length; i++) {
      if (i === timeSlots.length - 1) {
        if (currentMs >= timeSlots[i].timestampMs) currentSlotIndex = i;
      } else if (
        currentMs >= timeSlots[i].timestampMs &&
        currentMs < timeSlots[i + 1].timestampMs
      ) {
        currentSlotIndex = i;
        break;
      }
    }
    if (currentSlotIndex === -1) return baseOffset;

    const currentSlot = timeSlots[currentSlotIndex];

    if (currentSlotIndex === timeSlots.length - 1) {
      const minutesInto = (currentMs - currentSlot.timestampMs) / (60 * 1000);
      if (minutesInto > 60) return currentSlot.top + 30 + baseOffset;
      const pct = minutesInto / 60;
      return currentSlot.top + baseOffset + 30 * pct;
    }

    const nextSlot = timeSlots[currentSlotIndex + 1];
    const duration = nextSlot.timestampMs - currentSlot.timestampMs;
    const elapsed = currentMs - currentSlot.timestampMs;
    const pct = duration > 0 ? elapsed / duration : 0;
    const rowHeight = nextSlot.top - currentSlot.top;
    return currentSlot.top + baseOffset + rowHeight * pct;
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
