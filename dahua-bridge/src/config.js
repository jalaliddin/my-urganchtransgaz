import { loadEnv } from './loadEnv.js';

function required(env, key) {
  const value = env[key];

  if (!value) {
    throw new Error(`Missing required setting ${key}. Copy .env.example to .env and fill it in.`);
  }

  return value;
}

function parseList(value, fallback) {
  if (!value) {
    return fallback;
  }

  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function parseBool(value, fallback) {
  if (value === undefined || value === '') {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
}

/**
 * Reads and validates the whole runtime configuration once at startup,
 * so a missing or malformed setting fails immediately and loudly rather
 * than surfacing as a confusing error partway through a live event.
 *
 * `env` is injectable so tests can build a config from a plain object
 * instead of process.env / a real .env file.
 */
export function loadConfig(env = (loadEnv(), process.env)) {
  const directionMode = env.DIRECTION_MODE?.trim() || 'toggle';

  if (!['toggle', 'field'].includes(directionMode)) {
    throw new Error(`DIRECTION_MODE must be "toggle" or "field", got "${directionMode}".`);
  }

  const config = {
    device: {
      host: required(env, 'DEVICE_HOST'),
      port: Number(env.DEVICE_PORT || 80),
      username: required(env, 'DEVICE_USERNAME'),
      password: required(env, 'DEVICE_PASSWORD'),
      channel: env.DEVICE_CHANNEL ? Number(env.DEVICE_CHANNEL) : undefined,
      // How often the device is asked to send a "Heartbeat" keep-alive
      // part, in seconds — also used to size the watchdog that detects a
      // silently-dead connection (see dahuaClient.js).
      heartbeatSeconds: Number(env.DEVICE_HEARTBEAT_SECONDS || 5),
    },
    server: {
      url: required(env, 'SERVER_URL').replace(/\/+$/, ''),
      deviceToken: required(env, 'DEVICE_TOKEN'),
      deviceId: required(env, 'DEVICE_ID'),
    },
    direction: {
      mode: directionMode,
      field: env.DIRECTION_FIELD?.trim() || 'AccessControl.Door',
      entryValues: parseList(env.ENTRY_VALUES, ['1']),
      exitValues: parseList(env.EXIT_VALUES, ['2']),
    },
    personIdFields: parseList(env.PERSON_ID_FIELDS, [
      'AccessControl.UserID',
      'AccessControl.CardNo',
      'UserID',
      'CardNo',
    ]),
    logRawEvents: parseBool(env.LOG_RAW_EVENTS, false),
    dryRun: parseBool(env.DRY_RUN, false),
    logLevel: env.LOG_LEVEL?.trim() || 'info',
    // Two scans of the same person within this many seconds are treated
    // as one physical pass (a lingering face/card read, a double tap) —
    // a real second visit is never this fast.
    dedupeSeconds: Number(env.DEDUPE_SECONDS || 4),
    dataDir: env.DATA_DIR?.trim() || new URL('../data/', import.meta.url).pathname,
  };

  return config;
}
