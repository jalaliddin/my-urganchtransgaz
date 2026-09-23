import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

import { buildAuthorizationHeader, parseWwwAuthenticate } from '../src/digestAuth.js';

function md5(value) {
  return createHash('md5').update(value).digest('hex');
}

test('parseWwwAuthenticate reads quoted and unquoted Digest parameters', () => {
  const header = 'Digest realm="Login to 192.168.1.108", qop="auth", nonce="abc123", opaque="xyz"';
  const params = parseWwwAuthenticate(header);

  assert.equal(params.realm, 'Login to 192.168.1.108');
  assert.equal(params.qop, 'auth');
  assert.equal(params.nonce, 'abc123');
  assert.equal(params.opaque, 'xyz');
});

test('parseWwwAuthenticate rejects a non-Digest challenge', () => {
  assert.throws(() => parseWwwAuthenticate('Basic realm="x"'));
});

test('buildAuthorizationHeader computes the RFC 7616 qop=auth response correctly', () => {
  const challenge = { realm: 'Login to device', nonce: 'n0nce', qop: 'auth', opaque: 'op' };
  const header = buildAuthorizationHeader({
    username: 'admin',
    password: 'secret',
    method: 'GET',
    uri: '/cgi-bin/snapManager.cgi?action=attachFileProc',
    challenge,
    nonceCount: 1,
  });

  const cnonceMatch = /cnonce="([^"]+)"/.exec(header);
  assert.ok(cnonceMatch, 'expected a cnonce in the header');
  const cnonce = cnonceMatch[1];

  const ha1 = md5('admin:Login to device:secret');
  const ha2 = md5('GET:/cgi-bin/snapManager.cgi?action=attachFileProc');
  const expectedResponse = md5(`${ha1}:n0nce:00000001:${cnonce}:auth:${ha2}`);

  assert.match(header, /^Digest /);
  assert.match(header, new RegExp(`response="${expectedResponse}"`));
  assert.match(header, /nc=00000001/);
  assert.match(header, /opaque="op"/);
});

test('buildAuthorizationHeader falls back to the no-qop form when the device does not offer one', () => {
  const challenge = { realm: 'r', nonce: 'n' };
  const header = buildAuthorizationHeader({
    username: 'admin',
    password: 'secret',
    method: 'GET',
    uri: '/x',
    challenge,
  });

  const ha1 = md5('admin:r:secret');
  const ha2 = md5('GET:/x');
  const expectedResponse = md5(`${ha1}:n:${ha2}`);

  assert.match(header, new RegExp(`response="${expectedResponse}"`));
  assert.doesNotMatch(header, /qop=/);
});
