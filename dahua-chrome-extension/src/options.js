import { DeviceError, findAccessRecords } from './lib/dahua.js';
import { tashkentToday } from './lib/records.js';
import { DEFAULT_SETTINGS, loadAll } from './lib/sync.js';
import { checkDeviceToken } from './lib/webhook.js';
import { DIRECTION_LABELS, h } from './ui.js';

const store = chrome.storage.local;
const REQUIRED = ['host', 'username', 'password', 'deviceId', 'deviceToken'];

function notifyBackground() {
  return chrome.runtime.sendMessage({ type: 'settings-changed' }).catch(() => {});
}

/** Host permission for the terminal (and a non-default server), asked from the click itself. */
function requestAccess(device, serverUrl) {
  const origins = [];
  if (device?.host) origins.push(`http://${device.host}/*`);
  if (serverUrl && !serverUrl.startsWith('https://my.urtg.uz/')) origins.push(`${new URL(serverUrl).origin}/*`);

  return origins.length ? chrome.permissions.request({ origins }) : Promise.resolve(true);
}

function showResult(container, lines) {
  container.replaceChildren(...lines.map(({ ok, text }) => h('div', { class: `notice ${ok ? '' : 'error'}`, style: ok ? 'background: var(--navy-soft); color: var(--ok)' : null }, `${ok ? '✓' : '✗'} ${text}`)));
}

function field(label, name, value, attributes = {}) {
  const id = `${name}-${Math.random().toString(36).slice(2, 8)}`;

  return h('div', { class: 'field' }, h('label', { for: id }, label), h('input', { id, name, value: value ?? '', ...attributes }));
}

function readForm(form, original) {
  const data = Object.fromEntries(new FormData(form));

  return {
    ...original,
    name: data.name.trim(),
    host: data.host.trim(),
    port: Number(data.port) || 80,
    username: data.username.trim(),
    password: data.password,
    deviceId: data.deviceId.trim(),
    deviceToken: data.deviceToken.trim(),
    direction: data.direction,
    syncFrom: data.syncFrom || tashkentToday(Date.now()),
    enabled: form.elements.enabled.checked,
  };
}

