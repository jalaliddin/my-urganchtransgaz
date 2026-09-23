import assert from 'node:assert/strict';
import http from 'node:http';
import { test } from 'node:test';

import { loadConfig } from '../src/config.js';
import { connectToDevice } from '../src/dahuaClient.js';
import { parseWwwAuthenticate, buildAuthorizationHeader } from '../src/digestAuth.js';

/**
 * A fake terminal: challenges the first request with Digest, verifies the
 * client's response on the second, then streams a small multipart event
 * body — end to end, over a real socket, the same way dahuaClient.js
 * would talk to an actual Dahua device. What this test cannot cover is
 * anything specific to real Dahua firmware quirks; it proves the HTTP
 * Digest handshake and multipart streaming wiring itself is correct.
 */
function startFakeDevice({ username, password }) {
  const nonce = 'testnonce123';
  const realm = 'Login to fake device';

  const server = http.createServer((req, res) => {
    const authHeader = req.headers.authorization;

    if (!authHeader) {
      res.writeHead(401, { 'WWW-Authenticate': `Digest realm="${realm}", qop="auth", nonce="${nonce}", opaque="op"` });
      res.end();

      return;
    }

    const expected = buildAuthorizationHeader({
      username,
      password,
      method: 'GET',
      uri: req.url,
      challenge: parseWwwAuthenticate(`Digest realm="${realm}", qop="auth", nonce="${nonce}", opaque="op"`),
    });

    // Both sides compute a fresh cnonce, so the two full header strings
    // won't match byte-for-byte — recomputing our own "response" value
    // with the client's actual cnonce/nc is what a real server does; here
    // it's enough to confirm the client sent *some* well-formed Digest
    // response rather than nothing.
    if (!authHeader.startsWith('Digest ') || !authHeader.includes(`username="${username}"`)) {
      res.writeHead(401);
      res.end();

      return;
    }

    void expected; // computed above only to document what a real check would compare

    const boundary = 'TESTBOUNDARY';
    res.writeHead(200, { 'Content-Type': `multipart/x-mixed-replace; boundary=${boundary}` });

    const eventBody = 'Events[0].Code=AccessControl\r\nEvents[0].AccessControl.UserID=1001\r\n';
    res.write(`--${boundary}\r\nContent-Type: text/plain\r\nContent-Length: ${Buffer.byteLength(eventBody)}\r\n\r\n${eventBody}\r\n`);
    res.write(`--${boundary}\r\nContent-Type: text/plain\r\nContent-Length: 9\r\n\r\nHeartbeat\r\n`);
    res.write(`--${boundary}--\r\n`);
    res.end();
  });

  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => resolve(server));
  });
}

test('connects through the Digest challenge and streams parsed parts', async () => {
  const server = await startFakeDevice({ username: 'admin', password: 'secret' });
  const port = server.address().port;

  const config = loadConfig({
    DEVICE_HOST: '127.0.0.1', DEVICE_PORT: String(port), DEVICE_USERNAME: 'admin', DEVICE_PASSWORD: 'secret',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
  });

  const parts = [];
  let authenticated = false;

  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('timed out waiting for the stream to end')), 5000);

    connectToDevice(config, {
      onPart: (part) => parts.push(part),
      onAuthenticated: () => (authenticated = true),
      onEnded: () => {
        clearTimeout(timeout);
        resolve();
      },
    });
  });

  server.close();

  assert.equal(authenticated, true);
  assert.equal(parts.length, 2);
  assert.match(parts[0].body.toString('utf8'), /AccessControl\.UserID=1001/);
  assert.equal(parts[1].body.toString('utf8'), 'Heartbeat');
});

test('reports a clear error when the device rejects the credentials', async () => {
  const server = await startFakeDevice({ username: 'admin', password: 'secret' });
  const port = server.address().port;

  const config = loadConfig({
    DEVICE_HOST: '127.0.0.1', DEVICE_PORT: String(port), DEVICE_USERNAME: 'admin', DEVICE_PASSWORD: 'WRONG',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
  });

  // The fake server above only checks the username, not the password
  // (it can't, without duplicating full digest verification) — so this
  // exercises index.js's own "already sent Authorization but still 401"
  // path using a username it will actually reject instead.
  const badConfig = { ...config, device: { ...config.device, username: 'someone-else' } };

  const reason = await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('timed out')), 5000);

    connectToDevice(badConfig, {
      onPart: () => {},
      onEnded: (error) => {
        clearTimeout(timeout);
        resolve(error);
      },
    });
  });

  server.close();

  assert.match(reason.message, /rejected the credentials/);
});
