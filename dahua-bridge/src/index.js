import { connectToDevice } from './dahuaClient.js';
import { loadConfig } from './config.js';
import { DirectionResolver } from './directionResolver.js';
import { ScanDeduper } from './dedupe.js';
import { createEventPipeline } from './eventPipeline.js';
import { createLogger } from './logger.js';
import { OfflineQueue } from './offlineQueue.js';
import { flushQueue } from './queueFlusher.js';

const RECONNECT_BASE_DELAY_MS = 2000;
const RECONNECT_MAX_DELAY_MS = 60000;
// A connection that stayed up at least this long was genuinely working,
// not failing immediately — the reconnect backoff resets after one of
// these instead of staying maxed out from an unrelated earlier outage.
const STABLE_CONNECTION_MS = 20000;
const PERIODIC_FLUSH_MS = 30000;

async function main() {
  const config = loadConfig();
  const logger = createLogger(config.logLevel);

  logger.info('Starting the Dahua attendance bridge', {
    device: `${config.device.host}:${config.device.port}`,
    server: config.server.url,
    deviceId: config.server.deviceId,
    directionMode: config.direction.mode,
    dryRun: config.dryRun,
  });

  const queue = new OfflineQueue(config.dataDir);
  const dedupe = new ScanDeduper(config.dedupeSeconds);
  const direction = new DirectionResolver(config);
  const flush = () => flushQueue(config, queue, logger);
  const handlePart = createEventPipeline(config, { logger, dedupe, direction, queue, flush });

  if (queue.size > 0) {
    logger.info(`${queue.size} attendance event(s) left over from before this start — attempting delivery now.`);
    await flush();
  }

  const flushTimer = setInterval(() => {
    flush().catch((error) => logger.error('Unexpected error while flushing the queue', { error: error.message }));
  }, PERIODIC_FLUSH_MS);

  let reconnectDelay = RECONNECT_BASE_DELAY_MS;
  let shuttingDown = false;
  let activeConnection = null;

  const connect = () => {
    const connectedAt = Date.now();

    activeConnection = connectToDevice(config, {
      onPart: (part) => {
        handlePart(part).catch((error) => logger.error('Unexpected error handling a device event', { error: error.message }));
      },
      onAuthenticated: () => {
        logger.info('Subscribed to the device\'s event stream.');
        reconnectDelay = RECONNECT_BASE_DELAY_MS;
      },
      onEnded: (reason) => {
        if (shuttingDown) {
          return;
        }

        logger.warn('Disconnected from the device — reconnecting.', { reason: reason?.message });

        if (Date.now() - connectedAt >= STABLE_CONNECTION_MS) {
          reconnectDelay = RECONNECT_BASE_DELAY_MS;
        } else {
          reconnectDelay = Math.min(reconnectDelay * 2, RECONNECT_MAX_DELAY_MS);
        }

        setTimeout(connect, reconnectDelay);
      },
    });
  };

  connect();

  const shutdown = () => {
    if (shuttingDown) return;
    shuttingDown = true;
    logger.info('Shutting down.');
    clearInterval(flushTimer);
    activeConnection?.close();
    process.exit(0);
  };

  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}

main().catch((error) => {
  // eslint-disable-next-line no-console
  console.error(`Fatal startup error: ${error.message}`);
  process.exitCode = 1;
});
