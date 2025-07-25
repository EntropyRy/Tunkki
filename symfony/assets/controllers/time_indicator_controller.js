import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["indicator"];
  static values = {
    serverTime: String,
    updateInterval: { type: Number, default: 30000 }, // 30 seconds
  };

  connect() {
    if (!this.hasIndicatorTarget) return;

    // Calculate the time difference between server and client
    this.calculateTimeOffset();

    // Wait for fonts and layout to be ready, then initialize
    this.initializeWhenReady();

    // Set up periodic updates
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

  updateIndicatorPosition() {
    const currentTime = this.getCurrentTime();
    const position = this.calculatePosition(currentTime);

    if (position !== null) {
      this.indicatorTarget.style.transform = `translateY(${position}px)`;
      this.animateArrow();
    }
  }

  recalculateRowPositions() {
    // Force a reflow to get accurate measurements
    this.element.offsetHeight;
    
    // Get fresh row positions
    const rows = Array.from(this.element.querySelectorAll("tr")).filter(
      (row) => row.querySelector(".time") !== null,
    );

    return rows.map((row) => {
      const timeCell = row.querySelector(".time");
      const timeText = timeCell.textContent.trim();
      const timeMatch = timeText.match(/(\d{1,2}):(\d{2})/);
      const hours = parseInt(timeMatch[1], 10);
      const minutes = parseInt(timeMatch[2], 10);

      return {
        row: row,
        hours: hours,
        minutes: minutes,
        totalMinutes: hours * 60 + minutes,
        top: row.offsetTop,
        timeText: timeText,
      };
    });
  }

  animateArrow() {
    // Find the SVG element inside the indicator
    const svg = this.indicatorTarget.querySelector("svg");
    if (!svg) return;

    // Add CSS classes to trigger the animation
    svg.classList.add("update-animation");

    // Remove the class after animation completes to reset for next time
    setTimeout(() => {
      svg.classList.remove("update-animation");
    }, 500); // 1 second matches our animation duration
  }

  calculateTimeOffset() {
    // Calculate the time difference between server and client
    try {
      const serverTime = new Date(this.serverTimeValue);
      const clientTime = new Date();
      this.timeOffset = serverTime.getTime() - clientTime.getTime();
    } catch (e) {
      console.error("Error parsing server time:", e);
      this.timeOffset = 0; // Fallback to no offset
    }
  }

  getCurrentTime() {
    // Return current client time adjusted by server offset
    const now = new Date();
    return new Date(now.getTime() + (this.timeOffset || 0));
  }

  calculatePosition(currentTime) {
    // Get fresh row positions to account for dynamic heights
    const timeSlots = this.recalculateRowPositions();

    if (timeSlots.length === 0) return null;

    // Handle times after midnight (e.g., 00:30, 01:00)
    // If we find a time that's earlier than the previous, assume it's the next day
    for (let i = 1; i < timeSlots.length; i++) {
      if (timeSlots[i].totalMinutes < timeSlots[i - 1].totalMinutes) {
        timeSlots[i].totalMinutes += 24 * 60; // Add a day
      }
    }

    // Get current time in minutes
    const currentHour = currentTime.getHours();
    const currentMinute = currentTime.getMinutes();
    let currentTotalMinutes = currentHour * 60 + currentMinute;

    // Adjust current time for midnight crossing if needed
    // If the first slot is in the evening (e.g., 20:00) and current time is after midnight,
    // we need to add 24 hours to current time for proper comparison
    if (timeSlots[0].hours >= 12 && currentHour < 12) {
      currentTotalMinutes += 24 * 60;
    }

    // Base offset is -10px
    const baseOffset = -10;

    // Before first slot
    if (currentTotalMinutes < timeSlots[0].totalMinutes) {
      return baseOffset; // At the top with base offset
    }

    // Find which slot we're in
    let currentSlotIndex = -1;
    for (let i = 0; i < timeSlots.length; i++) {
      if (i === timeSlots.length - 1) {
        // Last slot
        if (currentTotalMinutes >= timeSlots[i].totalMinutes) {
          currentSlotIndex = i;
        }
      } else if (
        currentTotalMinutes >= timeSlots[i].totalMinutes &&
        currentTotalMinutes < timeSlots[i + 1].totalMinutes
      ) {
        currentSlotIndex = i;
        break;
      }
    }

    // If no slot found (shouldn't happen if we're after the first slot)
    if (currentSlotIndex === -1) {
      return baseOffset;
    }

    const currentSlot = timeSlots[currentSlotIndex];

    // If it's the last slot
    if (currentSlotIndex === timeSlots.length - 1) {
      const minutesIntoSlot = currentTotalMinutes - currentSlot.totalMinutes;

      // If more than 60 minutes after the last slot, stay at the end
      if (minutesIntoSlot > 60) {
        return currentSlot.top + 30 + baseOffset; // 30px default height
      }

      // Linear calculation for the last slot (assume 60 min duration)
      const percentage = minutesIntoSlot / 60;
      return currentSlot.top + baseOffset + 30 * percentage; // 30px default height
    }

    // Calculate position linearly between current slot and next slot
    const nextSlot = timeSlots[currentSlotIndex + 1];
    const slotDuration = nextSlot.totalMinutes - currentSlot.totalMinutes;
    const minutesIntoSlot = currentTotalMinutes - currentSlot.totalMinutes;
    const percentage = minutesIntoSlot / slotDuration;

    // Calculate the row height
    const rowHeight = nextSlot.top - currentSlot.top;

    // Linear calculation: start at current row top + base offset, then add percentage through slot
    return currentSlot.top + baseOffset + rowHeight * percentage;
  }
}
