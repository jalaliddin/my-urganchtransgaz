import { existsSync, readFileSync } from 'node:fs';

/**
 * A minimal `.env` reader — no `dotenv` dependency, so this app installs
 * and runs with nothing beyond Node itself on a locked-down on-site box.
 * Supports `KEY=value` lines, `#` comments, and blank lines; nothing
 * fancier (no multiline values, no `export` prefix) because the config
 * here never needs it. Existing `process.env` values always win, so a
 * value set by the shell or a systemd `Environment=` line overrides the
 * file.
 */
export function loadEnv(path = '.env') {
  if (!existsSync(path)) {
    return;
  }

  for (const rawLine of readFileSync(path, 'utf8').split(/\r?\n/)) {
    const line = rawLine.trim();

    if (!line || line.startsWith('#')) {
      continue;
    }

    const separatorIndex = line.indexOf('=');
    if (separatorIndex === -1) {
      continue;
    }

    const key = line.slice(0, separatorIndex).trim();
    let value = line.slice(separatorIndex + 1).trim();

    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }

    if (!(key in process.env)) {
      process.env[key] = value;
    }
  }
}
