import { EventEmitter } from 'node:events';

/**
 * Incrementally parses a Dahua `multipart/x-mixed-replace` event stream
 * (see the vendor's ACCESS CONTROL PRODUCTS INTEGRATION INSTRUCTION,
 * "Subscribe to real-time events") into discrete parts as bytes arrive on
 * the socket — one part per `--boundary` section, each with its own small
 * header block (`Content-Type`, usually `Content-Length`) followed by a
 * body that is either a flat `key=value` event listing, a JPEG snapshot,
 * or the literal text "Heartbeat".
 *
 * A part's body is read by its declared `Content-Length` whenever one is
 * given, never by scanning for the next boundary inside it — a JPEG
 * snapshot's bytes can coincidentally contain the boundary text, and only
 * the length is authoritative. Scanning for the next boundary is used
 * only as a fallback for the rare part with no declared length.
 *
 * Emits:
 *  - 'part' `{ headers: Map<string,string>, body: Buffer }` for each part
 *  - 'end' when the stream's closing `--boundary--` is seen
 *  - 'error' if the stream stops looking like this format at all
 */
export class MultipartStreamParser extends EventEmitter {
  constructor(boundary) {
    super();
    this.delimiter = Buffer.from(`--${boundary}`);
    this.buffer = Buffer.alloc(0);
    this.state = 'seek-boundary';
    this.pendingHeaders = null;
  }

  write(chunk) {
    this.buffer = this.buffer.length ? Buffer.concat([this.buffer, chunk]) : Buffer.from(chunk);
    this._process();
  }

  /** Call when the underlying connection closes; drops any partial part. */
  end() {
    this.buffer = Buffer.alloc(0);
  }

  _process() {
    // A single incoming chunk can contain several complete parts (or
    // finish one and start the next), hence the loop rather than one
    // pass — each state either advances and `continue`s, or returns
    // because it needs bytes that haven't arrived yet.
    for (;;) {
      if (this.state === 'seek-boundary') {
        if (!this._advancePastBoundary()) {
          return;
        }

        continue;
      }

      if (this.state === 'headers') {
        if (!this._consumeHeaders()) {
          return;
        }

        continue;
      }

      if (this.state === 'body') {
        if (!this._consumeBody()) {
          return;
        }

        continue;
      }

      if (this.state === 'body-trailer') {
        if (!this._consumeBodyTrailer()) {
          return;
        }

        continue;
      }

      return; // 'ended', or any other state with nothing left to do
    }
  }

  /** @returns {boolean} whether it advanced (false = needs more data, or the stream ended) */
  _advancePastBoundary() {
    const index = this.buffer.indexOf(this.delimiter);

    if (index === -1) {
      // Keep only a tail as long as the delimiter so a boundary split
      // across two chunks is still found, without the buffer growing
      // forever while skipping non-boundary noise.
      if (this.buffer.length > this.delimiter.length) {
        this.buffer = this.buffer.subarray(this.buffer.length - this.delimiter.length);
      }

      return false;
    }

    let cursor = index + this.delimiter.length;

    if (this.buffer.length < cursor + 2) {
      return false; // not enough bytes yet to know if this is "--" (end) or a line ending
    }

    if (this.buffer[cursor] === 0x2d && this.buffer[cursor + 1] === 0x2d) {
      this.emit('end');
      this.buffer = Buffer.alloc(0);
      this.state = 'ended';

      return false;
    }

    cursor = this._skipLineEnding(cursor);
    if (cursor === -1) {
      return false;
    }

    this.buffer = this.buffer.subarray(cursor);
    this.state = 'headers';

    return true;
  }

  _consumeHeaders() {
    const headerEnd = this.buffer.indexOf('\r\n\r\n');

    if (headerEnd === -1) {
      if (this.buffer.length > 8192) {
        this.emit('error', new Error('A part\'s headers exceeded 8KB without terminating — this no longer looks like the documented event stream.'));
      }

      return false;
    }

    this.pendingHeaders = parseHeaderBlock(this.buffer.subarray(0, headerEnd).toString('utf8'));
    this.buffer = this.buffer.subarray(headerEnd + 4);
    this.state = 'body';

    return true;
  }

