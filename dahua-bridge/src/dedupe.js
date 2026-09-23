/**
 * Suppresses a second scan of the same person within a short window — a
 * lingering face read or a double card-tap can fire the terminal's event
 * twice for one physical pass. A genuine second visit is never this
 * close together, so nothing legitimate is lost.
 */
export class ScanDeduper {
  constructor(windowSeconds, now = () => Date.now()) {
    this.windowMs = windowSeconds * 1000;
    this.now = now;
    this.lastSeenAt = new Map();
  }

  /** @returns {boolean} true if this scan should be processed, false if it's a repeat to drop */
  shouldProcess(personId) {
    const at = this.now();
    const last = this.lastSeenAt.get(personId);

    this.lastSeenAt.set(personId, at);

    // Bound memory use — a long-running process would otherwise accumulate
    // one entry per person forever.
    if (this.lastSeenAt.size > 5000) {
      this.lastSeenAt.clear();
      this.lastSeenAt.set(personId, at);
    }

    return last === undefined || at - last > this.windowMs;
  }
}
