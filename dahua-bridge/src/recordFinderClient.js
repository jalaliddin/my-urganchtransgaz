import http from 'node:http';

import { buildAuthorizationHeader, parseWwwAuthenticate } from './digestAuth.js';

/**
 * Pulls offline/historical access-control records straight from the
 * terminal (`recordFinder.cgi?action=find`) — the vendor's documented way
 * to read what already happened, as opposed to the live event
 * subscription (dahuaClient.js), which only ever reports what happens
 * from the moment it connects onward. This is what makes a real backfill
 * possible: anything that happened while this bridge was down, or before
 * it was ever installed, is still on the terminal and retrievable here.
 *
 * A single bounded GET request with Digest auth, not a stream — unlike
 * the event subscription, this response is a fixed, complete body.
 */
export async function fetchAccessRecords(config, { startTime, endTime, count } = {}) {
  const path = buildPath(config, { startTime, endTime, count });
  const body = await digestGet(config, path);

  return parseRecordFinderResponse(body);
}

function buildPath(config, { startTime, endTime, count }) {
  const params = [
    'action=find',
    `name=${encodeURIComponent(config.recordFinder.recordName)}`,
  ];

  if (startTime !== undefined) params.push(`StartTime=${Math.floor(startTime)}`);
  if (endTime !== undefined) params.push(`EndTime=${Math.floor(endTime)}`);
  params.push(`count=${count ?? config.recordFinder.count}`);

  return `/cgi-bin/recordFinder.cgi?${params.join('&')}`;
}

/**
 * One Digest-authenticated GET, collected to a single string — the
 * same challenge/response handshake dahuaClient.js uses for the
 * streaming connection, but for a bounded request with no watchdog,
 * reconnect, or multipart parsing of its own.
 */
function digestGet(config, path) {
  return new Promise((resolve, reject) => {
    const request = (headers) => {
      const req = http.request(
        { host: config.device.host, port: config.device.port, path, method: 'GET', headers },
        (response) => handleResponse(headers, response),
      );

      req.on('error', reject);
      req.end();
    };

    const handleResponse = (previousHeaders, response) => {
      if (response.statusCode === 401) {
        if (previousHeaders.Authorization) {
          response.resume();
          reject(new Error('The device rejected the credentials (401 after already sending Authorization).'));

          return;
        }

        const challengeHeader = response.headers['www-authenticate'];
        response.resume();

        try {
          const challenge = parseWwwAuthenticate(challengeHeader);
          const authorization = buildAuthorizationHeader({
            username: config.device.username,
            password: config.device.password,
            method: 'GET',
            uri: path,
            challenge,
          });
          request({ Authorization: authorization });
        } catch (error) {
          reject(error);
        }

        return;
      }

      if (response.statusCode !== 200) {
        response.resume();
        reject(new Error(`Unexpected HTTP status ${response.statusCode} from recordFinder.cgi.`));

        return;
      }

      const chunks = [];
      response.on('data', (chunk) => chunks.push(chunk));
      response.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
      response.on('error', reject);
    };

    request({});
  });
}

/**
 * The response is the same flat `key=value`-per-line shape as the live
 * event stream's text parts, but with its own array name (`records`,
 * lowercase on the terminal this was verified against — the vendor's
 * documentation excerpt shows it capitalized, so this matches
 * case-insensitively rather than assuming one casing) and a couple of
 * top-level scalars (`found`, and `totalCount` when the device sends
 * one — it did not on the terminal this was verified against).
 */
export function parseRecordFinderResponse(text) {
  const records = new Map();
  const scalars = {};
  const arrayLinePattern = /^records\[(\d+)]\.(.+)$/i;

  for (const rawLine of text.trim().split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line) continue;

    const separatorIndex = line.indexOf('=');
    if (separatorIndex === -1) continue;

    const key = line.slice(0, separatorIndex).trim();
    const value = line.slice(separatorIndex + 1).trim();
    const match = arrayLinePattern.exec(key);

    if (!match) {
      scalars[key] = value;
      continue;
    }

    const [, indexText, field] = match;
    const index = Number(indexText);

    if (!records.has(index)) records.set(index, {});
    records.get(index)[field] = value;
  }

  return {
    found: scalars.found !== undefined ? Number(scalars.found) : undefined,
    totalCount: scalars.totalCount !== undefined ? Number(scalars.totalCount) : undefined,
    records: [...records.entries()].sort(([a], [b]) => a - b).map(([, fields]) => fields),
  };
}
