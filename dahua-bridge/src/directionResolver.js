import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

function today() {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Decides whether a scan is a check-in or a check-out.
 *
 * `fixed` mode: this whole bridge instance only ever reports one
 * direction (`FIXED_DIRECTION=check_in` or `check_out`) — for a site
 * with two separate terminals, one on the entry side of a gate and one
 * on the exit side, each its own IP. Which physical device an event
 * came from already says everything; run one bridge instance per
 * terminal (see the README's two-terminal setup).
 *
 * `field` mode trusts a device field (see config.js's DIRECTION_FIELD) —
 * for a single terminal with two readers wired to report which one was
 * used.
 *
 * `toggle` mode (the default) needs no such field: each person's first
 * scan on a given calendar day is a check-in, their next one that day is
 * a check-out, and so on. This is the only direction signal a single-lane
 * turnstile with one reader can give, and it is what most such
 * installations actually are. State lives in a small JSON file next to
 * the queue (`data/direction-state.json`) so a bridge restart mid-day
 * doesn't turn everyone's next scan back into a check-in; it resets
 * itself once the calendar date in the file is no longer today.
 */
export class DirectionResolver {
  constructor(config) {
    this.config = config;
    this.statePath = join(config.dataDir, 'direction-state.json');
    this.state = this._load();
  }

  resolve(event) {
    if (this.config.direction.mode === 'fixed') {
      return this.config.direction.fixedDirection;
    }

    if (this.config.direction.mode === 'field') {
      const value = event.directionFieldValue;

      if (value !== undefined) {
        if (this.config.direction.entryValues.includes(value)) return 'check_in';
        if (this.config.direction.exitValues.includes(value)) return 'check_out';
      }

      // The configured field wasn't present, or held a value neither list
      // recognizes — fall through to toggling rather than guessing wrong
      // silently, since a missing/misconfigured field is a setup mistake
      // this bridge cannot fix on its own.
    }

    return this._toggle(event.personId);
  }

  _toggle(personId) {
    if (this.state.date !== today()) {
      this.state = { date: today(), lastDirection: {} };
    }

    const last = this.state.lastDirection[personId];
    const next = last === 'check_in' ? 'check_out' : 'check_in';

    this.state.lastDirection[personId] = next;
    this._save();

    return next;
  }

  _load() {
    if (existsSync(this.statePath)) {
      try {
        const parsed = JSON.parse(readFileSync(this.statePath, 'utf8'));

        if (parsed.date === today()) {
          return parsed;
        }
      } catch {
        // A corrupted state file starts fresh rather than crashing the bridge.
      }
    }

    return { date: today(), lastDirection: {} };
  }

  _save() {
    mkdirSync(this.config.dataDir, { recursive: true });
    writeFileSync(this.statePath, JSON.stringify(this.state));
  }
}
