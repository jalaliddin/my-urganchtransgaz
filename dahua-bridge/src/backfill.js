import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { readAccessRecord, resolveEventTime } from './accessControlEvent.js';
import { fetchAccessRecords } from './recordFinderClient.js';
import { epochSecondsToTashkentDateTimeString, toTashkentDateTimeString } from './tashkentTime.js';

/**
 * Pulls whatever happened on the terminal that the live event
 * subscription never saw — either because this bridge wasn't running
 * yet, or was briefly disconnected — via recordFinder.cgi, and queues it
 * through the exact same offline queue the live pipeline uses. This is
 * what makes "runs locally" actually mean something for history, not
 * just for events from the moment the process starts.
 *
 * State (the last record already processed) is persisted in
 * DATA_DIR/backfill-state.json so a restart resumes instead of
 * re-querying from the beginning every time.
 */
export async function runBackfill(config, { logger, queue, direction, flush, fetchRecords = fetchAccessRecords }) {
  if (!config.recordFinder.enabled) {
    return { processed: 0, found: 0 };
  }

  const statePath = join(config.dataDir, 'backfill-state.json');
  const state = loadState(statePath);
  const startTime = determineStartTime(config, state);
  const endTime = Math.floor(Date.now() / 1000);

  const { found, records } = await fetchRecords(config, { startTime, endTime, count: config.recordFinder.count });

  if (found === config.recordFinder.count) {
    logger.warn(
      'recordFinder returned exactly as many records as requested — this window may hold more than that; consider RECORD_FINDER_COUNT or a narrower BACKFILL_SINCE',
      { found },
    );
  }

  let processed = 0;
  // RecNo (confirmed live: a plain incrementing sequence number on every
  // record) is the real "already handled this one" boundary — StartTime
  // alone can't tell two records apart that landed in the same second.
  // This is inferred from one device's behavior, not documented by the
  // vendor, so it is only ever used to skip already-seen records, never
  // to reject a record that lacks one.
  let maxRecNo = state.lastRecNo ?? -1;
  let maxTime = state.lastProcessedTime ?? startTime;

  for (const fields of records) {
    const recNo = Number(fields.RecNo);
    const hasRecNo = Number.isFinite(recNo);

    if (hasRecNo && recNo <= maxRecNo) {
      continue; // already processed on an earlier run
    }

    const accessEvent = readAccessRecord(fields, config);
    const deviceTime = resolveEventTime(fields, config);

    if (accessEvent.personId && accessEvent.successful) {
      const eventTime = deviceTime
        ? epochSecondsToTashkentDateTimeString(deviceTime)
        : toTashkentDateTimeString(new Date());
      const attendanceEvent = { personId: accessEvent.personId, direction: direction.resolve(accessEvent), eventTime };

      // Same contract as the live pipeline: DRY_RUN observes without
      // touching the real queue/webhook — `npm run capture` must not
      // silently backfill production data just because it also runs
      // this on startup.
      if (!config.dryRun) {
        queue.enqueue(attendanceEvent);
      } else {
        logger.info('Backfill (dry run): would queue', attendanceEvent);
      }

      processed++;
    }

    if (hasRecNo) maxRecNo = Math.max(maxRecNo, recNo);
    if (deviceTime !== undefined) maxTime = Math.max(maxTime, Number(deviceTime));
  }

  // Not saved during a dry run: this cursor decides whether a real record
  // is ever offered again, so a "just observing" capture run must not
  // permanently skip it once dry-run testing is over and real backfill
  // runs for real.
  if (!config.dryRun) {
    saveState(statePath, { lastRecNo: maxRecNo, lastProcessedTime: maxTime });
  }

  if (processed > 0 && !config.dryRun) {
    await flush();
  }

  logger.info(`Backfill: ${processed} historical scan(s) queued (${records.length} record(s) found in the window)`, {
    startTime,
    endTime,
  });

  return { processed, found: records.length };
}

function determineStartTime(config, state) {
  if (state.lastProcessedTime !== undefined) {
    return state.lastProcessedTime;
  }

  if (config.recordFinder.backfillSince) {
    const parsed = Date.parse(config.recordFinder.backfillSince);

    if (!Number.isNaN(parsed)) {
      return Math.floor(parsed / 1000);
    }
  }

  // No prior run and no explicit BACKFILL_SINCE: a conservative one-day
  // look-back, so a fresh install's first run stays fast and bounded
  // rather than pulling a terminal's entire history unasked.
  return Math.floor(Date.now() / 1000) - 24 * 3600;
}

function loadState(statePath) {
  if (existsSync(statePath)) {
    try {
      return JSON.parse(readFileSync(statePath, 'utf8'));
    } catch {
      // A corrupted state file starts fresh rather than crashing the bridge.
    }
  }

  return {};
}

function saveState(statePath, state) {
  mkdirSync(join(statePath, '..'), { recursive: true });
  writeFileSync(statePath, JSON.stringify(state));
}