  /**
   * Handles both the length-known and length-unknown cases. The
   * length-known case deliberately does *not* also skip the trailing line
   * ending here: once the body bytes are sliced off and the part is
   * emitted, that step must never run again even if this method is
   * re-entered (on the next `write()`, waiting for just those last 1-2
   * bytes) — re-reading `pendingHeaders` or re-slicing the body the
   * second time would either crash or silently corrupt the next part. So
   * that remaining bit of work moves to its own state, `body-trailer`,
   * entered only once, after the emit has already fully happened.
   */
  _consumeBody() {
    const declaredLength = this.pendingHeaders.get('content-length');

    if (declaredLength === undefined) {
      const index = this.buffer.indexOf(this.delimiter);
      if (index === -1) {
        return false;
      }

      this._emitPart(trimTrailingLineEnding(this.buffer.subarray(0, index)));
      this.buffer = this.buffer.subarray(index);
      this.state = 'seek-boundary';

      return true;
    }

    const length = Number(declaredLength);

    if (!Number.isFinite(length) || length < 0) {
      this.emit('error', new Error(`A part declared a non-numeric Content-Length: "${declaredLength}".`));
      this.pendingHeaders = null;
      this.state = 'seek-boundary';

      return true;
    }

    if (this.buffer.length < length) {
      return false;
    }

    this._emitPart(this.buffer.subarray(0, length));
    this.buffer = this.buffer.subarray(length);
    this.state = 'body-trailer';

    return true;
  }

  _consumeBodyTrailer() {
    const afterBody = this._skipLineEnding(0);
    if (afterBody === -1) {
      return false;
    }

    this.buffer = this.buffer.subarray(afterBody);
    this.state = 'seek-boundary';

    return true;
  }

  _emitPart(body) {
    this.emit('part', { headers: this.pendingHeaders, body: Buffer.from(body) });
    this.pendingHeaders = null;
  }

  /** @returns {number} the index past the line ending, or -1 if more data is needed */
  _skipLineEnding(from) {
    if (this.buffer.length <= from) {
      return -1;
    }

    if (this.buffer[from] === 0x0d) {
      if (this.buffer.length < from + 2) {
        return -1;
      }

      return from + 2;
    }

    if (this.buffer[from] === 0x0a) {
      return from + 1;
    }

    // No line ending where one was expected — proceed without consuming
    // anything rather than get stuck, since a device that omits it here
    // still puts real content right after.
    return from;
  }
}

function parseHeaderBlock(block) {
  const headers = new Map();

  for (const line of block.split('\r\n')) {
    const separatorIndex = line.indexOf(':');
    if (separatorIndex === -1) {
      continue;
    }

    headers.set(line.slice(0, separatorIndex).trim().toLowerCase(), line.slice(separatorIndex + 1).trim());
  }

  return headers;
}

function trimTrailingLineEnding(buffer) {
  let end = buffer.length;

  if (end >= 2 && buffer[end - 2] === 0x0d && buffer[end - 1] === 0x0a) {
    end -= 2;
  } else if (end >= 1 && buffer[end - 1] === 0x0a) {
    end -= 1;
  }

  return buffer.subarray(0, end);
}

/**
 * Pulls the boundary token out of a `Content-Type: multipart/x-mixed-replace;
 * boundary=...` response header. The device chooses this string per
 * connection — it is never hardcoded here.
 */
export function extractBoundary(contentTypeHeader) {
  const match = /boundary=("?)([^;"]+)\1/i.exec(contentTypeHeader || '');

  if (!match) {
    throw new Error(`No multipart boundary found in Content-Type: "${contentTypeHeader}"`);
  }

  return match[2];
}
