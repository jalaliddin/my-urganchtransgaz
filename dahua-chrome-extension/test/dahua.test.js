import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import http from 'node:http';
import { test } from 'node:test';

import {
  buildDigestAuthorization,
  DeviceError,
  findAccessRecords,
  parseDigestChallenge,
  parseRecordFinderResponse,
} from '../src/lib/dahua.js';

const md5 = (value) => createHash('md5').update(value).digest('hex');

// Verbatim from this site's entry terminal (see dahua-bridge's tests).
const REAL_RESPONSE = [
  'found=2',
  'records[0].AttendanceState=0',
  'records[0].CardName=Kenjayev N',
  'records[0].CardNo=',
  'records[0].CreateTime=1789554842',
  'records[0].ErrorCode=0',
  'records[0].RecNo=550478',
  'records[0].Status=1',
  'records[0].Type=Entry',
  'records[0].UserID=108',
  'records[1].CardName=Matyoqubov O',
  'records[1].CreateTime=1789554945',
  'records[1].ErrorCode=0',
  'records[1].RecNo=550479',
  'records[1].Status=1',
  'records[1].Type=Entry',
  'records[1].UserID=28',
  '',
].join('\r\n');

test('parses a real recordFinder.cgi response', () => {
  const { found, totalCount, records } = parseRecordFinderResponse(REAL_RESPONSE);

  assert.equal(found, 2);
  assert.equal(totalCount, undefined);
  assert.equal(records[0].UserID, '108');
  assert.equal(records[0].CardName, 'Kenjayev N');
  assert.equal(records[1].RecNo, '550479');
});

test('reads quoted and bare Digest challenge parameters', () => {
  assert.deepEqual(parseDigestChallenge('Digest realm="Login to 4K", qop="auth", nonce="abc", opaque=xyz'), {
    realm: 'Login to 4K',
    qop: 'auth',
    nonce: 'abc',
    opaque: 'xyz',
  });
  assert.throws(() => parseDigestChallenge('Basic realm="x"'));
});

test('builds the RFC 7616 response for qop=auth', () => {
  const header = buildDigestAuthorization({
    username: 'admin',
    password: 'secret',
    method: 'GET',
    uri: '/cgi-bin/recordFinder.cgi?action=find',
    challenge: { realm: 'Login to 4K', nonce: 'n1', qop: 'auth', opaque: 'o1' },
    cnonce: 'c1',
  });
  const ha1 = md5('admin:Login to 4K:secret');
  const ha2 = md5('GET:/cgi-bin/recordFinder.cgi?action=find');

  assert.match(header, new RegExp(`response="${md5(`${ha1}:n1:00000001:c1:auth:${ha2}`)}"`));
  assert.match(header, /qop=auth, nc=00000001, cnonce="c1"/);
  assert.match(header, /opaque="o1"/);
});

/** A local stand-in for the terminal that really checks Digest credentials. */
function startFakeTerminal({ username, password }) {
  const realm = 'Login to TEST';
  const nonce = 'fixed-nonce';
  const requests = [];
  const server = http.createServer((req, res) => {
    requests.push(req.url);
    const auth = req.headers.authorization;

    if (!auth) {
      res.writeHead(401, { 'WWW-Authenticate': `Digest realm="${realm}", qop="auth", nonce="${nonce}", opaque="op"` });
      res.end();
      return;
    }

    const params = parseDigestChallenge(auth);
    const ha1 = md5(`${username}:${realm}:${password}`);
    const ha2 = md5(`GET:${params.uri}`);
    const expected = md5(`${ha1}:${nonce}:${params.nc}:${params.cnonce}:${params.qop}:${ha2}`);

    if (params.response !== expected || params.uri !== req.url) {
      res.writeHead(401, { 'WWW-Authenticate': `Digest realm="${realm}", qop="auth", nonce="${nonce}"` });
      res.end();
      return;
    }

    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end(REAL_RESPONSE);
  });

  return new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve({ server, requests, port: server.address().port })));
}

test('logs in with Digest over fetch and reads the records', async () => {
  const { server, requests, port } = await startFakeTerminal({ username: 'admin', password: 'p@ss' });

  try {
    const page = await findAccessRecords(
      { host: '127.0.0.1', port, username: 'admin', password: 'p@ss' },
      { startTime: 1789550000, endTime: 1789560000, count: 500 },
    );

    assert.equal(page.records.length, 2);
    assert.equal(requests.length, 2, 'one challenge, one authenticated request');
    assert.match(requests[1], /^\/cgi-bin\/recordFinder\.cgi\?action=find&name=AccessControlCardRec&StartTime=1789550000&EndTime=1789560000&count=500$/);
  } finally {
    server.close();
  }
});

test('says the credentials are wrong when the terminal refuses them', async () => {
  const { server, port } = await startFakeTerminal({ username: 'admin', password: 'right' });

  try {
    await assert.rejects(
      findAccessRecords({ host: '127.0.0.1', port, username: 'admin', password: 'wrong' }, { count: 1 }),
      (error) => error instanceof DeviceError && error.kind === 'credentials',
    );
  } finally {
    server.close();
  }
});

test('says the terminal is unreachable when nothing answers', async () => {
  const { server, port } = await startFakeTerminal({ username: 'a', password: 'b' });
  server.close();

  await assert.rejects(
    findAccessRecords({ host: '127.0.0.1', port, username: 'a', password: 'b' }, { count: 1 }),
    (error) => error instanceof DeviceError && error.kind === 'unreachable',
  );
});
