import type { MapLegendItem } from '@/components/issues/LeafletMap.vue'
import type { IssueStatus } from '@/types/models'
import { daysUntil } from '@/utils/date'

/** Mirrors BuildIssueReport::STALE_AFTER_DAYS on the backend. */
export const ISSUE_STALE_AFTER_DAYS = 7

// Status steps from the dataviz skill's reserved palette. Each also has its
// own glyph: red and green are indistinguishable to many color-blind readers,
// so the glyph is what actually separates the three states.
export const ISSUE_PIN_STYLES = {
  open: { color: '#d03b3b', glyph: '' },
  stale: { color: '#8f1d1d', glyph: '!' },
  resolved: { color: '#0ca30c', glyph: '✓' },
} as const

/** Days an open issue has been open, from its created_at timestamp. */
export function openDays(createdAt: string): number {
  return -daysUntil(createdAt)
}

export function issuePinStyle(status: IssueStatus, daysOpen: number | null): { color: string; glyph: string } {
  if (status === 'resolved') return ISSUE_PIN_STYLES.resolved
  return daysOpen !== null && daysOpen > ISSUE_STALE_AFTER_DAYS ? ISSUE_PIN_STYLES.stale : ISSUE_PIN_STYLES.open
}

export function issueLegend(t: (key: string, values?: Record<string, unknown>) => string): MapLegendItem[] {
  return [
    { ...ISSUE_PIN_STYLES.open, label: t('status.open') },
    { ...ISSUE_PIN_STYLES.stale, label: t('issueReport.stale', { days: ISSUE_STALE_AFTER_DAYS }) },
    { ...ISSUE_PIN_STYLES.resolved, label: t('status.resolved') },
  ]
}