function deviceForm(device, serverUrl, isNew) {
  const result = h('div');
  const direction = h(
    'select',
    { name: 'direction', id: `direction-${device.id}` },
    ...Object.entries({
      check_in: "Kirish (bu terminal faqat kirishni ko'radi)",
      check_out: "Chiqish (bu terminal faqat chiqishni ko'radi)",
      type: "Terminal o'zi aytadi (Type: Entry/Exit)",
    }).map(([value, label]) => h('option', { value, selected: device.direction === value }, label)),
  );

  const form = h(
    'form',
    { class: 'card', 'data-id': device.id, novalidate: true },
    h(
      'div',
      { class: 'row spread device-head' },
      h('b', {}, isNew ? 'Yangi terminal' : device.name || device.host),
      device.direction ? h('span', { class: 'chip' }, DIRECTION_LABELS[device.direction]) : null,
    ),
    h(
      'div',
      { class: 'grid-2' },
      field('Nomi', 'name', device.name, { placeholder: 'Asosiy darvoza — kirish' }),
      h('div', { class: 'grid-2', style: 'gap: 0 8px' },
        field('IP manzil', 'host', device.host, { placeholder: '10.100.90.51', required: true }),
        field('Port', 'port', device.port ?? 80, { type: 'number', min: 1, max: 65535 })),
      field('Terminal logini', 'username', device.username ?? 'admin', { required: true, autocomplete: 'off' }),
      field('Terminal paroli', 'password', device.password, { type: 'password', required: true, autocomplete: 'new-password' }),
      field('Device ID (my.urtg.uz)', 'deviceId', device.deviceId, { placeholder: 'TURNSTILE-1', required: true }),
      field('Qurilma tokeni', 'deviceToken', device.deviceToken, { type: 'password', required: true, autocomplete: 'off' }),
      h('div', { class: 'field' }, h('label', { for: `direction-${device.id}` }, "Yo'nalish"), direction),
      field('Qaysi sanadan boshlab yuklash', 'syncFrom', device.syncFrom ?? tashkentToday(Date.now()), { type: 'date' }),
    ),
    h('label', { class: 'check field' }, h('input', { type: 'checkbox', name: 'enabled', checked: device.enabled !== false }), 'Faol'),
    h(
      'div',
      { class: 'buttons' },
      h('button', { class: 'primary', type: 'submit' }, 'Saqlash'),
      h('button', { type: 'button', 'data-action': 'test' }, 'Ulanishni tekshirish'),
      isNew ? null : h('button', { type: 'button', 'data-action': 'resync' }, 'Shu sanadan qayta yuklash'),
      h('button', { class: 'danger', type: 'button', 'data-action': 'delete' }, isNew ? 'Bekor qilish' : "O'chirish"),
    ),
    result,
  );

  const missing = (candidate) => REQUIRED.filter((key) => !candidate[key]);

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const candidate = readForm(form, device);
    if (missing(candidate).length) {
      showResult(result, [{ ok: false, text: "IP manzil, login, parol, Device ID va token to'ldirilishi kerak." }]);
      return;
    }

    const granted = await requestAccess(candidate, serverUrl);
    if (!granted) {
      showResult(result, [{ ok: false, text: 'Brauzer terminalga ulanishga ruxsat bermadi.' }]);
      return;
    }

    const { settings, state } = await loadAll(store);
    const index = settings.devices.findIndex((d) => d.id === candidate.id);
    if (index === -1) settings.devices.push(candidate);
    else settings.devices[index] = candidate;

    // New settings deserve a fresh try at delivering what was held back.
    state[candidate.id] = { ...(state[candidate.id] ?? {}), blocked: false, lastError: null };
    await store.set({ settings, state });
    await notifyBackground();
    render();
  });

  form.addEventListener('click', async (event) => {
    const action = event.target.closest('button')?.dataset.action;
    if (!action) return;

    if (action === 'test') {
      const candidate = readForm(form, device);
      if (missing(candidate).length) {
        showResult(result, [{ ok: false, text: "Avval IP manzil, login, parol, Device ID va tokenni to'ldiring." }]);
        return;
      }

      const granted = await requestAccess(candidate, serverUrl);
      if (!granted) {
        showResult(result, [{ ok: false, text: 'Brauzer terminalga ulanishga ruxsat bermadi.' }]);
        return;
      }

      showResult(result, [{ ok: true, text: 'Tekshirilmoqda…' }]);
      const lines = [];
      const now = Math.floor(Date.now() / 1000);

      try {
        // Run from this page, not the background worker: if Chrome asks
        // for local-network permission, it can only ask here.
        await findAccessRecords(candidate, { startTime: now - 3600, endTime: now, count: 1 });
        lines.push({ ok: true, text: "Terminal javob berdi, login va parol to'g'ri." });
      } catch (error) {
        lines.push({ ok: false, text: error instanceof DeviceError ? error.message : `Terminal: ${error.message}` });
      }

      const token = await checkDeviceToken(serverUrl, candidate);
      lines.push(
        token.ok
          ? { ok: true, text: 'Server qurilma tokenini qabul qildi.' }
          : token.reason === 'token'
            ? { ok: false, text: "Server tokenni rad etdi — token noto'g'ri yoki qurilma o'chirilgan." }
            : { ok: false, text: `Serverga ulanib bo'lmadi (${token.status ?? token.error}).` },
      );
      showResult(result, lines);
    }

    if (action === 'resync') {
      const candidate = readForm(form, device);
      if (!confirm(`${candidate.syncFrom} dan boshlab barcha yozuvlar qayta yuklansinmi? Avval yozilganlari takrorlanmaydi.`)) return;

      const { settings, state } = await loadAll(store);
      const saved = settings.devices.find((d) => d.id === device.id);
      if (saved) saved.syncFrom = candidate.syncFrom;
      state[device.id] = { ...(state[device.id] ?? {}), cursor: undefined, blocked: false, lastError: null };
      await store.set({ settings, state });
      showResult(result, [{ ok: true, text: 'Qayta yuklash boshlandi — natijani ikonka ustidagi oynada kuzating.' }]);
      chrome.runtime.sendMessage({ type: 'sync-now' }).catch(() => {});
    }

    if (action === 'delete') {
      if (isNew) {
        render();
        return;
      }
      if (!confirm(`«${device.name || device.host}» o'chirilsinmi? Navbatdagi yuborilmagan yozuvlari ham o'chadi.`)) return;

      const { settings, state, queue } = await loadAll(store);
      settings.devices = settings.devices.filter((d) => d.id !== device.id);
      delete state[device.id];
      await store.set({ settings, state, queue: queue.filter((e) => e.deviceRef !== device.id) });
      await notifyBackground();
      render();
    }
  });

  return form;
}

async function render() {
  const { settings } = await loadAll(store);
  const general = document.getElementById('general');
  general.elements.serverUrl.value = settings.serverUrl;
  general.elements.intervalMinutes.value = settings.intervalMinutes;

  document.getElementById('devices').replaceChildren(
    ...(settings.devices.length
      ? settings.devices.map((device) => deviceForm(device, settings.serverUrl, false))
      : [h('div', { class: 'card muted' }, "Hali terminal qo'shilmagan.")]),
  );
}

document.getElementById('general').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const result = document.getElementById('general-result');
  const serverUrl = form.elements.serverUrl.value.trim().replace(/\/+$/, '') || DEFAULT_SETTINGS.serverUrl;
  const intervalMinutes = Math.min(60, Math.max(1, Number(form.elements.intervalMinutes.value) || 2));

  if (!(await requestAccess(null, serverUrl))) {
    showResult(result, [{ ok: false, text: 'Brauzer serverga ulanishga ruxsat bermadi.' }]);
    return;
  }

  const { settings, state } = await loadAll(store);
  if (serverUrl !== settings.serverUrl) {
    for (const ds of Object.values(state)) Object.assign(ds, { blocked: false, lastError: null });
  }
  await store.set({ settings: { ...settings, serverUrl, intervalMinutes }, state });
  await notifyBackground();
  showResult(result, [{ ok: true, text: 'Saqlandi.' }]);
  render();
});

document.getElementById('add-device').addEventListener('click', async () => {
  const { settings } = await loadAll(store);
  const fresh = { id: crypto.randomUUID(), port: 80, username: 'admin', direction: 'check_in', enabled: true };
  const container = document.getElementById('devices');
  if (settings.devices.length === 0) container.replaceChildren();
  const form = deviceForm(fresh, settings.serverUrl, true);
  container.append(form);
  form.querySelector('input').focus();
});

render();
