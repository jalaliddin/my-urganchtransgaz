const LEVELS = ['debug', 'info', 'warn', 'error'];

/**
 * Plain, timestamped, single-line-per-record logging to stdout/stderr —
 * this runs under systemd (or a similar supervisor) on-site, and
 * `journalctl` already timestamps and stores lines; nothing here needs a
 * log file, rotation or a formatting library of its own.
 */
export function createLogger(level = 'info') {
  const threshold = LEVELS.includes(level) ? LEVELS.indexOf(level) : LEVELS.indexOf('info');

  const log = (levelName, message, meta) => {
    if (LEVELS.indexOf(levelName) < threshold) {
      return;
    }

    const line = `${new Date().toISOString()} [${levelName.toUpperCase()}] ${message}`;
    const stream = levelName === 'error' || levelName === 'warn' ? console.error : console.log;

    stream(meta === undefined ? line : `${line} ${JSON.stringify(meta)}`);
  };

  return {
    debug: (message, meta) => log('debug', message, meta),
    info: (message, meta) => log('info', message, meta),
    warn: (message, meta) => log('warn', message, meta),
    error: (message, meta) => log('error', message, meta),
  };
}
