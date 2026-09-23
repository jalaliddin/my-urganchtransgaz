import { createHash, randomBytes } from 'node:crypto';

function md5(value) {
  return createHash('md5').update(value).digest('hex');
}

/**
 * Parses a `WWW-Authenticate: Digest ...` header into its named
 * parameters (realm, nonce, qop, opaque, ...). Values may or may not be
 * quoted — Dahua's own firmware is not fully consistent about it across
 * models/versions, so both are accepted.
 */
export function parseWwwAuthenticate(header) {
  if (!header || !header.toLowerCase().startsWith('digest ')) {
    throw new Error(`Expected a Digest challenge, got: ${header}`);
  }

  const params = {};
  const body = header.slice('Digest '.length);
  const pattern = /(\w+)=(?:"([^"]*)"|([^,\s]+))/g;
  let match;

  while ((match = pattern.exec(body))) {
    params[match[1]] = match[2] !== undefined ? match[2] : match[3];
  }

  return params;
}

/**
 * Builds the `Authorization: Digest ...` header for one request, per
 * RFC 7616. Only MD5 and `qop=auth` are implemented — the only scheme
 * Dahua terminals are known to challenge with; a device that asked for
 * something else would need this extended, not worked around.
 */
export function buildAuthorizationHeader({ username, password, method, uri, challenge, nonceCount = 1 }) {
  const { realm, nonce, opaque, qop: qopOffered } = challenge;
  const qop = qopOffered?.split(',').map((s) => s.trim()).includes('auth') ? 'auth' : undefined;
  const cnonce = randomBytes(8).toString('hex');
  const nc = nonceCount.toString(16).padStart(8, '0');

  const ha1 = md5(`${username}:${realm}:${password}`);
  const ha2 = md5(`${method}:${uri}`);
  const response = qop
    ? md5(`${ha1}:${nonce}:${nc}:${cnonce}:${qop}:${ha2}`)
    : md5(`${ha1}:${nonce}:${ha2}`);

  const parts = [
    `username="${username}"`,
    `realm="${realm}"`,
    `nonce="${nonce}"`,
    `uri="${uri}"`,
    `response="${response}"`,
  ];

  if (qop) {
    parts.push(`qop=${qop}`, `nc=${nc}`, `cnonce="${cnonce}"`);
  }

  if (opaque) {
    parts.push(`opaque="${opaque}"`);
  }

  return `Digest ${parts.join(', ')}`;
}
