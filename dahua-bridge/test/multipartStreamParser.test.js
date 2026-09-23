import assert from 'node:assert/strict';
import { test } from 'node:test';

import { extractBoundary, MultipartStreamParser } from '../src/multipartStreamParser.js';

test('extractBoundary reads the boundary out of a Content-Type header', () => {
  assert.equal(extractBoundary('multipart/x-mixed-replace; boundary=myboundary'), 'myboundary');
  assert.equal(extractBoundary('multipart/x-mixed-replace;boundary="quoted-one"'), 'quoted-one');
});

test('extractBoundary throws when there is no boundary', () => {
  assert.throws(() => extractBoundary('text/plain'));
});

function buildPart(boundary, headers, body) {
  const headerLines = Object.entries(headers).map(([key, value]) => `${key}: ${value}\r\n`).join('');

  return Buffer.concat([
    Buffer.from(`--${boundary}\r\n${headerLines}\r\n`),
    Buffer.isBuffer(body) ? body : Buffer.from(body),
    Buffer.from('\r\n'),
  ]);
}

test('parses a single text part using its declared Content-Length', () => {
  const body = 'Events[0].Code=AccessControl\r\nEvents[0].AccessControl.UserID=1001\r\n';
  const stream = Buffer.concat([
    buildPart('B', { 'Content-Type': 'text/plain', 'Content-Length': Buffer.byteLength(body) }, body),
    Buffer.from('--B--\r\n'),
  ]);

  const parser = new MultipartStreamParser('B');
  const parts = [];
  let ended = false;
  parser.on('part', (part) => parts.push(part));
  parser.on('end', () => (ended = true));

  parser.write(stream);

  assert.equal(parts.length, 1);
  assert.equal(parts[0].headers.get('content-type'), 'text/plain');
  assert.equal(parts[0].body.toString('utf8'), body);
  assert.equal(ended, true);
});

test('parses several parts, including a binary JPEG body containing boundary-like bytes', () => {
  // A body engineered to contain the exact delimiter bytes inside it —
  // only a correct Content-Length-driven read (not a boundary scan)
  // gets this right.
  const jpeg = Buffer.concat([Buffer.from([0xff, 0xd8]), Buffer.from('--B'), Buffer.from([0xff, 0xd9])]);
  const heartbeat = 'Heartbeat';

  const stream = Buffer.concat([
    buildPart('B', { 'Content-Type': 'image/jpeg', 'Content-Length': jpeg.length }, jpeg),
    buildPart('B', { 'Content-Type': 'text/plain', 'Content-Length': Buffer.byteLength(heartbeat) }, heartbeat),
    Buffer.from('--B--\r\n'),
  ]);

  const parser = new MultipartStreamParser('B');
  const parts = [];
  parser.on('part', (part) => parts.push(part));

  parser.write(stream);

  assert.equal(parts.length, 2);
  assert.equal(parts[0].headers.get('content-type'), 'image/jpeg');
  assert.ok(parts[0].body.equals(jpeg));
  assert.equal(parts[1].body.toString('utf8'), heartbeat);
});

test('reassembles a part split across many small chunks, including mid-boundary and mid-header splits', () => {
  const body = 'Events[0].Code=AccessControl\r\n';
  const full = Buffer.concat([
    buildPart('B', { 'Content-Type': 'text/plain', 'Content-Length': Buffer.byteLength(body) }, body),
    Buffer.from('--B--\r\n'),
  ]);

  const parser = new MultipartStreamParser('B');
  const parts = [];
  parser.on('part', (part) => parts.push(part));

  for (let i = 0; i < full.length; i++) {
    parser.write(full.subarray(i, i + 1));
  }

  assert.equal(parts.length, 1);
  assert.equal(parts[0].body.toString('utf8'), body);
});

test('falls back to scanning for the boundary when Content-Length is absent', () => {
  const stream = Buffer.from('--B\r\nContent-Type: text/plain\r\n\r\nHeartbeat\r\n--B--\r\n');

  const parser = new MultipartStreamParser('B');
  const parts = [];
  parser.on('part', (part) => parts.push(part));

  parser.write(stream);

  assert.equal(parts.length, 1);
  assert.equal(parts[0].body.toString('utf8'), 'Heartbeat');
});

test('emits an error instead of hanging forever on an unparseable header block', () => {
  const parser = new MultipartStreamParser('B');
  let error = null;
  parser.on('error', (err) => (error = err));

  parser.write(Buffer.concat([Buffer.from('--B\r\n'), Buffer.alloc(9000, 'x')]));

  assert.ok(error);
});
