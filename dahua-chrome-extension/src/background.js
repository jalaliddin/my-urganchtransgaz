import { loadAll, runSync } from './lib/sync.js';

const ALARM = 'dahua-sync';
const store = chrome.storage.local;
let running = null;

async function ensureAlarm() {
  const { settings } = await loadAll(store);
  const period = Math.max(1, Number(settings.intervalMinutes) || 2);
  const existing = await chrome.alarms.get(ALARM);

  if (existing?.periodInMinutes !== period) {
    await chrome.alarms.create(ALARM, { delayInMinutes: 0.1, periodInMinutes: period });
  }
}

/** The extension icon's badge: "!" when a terminal has a problem, else the queue size. */
async function updateBadge() {
  const { settings, state, queue } = await loadAll(store);
  const hasError = settings.devices.some((device) => device.enabled !== false && state[device.id]?.lastError);

  if (hasError) {
    await chrome.action.setBadgeBackgroundColor({ color: '#C62828' });
    await chrome.action.setBadgeText({ text: '!' });
  } else if (queue.length > 0) {
    await chrome.action.setBadgeBackgroundColor({ color: '#B26A00' });
    await chrome.action.setBadgeText({ text: queue.length > 999 ? '999+' : String(queue.length) });
  } else {
    await chrome.action.setBadgeText({ text: '' });
  }
}

/** One run at a time: an alarm firing during a manual sync joins it. */
function sync() {
  running ??= runSync({ store })
    .then(async (result) => {
      await updateBadge();

      return result;
    })
    .finally(() => {
      running = null;
    });

  return running;
}

chrome.runtime.onInstalled.addListener(() => {
  ensureAlarm();
});

chrome.runtime.onStartup.addListener(() => {
  ensureAlarm();
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === ALARM) sync();
});

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message?.type === 'sync-now') {
    sync().then(
      (result) => sendResponse({ ok: true, result }),
      (error) => sendResponse({ ok: false, error: error.message }),
    );

    return true;
  }

  if (message?.type === 'settings-changed') {
    Promise.all([ensureAlarm(), updateBadge()]).then(() => sendResponse({ ok: true }));

    return true;
  }

  return false;
});
