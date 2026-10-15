import { md5 } from './md5.js';

/**
 * Talking to a Dahua terminal: HTTP Digest auth (RFC 7616, MD5 + qop=auth —
 * what Dahua challenges with) over plain fetch, and the offline-records
 * query `recordFinder.cgi?action=find&name=AccessControlCardRec`. Ported
 * from dahua-bridge/src (digestAuth.js, recordFinderClient.js), where it
 * was verified live against this site's two terminals.
 */

const REQUEST_TIMEOUT_MS = 15000;

/** `WWW-Authenticate: Digest ...` → its parameters; quoted or bare values. */
export function parseDigestChallenge(header) {
  if (!header || !header.toLowerCase().startsWith('digest ')) {
    throw new Error(`Qurilma Digest so'ramadi: ${header ?? "sarlavha yo'q"}`);
  }

  const params = {};
  const pattern = /(\w+)=(?:"([^"]*)"|([^,\s]+))/g;
  let match;

  while ((match = pattern.exec(header.slice('Digest '.length)))) {
    params[match[1]] = match[2] !== undefined ? match[2] : match[3];
  }

  return params;
}

export function randomCnonce() {
  const bytes = crypto.getRandomValues(new Uint8Array(8));

  return [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

export function buildDigestAuthorization({ username, password, method, uri, challenge, cnonce = randomCnonce(), nonceCount = 1 }) {
  const { realm, nonce, opaque, qop: offered } = challenge;
  const qop = offered?.split(',').map((value) => value.trim()).includes('auth') ? 'auth' : undefined;
  const nc = nonceCount.toString(16).padStart(8, '0');
  const ha1 = md5(`${username}:${realm}:${password}`);
  const ha2 = md5(`${method}:${uri}`);
  const response = qop ? md5(`${ha1}:${nonce}:${nc}:${cnonce}:${qop}:${ha2}`) : md5(`${ha1}:${nonce}:${ha2}`);

  const parts = [`username="${username}"`, `realm="${realm}"`, `nonce="${nonce}"`, `uri="${uri}"`, `response="${response}"`];
  if (qop) parts.push(`qop=${qop}`, `nc=${nc}`, `cnonce="${cnonce}"`);
  if (opaque) parts.push(`opaque="${opaque}"`);

  return `Digest ${parts.join(', ')}`;
}

export class DeviceError extends Error {
  /** @param {'unreachable'|'credentials'|'http'} kind */
  constructor(kind, message) {
    super(message);
    this.kind = kind;
  }
}

function deviceBase(device) {
  const port = Number(device.port) || 80;

  return `http://${device.host}${port === 80 ? '' : `:${port}`}`;
}

/**
 * GET with the Digest handshake: an unauthenticated request for the
 * challenge, then the real one. `credentials: 'omit'` keeps the browser's
 * own HTTP-auth handling (cache, login prompt) out of it.
 */
export async function digestGet(device, path, fetchImpl = fetch) {
  const url = `${deviceBase(device)}${path}`;
  const request = async (headers) => {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    try {
      return await fetchImpl(url, { method: 'GET', headers, credentials: 'omit', cache: 'no-store', signal: controller.signal });
    } catch (error) {
      throw new DeviceError('unreachable', `Qurilmaga ulanib bo'lmadi (${device.host}): ${error.message}`);
    } finally {
      clearTimeout(timeout);
    }
  };

  let response = await request({});

  if (response.status === 401) {
    const challenge = parseDigestChallenge(response.headers.get('www-authenticate'));
    const authorization = buildDigestAuthorization({
      username: device.username,
      password: device.password,
      method: 'GET',
      uri: path,
      challenge,
    });
    response = await request({ Authorization: authorization });

    if (response.status === 401) {
      throw new DeviceError('credentials', "Qurilma login yoki parolni qabul qilmadi.");
    }
  }

  if (!response.ok) {
    throw new DeviceError('http', `Qurilma HTTP ${response.status} qaytardi.`);
  }

  return response.text();
}

/**
 * The response is flat `key=value` lines: `found=`, sometimes `totalCount=`,
 * and `records[n].Field=value` (lowercase `records` on this site's
 * terminals, capitalized in the vendor's document — matched either way).
 */
export function parseRecordFinderResponse(text) {
  const records = new Map();
  const scalars = {};
  const arrayLine = /^records\[(\d+)]\.(.+)$/i;

  for (const rawLine of text.trim().split(/\r?\n/)) {
    const line = rawLine.trim();
    const separator = line.indexOf('=');
    if (separator === -1) continue;

    const key = line.slice(0, separator).trim();
    const value = line.slice(separator + 1).trim();
    const match = arrayLine.exec(key);

    if (!match) {
      scalars[key] = value;
      continue;
    }

    const index = Number(match[1]);
    if (!records.has(index)) records.set(index, {});
    records.get(index)[match[2]] = value;
  }

  return {
    found: scalars.found !== undefined ? Number(scalars.found) : undefined,
    totalCount: scalars.totalCount !== undefined ? Number(scalars.totalCount) : undefined,
    records: [...records.entries()].sort(([a], [b]) => a - b).map(([, fields]) => fields),
  };
}

/** One page of offline records; times are Unix epoch seconds. */
export async function findAccessRecords(device, { startTime, endTime, count }, fetchImpl = fetch) {
  const params = ['action=find', 'name=AccessControlCardRec'];
  if (startTime !== undefined) params.push(`StartTime=${Math.floor(startTime)}`);
  if (endTime !== undefined) params.push(`EndTime=${Math.floor(endTime)}`);
  params.push(`count=${count}`);

  const body = await digestGet(device, `/cgi-bin/recordFinder.cgi?${params.join('&')}`, fetchImpl);

  return parseRecordFinderResponse(body);
}
