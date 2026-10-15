/**
 * Packs the extension into the zip the web app offers for download
 * (frontend/public/downloads/dahua-davomat.zip): manifest.json, src/ and
 * icons/, under a `dahua-davomat/` folder, so unzipping gives the folder
 * Chrome's "Load unpacked" wants.
 *
 * No dependencies, and the same source always gives the same bytes
 * (stored, not compressed; fixed timestamps; sorted paths) — so
 * test/pack.test.js can tell when the published zip is out of date.
 *
 *   npm run pack
 */
import { readdirSync, readFileSync, statSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
export const OUTPUT = join(ROOT, '..', 'frontend', 'public', 'downloads', 'dahua-davomat.zip');
const INCLUDE = ['manifest.json', 'src', 'icons'];
const FOLDER = 'dahua-davomat/';
// 2026-01-01 00:00 in MS-DOS date/time form.
const DOS_DATE = ((2026 - 1980) << 9) | (1 << 5) | 1;
const DOS_TIME = 0;

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c >>> 0;
});

function crc32(bytes) {
  let crc = 0xffffffff;
  for (const byte of bytes) crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}

function listFiles(path) {
  const full = join(ROOT, path);
  if (statSync(full).isFile()) return [path];

  return readdirSync(full).flatMap((name) => listFiles(join(path, name)));
}

export function buildZip() {
  const paths = INCLUDE.flatMap(listFiles).map((path) => path.split(sep).join('/')).sort();
  const locals = [];
  const centrals = [];
  let offset = 0;

  for (const path of paths) {
    const data = readFileSync(join(ROOT, path));
    const name = Buffer.from(FOLDER + path, 'utf8');
    const crc = crc32(data);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4); // version needed
    local.writeUInt16LE(0x0800, 6); // UTF-8 names
    local.writeUInt16LE(0, 8); // stored
    local.writeUInt16LE(DOS_TIME, 10);
    local.writeUInt16LE(DOS_DATE, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(data.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(name.length, 26);
    local.writeUInt16LE(0, 28);
    locals.push(local, name, data);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(0x0314, 4); // made by: Unix, 2.0
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(0, 10);
    central.writeUInt16LE(DOS_TIME, 12);
    central.writeUInt16LE(DOS_DATE, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(data.length, 20);
    central.writeUInt32LE(data.length, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt32LE((0o100644 << 16) >>> 0, 38); // rw-r--r--
    central.writeUInt32LE(offset, 42);
    centrals.push(central, name);

    offset += local.length + name.length + data.length;
  }

  const centralDirectory = Buffer.concat(centrals);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(paths.length, 8);
  end.writeUInt16LE(paths.length, 10);
  end.writeUInt32LE(centralDirectory.length, 12);
  end.writeUInt32LE(offset, 16);

  return { zip: Buffer.concat([...locals, centralDirectory, end]), paths };
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const { zip, paths } = buildZip();
  mkdirSync(dirname(OUTPUT), { recursive: true });
  writeFileSync(OUTPUT, zip);
  console.log(`${relative(process.cwd(), OUTPUT)}: ${paths.length} files, ${(zip.length / 1024).toFixed(0)} KB`);
}
