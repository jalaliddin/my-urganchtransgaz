import { appendFileSync, existsSync, mkdirSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * A durable, append-only queue of attendance events still waiting to
 * reach my.urtg.uz — the whole reason this bridge keeps a local queue at
 * all is that "lokal turadi" (it runs on-site) must survive the site's
 * internet connection dropping without losing scans, not just retry them
 * in memory until the process happens to restart.
 *
 * Stored as newline-delimited JSON so a crash mid-write only ever
 * corrupts the last, still-unflushed line, never the file as a whole.
 */
export class OfflineQueue {
  constructor(dataDir) {
    this.path = join(dataDir, 'queue.ndjson');
    mkdirSync(dataDir, { recursive: true });

    if (!existsSync(this.path)) {
      writeFileSync(this.path, '');
    }
  }

  enqueue(event) {
    appendFileSync(this.path, `${JSON.stringify(event)}\n`);
  }

  /** All currently queued events, oldest first, skipping any unparseable trailing line. */
  peekAll() {
    return readFileSync(this.path, 'utf8')
      .split('\n')
      .filter(Boolean)
      .map((line) => {
        try {
          return JSON.parse(line);
        } catch {
          return null;
        }
      })
      .filter((event) => event !== null);
  }

  /**
   * Rewrites the queue to contain only `remaining` — called after a flush
   * attempt with whatever wasn't successfully delivered (or wasn't
   * attempted this round). Written to a temp file and renamed into place
   * so a crash mid-rewrite can't leave a half-written queue file.
   */
  replaceWith(remaining) {
    const tempPath = `${this.path}.tmp`;
    writeFileSync(tempPath, remaining.map((event) => JSON.stringify(event)).join('\n') + (remaining.length ? '\n' : ''));
    renameSync(tempPath, this.path);
  }

  get size() {
    return this.peekAll().length;
  }
}
