import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

import { md5 } from '../src/lib/md5.js';

test('matches the RFC 1321 test vectors', () => {
  assert.equal(md5(''), 'd41d8cd98f00b204e9800998ecf8427e');
  assert.equal(md5('abc'), '900150983cd24fb0d6963f7d28e17f72');
  assert.equal(md5('message digest'), 'f96b697d7cb7938d525a2f31aaf161d0');
  assert.equal(
    md5('12345678901234567890123456789012345678901234567890123456789012345678901234567890'),
    '57edf4a22be3c955ac49da2e2107b67a',
  );
});

test('matches node:crypto across block boundaries and for non-ASCII text', () => {
  for (const text of ['a'.repeat(55), 'a'.repeat(56), 'a'.repeat(64), 'a'.repeat(200), "admin:Login to 4K:Parol'ō‘ 2026"]) {
    assert.equal(md5(text), createHash('md5').update(text, 'utf8').digest('hex'), `length ${text.length}`);
  }
});
