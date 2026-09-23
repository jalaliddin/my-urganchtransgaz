import http from 'node:http';

import { buildAuthorizationHeader, parseWwwAuthenticate } from './digestAuth.js';
import { extractBoundary, MultipartStreamParser } from './multipartStreamParser.js';

/**
 * Builds the event-subscription path exactly as the vendor's own
 * documentation examples show it — including the literal, unescaped `[`
 * and `]` in `Flags[0]=Event&Events=[All]` — rather than through
 * `URLSearchParams`, which would percent-encode them into a query string
 * some Dahua firmware does not parse correctly.
 */
function buildEventPath(config) {
  const params = [`action=attachFileProc`, `Flags[0]=Event`, `Events=[All]`, `heartbeat=${config.device.heartbeatSeconds}`];

  if (config.device.channel !== undefined) {
    params.push(`channel=${config.device.channel}`);
  }

  return `/cgi-bin/snapManager.cgi?${params.join('&')}`;
}

/**
 * Opens one subscription connection to the terminal (handling the
 * Digest-auth challenge/response round trip Dahua terminals require) and
 * streams its multipart events to `onPart` as they arrive.
 *
 * Returns a handle with `.close()`. Exactly one of `onEnded` (connection
 * closed or failed, for any reason — auth, network, malformed stream)
 * or the connection running until `.close()` is called will happen;
 * reconnecting is index.js's job, not this module's, so that the retry
 * policy lives in one place.
 */
export function connectToDevice(config, { onPart, onEnded, onAuthenticated }) {
  const path = buildEventPath(config);
  let closed = false;
  let activeRequest = null;
  let watchdog = null;

  const finish = (reason) => {
    if (closed) return;
    closed = true;
    clearTimeout(watchdog);
    activeRequest?.destroy();
    onEnded(reason);
  };

  const armWatchdog = () => {
    clearTimeout(watchdog);
    // No bytes at all — not even a heartbeat — for three missed intervals
    // means the connection is dead even though the OS may not have
    // noticed yet (a router silently dropping a long-lived idle TCP
    // stream is the common real-world cause).
    const timeoutMs = Math.max(config.device.heartbeatSeconds * 3, 30) * 1000;
    watchdog = setTimeout(() => finish(new Error('No data received from the device within the expected heartbeat window.')), timeoutMs);
  };

  const request = (headers) => {
    activeRequest = http.request(
      {
        host: config.device.host,
        port: config.device.port,
        path,
        method: 'GET',
        headers,
      },
      (response) => handleResponse(headers, response),
    );

    activeRequest.on('error', (error) => finish(error));
    activeRequest.end();
  };

  const handleResponse = (previousHeaders, response) => {
    if (response.statusCode === 401) {
      if (previousHeaders.Authorization) {
        response.resume();
        finish(new Error('The device rejected the credentials (401 after already sending Authorization).'));

        return;
      }

      const challengeHeader = response.headers['www-authenticate'];
      response.resume();

      try {
        request(buildAuthorizationForChallenge(config, path, challengeHeader));
      } catch (error) {
        finish(error);
      }

      return;
    }

    if (response.statusCode !== 200) {
      response.resume();
      finish(new Error(`Unexpected HTTP status ${response.statusCode} from the device.`));

      return;
    }

    let boundary;

    try {
      boundary = extractBoundary(response.headers['content-type']);
    } catch (error) {
      response.resume();
      finish(error);

      return;
    }

    onAuthenticated?.();

    const parser = new MultipartStreamParser(boundary);
    parser.on('part', onPart);
    parser.on('error', (error) => finish(error));
    parser.on('end', () => finish(new Error('The device closed the event subscription.')));

    armWatchdog();
    response.on('data', (chunk) => {
      armWatchdog();
      parser.write(chunk);
    });
    response.on('end', () => finish(new Error('The connection to the device ended.')));
    response.on('error', (error) => finish(error));
  };

  request({});

  return {
    close: () => finish(null),
  };
}

function buildAuthorizationForChallenge(config, path, challengeHeader) {
  if (!challengeHeader) {
    throw new Error('The device replied 401 with no WWW-Authenticate header.');
  }

  if (challengeHeader.toLowerCase().startsWith('basic')) {
    const token = Buffer.from(`${config.device.username}:${config.device.password}`).toString('base64');

    return { Authorization: `Basic ${token}` };
  }

  const challenge = parseWwwAuthenticate(challengeHeader);
  const authorization = buildAuthorizationHeader({
    username: config.device.username,
    password: config.device.password,
    method: 'GET',
    uri: path,
    challenge,
  });

  return { Authorization: authorization };
}
