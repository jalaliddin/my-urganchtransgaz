/**
 * A one-off historical pull, independent of the running bridge — for
 * "get everything the terminal already has" (set BACKFILL_SINCE to
 * however far back is wanted) without waiting for the continuous
 * process's own periodic reconciliation, and without needing the live
 * event subscription to even be reachable.
 *
 *   BACKFILL_SINCE=2026-01-01 npm run backfill
 */
import { runBackfill } from './backfill.js';
import { loadConfig } from './config.js';
import { DirectionResolver } from './directionResolver.js';
import { createLogger } from './logger.js';
import { OfflineQueue } from './offlineQueue.js';
import { flushQueue } from './queueFlusher.js';

async function main() {
  const config = loadConfig();
  const logger = createLogger(config.logLevel);
  const queue = new OfflineQueue(config.dataDir);
  const direction = new DirectionResolver(config);
  const flush = () => flushQueue(config, queue, logger);

  logger.info('Running a one-off backfill.', { device: `${config.device.host}:${config.device.port}` });

  const result = await runBackfill(config, { logger, queue, direction, flush });

  logger.info(`Done: ${result.processed} historical scan(s) queued.`);
}

main().catch((error) => {
  // eslint-disable-next-line no-console
  console.error(`Backfill failed: ${error.message}`);
  process.exitCode = 1;
});
