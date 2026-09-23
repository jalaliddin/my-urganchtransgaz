import assert from 'node:assert/strict';
import http from 'node:http';
import { test } from 'node:test';

import { loadConfig } from '../src/config.js';
import { fetchAccessRecords, parseRecordFinderResponse } from '../src/recordFinderClient.js';

test('parses a real captured recordFinder.cgi response (lowercase records[n])', () => {
  // Captured verbatim from a live terminal's response to
  // action=find&name=AccessControlCardRec.
  const body = [
    'found=2',
    'records[0].AttendanceState=0',
    'records[0].CardName=Kenjayev N',
    'records[0].CardNo=',
    'records[0].CreateTime=1789554842',
    'records[0].Door=0',
    'records[0].ErrorCode=0',
    'records[0].Method=15',
    'records[0].ReaderID=1',
    'records[0].RecNo=550478',
    'records[0].Status=1',
    'records[0].Type=Entry',
    'records[0].UserID=108',
    'records[1].AttendanceState=0',
    'records[1].CardName=Matyoqubov O',
    'records[1].CreateTime=1789554945',
    'records[1].ErrorCode=0',
    'records[1].RecNo=550479',
    'records[1].Status=1',
    'records[1].Type=Entry',
    'records[1].UserID=28',
    '',
  ].join('\r\n');

  const { found, totalCount, records } = parseRecordFinderResponse(body);

  assert.equal(found, 2);
  assert.equal(totalCount, undefined, 'this terminal never sends one');
  assert.equal(records.length, 2);
  assert.deepEqual(records[0], {
    AttendanceState: '0', CardName: 'Kenjayev N', CardNo: '', CreateTime: '1789554842', Door: '0',
    ErrorCode: '0', Method: '15', ReaderID: '1', RecNo: '550478', Status: '1', Type: 'Entry', UserID: '108',
  });
  assert.equal(records[1].UserID, '28');
});

test('is case-insensitive about the array name, per the vendor documentation\'s own capitalization', () => {
  const { records } = parseRecordFinderResponse('Records[0].UserID=5\nRecords[0].Status=1');
  assert.equal(records[0].UserID, '5');
});

test('reports totalCount when the response actually sends one', () => {
  const { found, totalCount } = parseRecordFinderResponse('found=5\ntotalCount=1000\nrecords[0].UserID=1');
  assert.equal(found, 5);
  assert.equal(totalCount, 1000);
});

test('an empty result has no records', () => {
  const { found, records } = parseRecordFinderResponse('found=0\n');
  assert.equal(found, 0);
  assert.deepEqual(records, []);
});

function baseConfig(overrides = {}) {
  return loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
    ...overrides,
  });
}

test('fetchAccessRecords completes the Digest handshake against a real HTTP server and parses the result', async () => {
  const nonce = 'testnonce';
  const realm = 'Login to fake device';

  const server = http.createServer((req, res) => {
    if (!req.headers.authorization) {
      res.writeHead(401, { 'WWW-Authenticate': `Digest realm="${realm}", qop="auth", nonce="${nonce}", opaque="op"` });
      res.end();

      return;
    }

    assert.match(req.url, /action=find/);
    assert.match(req.url, /name=AccessControlCardRec/);
    assert.match(req.url, /StartTime=100/);
    assert.match(req.url, /EndTime=200/);

    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('found=1\nrecords[0].UserID=42\nrecords[0].Status=1\nrecords[0].CreateTime=150\n');
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;

  const config = baseConfig({ DEVICE_HOST: '127.0.0.1', DEVICE_PORT: String(port) });
  const result = await fetchAccessRecords(config, { startTime: 100, endTime: 200 });

  server.close();

  assert.equal(result.found, 1);
  assert.equal(result.records[0].UserID, '42');
});

test('fetchAccessRecords rejects when the device refuses the credentials', async () => {
  const server = http.createServer((req, res) => {
    res.writeHead(401, { 'WWW-Authenticate': 'Digest realm="r", qop="auth", nonce="n", opaque="o"' });
    res.end();
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  const config = baseConfig({ DEVICE_HOST: '127.0.0.1', DEVICE_PORT: String(port) });

  await assert.rejects(() => fetchAccessRecords(config), /rejected the credentials/);
  server.close();
});
