import { loadAll } from './lib/sync.js';
import { DIRECTION_LABELS, formatTime, h } from './ui.js';

const store = chrome.storage.local;

function deviceCard(device, ds, queued) {
  const status = device.enabled === false
    ? h('span', { class: 'small muted' }, "O'chirilgan")
    : ds?.lastError
      ? h('span', { class: 'small status-error' }, '⚠ Xatolik')
      : ds?.lastSyncAt
        ? h('span', { class: 'small status-ok' }, '✓ Ishlayapti')
        : h('span', { class: 'small muted' }, 'Hali sinxronlanmagan');

  return h(
    'div',
    { class: 'card' },
    h(
      'div',
      { class: 'row spread' },
      h('div', {}, h('b', {}, device.name || device.host), ' ', h('span', { class: 'chip' }, DIRECTION_LABELS[device.direction] ?? '')),
      status,
    ),
    h('div', { class: 'small muted' }, `${device.host} · ${device.deviceId}`),
    h(
      'div',
      { class: 'stats' },
      h('div', { class: 'stat' }, h('b', {}, String(ds?.today?.delivered ?? 0)), h('span', { class: 'small muted' }, 'Bugun yozildi')),
      h('div', { class: 'stat' }, h('b', {}, String(queued)), h('span', { class: 'small muted' }, 'Navbatda')),
      h('div', { class: 'stat' }, h('b', {}, String(ds?.today?.rejected ?? 0)), h('span', { class: 'small muted' }, 'Rad etildi')),
    ),
    h('div', { class: 'small muted', style: 'margin-top: 6px' }, `Oxirgi sinxronlash: ${formatTime(ds?.lastSyncAt)}`),
    ds?.lastError ? h('div', { class: 'notice error' }, ds.lastError) : null,
  );
}

async function render() {
  const { settings, state, queue, log } = await loadAll(store);
  const { lastRunAt } = await store.get('lastRunAt');
  const devicesEl = document.getElementById('devices');

  document.getElementById('last-run').textContent = `Oxirgi tekshiruv: ${formatTime(lastRunAt)}`;

  if (settings.devices.length === 0) {
    devicesEl.replaceChildren(
      h('div', { class: 'card' }, h('p', { style: 'margin: 0' }, "Hali terminal qo'shilmagan. «Sozlamalar» orqali qo'shing.")),
    );
  } else {
    devicesEl.replaceChildren(
      ...settings.devices.map((device) =>
        deviceCard(device, state[device.id], queue.filter((event) => event.deviceRef === device.id).length),
      ),
    );
  }

  const unknown = settings.devices.flatMap((device) =>
    Object.entries(state[device.id]?.unknown ?? {}).map(([personId, info]) => ({ personId, ...info, device })),
  );
  unknown.sort((a, b) => b.lastSeen.localeCompare(a.lastSeen));
  document.getElementById('unknown-section').hidden = unknown.length === 0;
  document.getElementById('unknown').replaceChildren(
    ...unknown.slice(0, 30).map((item) =>
      h(
        'li',
        {},
        h('b', {}, item.personId),
        item.name ? ` — ${item.name}` : '',
        h('div', { class: 'muted' }, `${item.count} marta · oxirgi: ${item.lastSeen} · ${item.device.name}`),
      ),
    ),
  );

  document.getElementById('log').replaceChildren(
    ...(log.length === 0
      ? [h('li', { class: 'muted' }, "Hozircha yo'q")]
      : log.slice(0, 12).map((entry) =>
          h(
            'li',
            { class: entry.level === 'error' ? 'status-error' : entry.level === 'warn' ? 'status-warn' : '' },
            h('span', { class: 'muted' }, `${formatTime(entry.at)} `),
            entry.message,
          ),
        )),
  );
}

document.getElementById('sync').addEventListener('click', async (event) => {
  const button = event.currentTarget;
  button.disabled = true;
  button.textContent = 'Sinxronlanmoqda…';

  try {
    await chrome.runtime.sendMessage({ type: 'sync-now' });
  } finally {
    button.disabled = false;
    button.textContent = 'Hozir sinxronlash';
    render();
  }
});

document.getElementById('settings').addEventListener('click', () => chrome.runtime.openOptionsPage());
chrome.storage.onChanged.addListener(() => render());
render();
