import { toTashkentDateTime } from './lib/records.js';

export const DIRECTION_LABELS = {
  check_in: 'Kirish',
  check_out: 'Chiqish',
  type: 'Kirish/chiqish',
};

/**
 * Tiny element builder. Text always goes in as text nodes — device names
 * and people's names from the terminal are never parsed as HTML.
 */
export function h(tag, attributes = {}, ...children) {
  const element = document.createElement(tag);

  for (const [name, value] of Object.entries(attributes)) {
    if (value === false || value === null || value === undefined) continue;
    element.setAttribute(name, value === true ? '' : value);
  }

  for (const child of children.flat()) {
    if (child === null || child === undefined || child === false) continue;
    element.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }

  return element;
}

/** Tashkent wall-clock time; just the time for today, date and time otherwise. */
export function formatTime(ms) {
  if (!ms) return '—';

  const value = toTashkentDateTime(ms / 1000);
  const today = toTashkentDateTime(Date.now() / 1000).slice(0, 10);

  return value.startsWith(today) ? value.slice(11, 16) : value.slice(0, 16);
}
