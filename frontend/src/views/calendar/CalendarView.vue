<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { RouteLocationRaw } from 'vue-router'

import { absenceService } from '@/services/absenceService'
import { announcementService } from '@/services/announcementService'
import { examService } from '@/services/examService'
import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import { absenceCategory, CATEGORY_COLOR } from '@/utils/absences'

interface CalendarEvent {
  date: string
  title: string
  label: string
  color: string
  to: RouteLocationRaw
}

interface CalendarCell {
  date: Date
  inMonth: boolean
}

const { t, tm } = useI18n()
const auth = useAuthStore()

/**
 * Local calendar date, not toISOString().slice(0, 10) — see
 * AttendanceView's own toLocalDateString for why: the UTC-based form
 * can land on the wrong day once the browser's local time and
 * APP_TIMEZONE (Asia/Tashkent) disagree about what day it is.
 */
function toLocalDateString(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const todayStr = toLocalDateString(new Date())
const monthAnchor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const selectedDate = ref(todayStr)
const loading = ref(false)

const monthLabel = computed(() => {
  const months = tm('calendar.months') as unknown as string[]
  return `${months[monthAnchor.value.getMonth()]} ${monthAnchor.value.getFullYear()}`
})

const weeks = computed<CalendarCell[][]>(() => {
  const year = monthAnchor.value.getFullYear()
  const month = monthAnchor.value.getMonth()
  const firstOfMonth = new Date(year, month, 1)
  // getDay() is Sun=0..Sat=6; the grid starts Monday, so shift it to Mon=0..Sun=6.
  const startWeekday = (firstOfMonth.getDay() + 6) % 7
  const daysInMonth = new Date(year, month + 1, 0).getDate()

  const cells: CalendarCell[] = []
  for (let i = startWeekday; i > 0; i--) {
    cells.push({ date: new Date(year, month, 1 - i), inMonth: false })
  }
  for (let day = 1; day <= daysInMonth; day++) {
    cells.push({ date: new Date(year, month, day), inMonth: true })
  }
  while (cells.length % 7 !== 0) {
    const last = cells[cells.length - 1].date
    cells.push({ date: new Date(last.getFullYear(), last.getMonth(), last.getDate() + 1), inMonth: false })
  }

  const rows: CalendarCell[][] = []
  for (let i = 0; i < cells.length; i += 7) rows.push(cells.slice(i, i + 7))
  return rows
})

function prevMonth() {
  monthAnchor.value = new Date(monthAnchor.value.getFullYear(), monthAnchor.value.getMonth() - 1, 1)
}
function nextMonth() {
  monthAnchor.value = new Date(monthAnchor.value.getFullYear(), monthAnchor.value.getMonth() + 1, 1)
}
function goToday() {
  monthAnchor.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
  selectedDate.value = todayStr
}

const events = ref<CalendarEvent[]>([])

/**
 * Aggregates from the same list endpoints each source page already
 * uses (tasks/exams/announcements/absences) instead of a dedicated
 * backend endpoint — this inherits each module's existing
 * visibility/scoping exactly as already enforced there, with no
 * separate authorization logic to keep in sync. Absences are the
 * viewer's own only: this is a personal calendar, and the absences page
 * already has a month schedule for a whole team.
 */
async function loadEvents() {
  loading.value = true
  const ownEmployeeId = auth.user?.employee?.id
  try {
    const [tasksResult, examsResult, announcementsResult, absencesResult] = await Promise.all([
      taskService.list({ per_page: 100 }),
      examService.list({ per_page: 100 }),
      announcementService.list({ per_page: 100 }),
      ownEmployeeId
        ? absenceService.list({ 'filter[employee_id]': ownEmployeeId, 'filter[state]': 'active', per_page: 100 })
        : Promise.resolve({ data: [] }),
    ])

    const list: CalendarEvent[] = []

    for (const task of tasksResult.data) {
      if (!task.due_date) continue
      list.push({
        date: task.due_date,
        title: task.title,
        label: t('calendar.taskDue'),
        color: 'warning',
        to: { name: 'task-detail', params: { id: task.id } },
      })
    }

    for (const exam of examsResult.data) {
      if (exam.start_date) {
        list.push({ date: exam.start_date, title: exam.title, label: t('calendar.examStart'), color: 'primary', to: { name: 'exams' } })
      }
      if (exam.end_date) {
        list.push({ date: exam.end_date, title: exam.title, label: t('calendar.examEnd'), color: 'secondary', to: { name: 'exams' } })
      }
    }

    for (const announcement of announcementsResult.data) {
      if (!announcement.publish_at) continue
      list.push({
        date: announcement.publish_at.slice(0, 10),
        title: announcement.title,
        label: t('calendar.announcementPublish'),
        color: 'info',
        to: { name: 'announcement-detail', params: { id: announcement.id } },
      })
    }

    for (const absence of absencesResult.data) {
      const day = new Date(`${absence.start_date}T00:00:00`)
      const end = new Date(`${absence.end_date}T00:00:00`)
      while (day <= end) {
        list.push({
          date: toLocalDateString(day),
          title: t(`absences.types.${absence.type}`),
          label: absence.destination ?? t(`absences.categories.${absenceCategory(absence.type)}`),
          color: CATEGORY_COLOR[absenceCategory(absence.type)],
          to: { name: 'absences' },
        })
        day.setDate(day.getDate() + 1)
      }
    }

    events.value = list
  } finally {
    loading.value = false
  }
}

onMounted(loadEvents)

const eventsByDate = computed(() => {
  const map: Record<string, CalendarEvent[]> = {}
  for (const event of events.value) {
    ;(map[event.date] ??= []).push(event)
  }
  return map
})

const selectedEvents = computed(() => eventsByDate.value[selectedDate.value] ?? [])
const weekdaysShort = computed(() => tm('calendar.weekdaysShort') as unknown as string[])
</script>

<template>
  <AppPageHeader :title="$t('calendar.title')">
    <template #actions>
      <v-btn variant="text" @click="goToday">{{ $t('calendar.today') }}</v-btn>
      <v-btn icon="mdi-chevron-left" variant="text" @click="prevMonth" />
      <span class="text-subtitle-1 font-weight-medium mx-1" style="min-width: 160px; text-align: center">{{ monthLabel }}</span>
      <v-btn icon="mdi-chevron-right" variant="text" @click="nextMonth" />
    </template>
  </AppPageHeader>

  <v-progress-linear v-if="loading" indeterminate class="mb-4" />

  <v-row>
    <v-col cols="12" md="8">
      <v-card class="pa-2">
        <div class="calendar-grid">
          <div v-for="weekday in weekdaysShort" :key="weekday" class="calendar-weekday">{{ weekday }}</div>
        </div>
        <div v-for="(row, rowIndex) in weeks" :key="rowIndex" class="calendar-grid">
          <div
            v-for="cell in row"
            :key="toLocalDateString(cell.date)"
            class="calendar-cell"
            :class="{
              'calendar-cell--muted': !cell.inMonth,
              'calendar-cell--selected': toLocalDateString(cell.date) === selectedDate,
            }"
            @click="selectedDate = toLocalDateString(cell.date)"
          >
            <div class="calendar-cell__date" :class="{ 'calendar-cell__date--today': toLocalDateString(cell.date) === todayStr }">
              {{ cell.date.getDate() }}
            </div>
            <div class="calendar-cell__dots">
              <span
                v-for="(event, idx) in (eventsByDate[toLocalDateString(cell.date)] ?? []).slice(0, 3)"
                :key="idx"
                class="calendar-dot"
                :class="`bg-${event.color}`"
              />
              <span v-if="(eventsByDate[toLocalDateString(cell.date)] ?? []).length > 3" class="text-caption text-medium-emphasis">
                +{{ (eventsByDate[toLocalDateString(cell.date)] ?? []).length - 3 }}
              </span>
            </div>
          </div>
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" md="4">
      <v-card>
        <v-card-title class="text-subtitle-1">{{ selectedDate }}</v-card-title>
        <v-list density="compact">
          <v-list-item v-for="(event, idx) in selectedEvents" :key="idx" :title="event.title" :subtitle="event.label" :to="event.to">
            <template #prepend>
              <span class="calendar-dot" :class="`bg-${event.color}`" />
            </template>
          </v-list-item>
        </v-list>
        <AppEmptyState v-if="!selectedEvents.length" icon="mdi-calendar-blank-outline" />
      </v-card>
    </v-col>
  </v-row>
</template>

<style scoped>
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

.calendar-weekday {
  text-align: center;
  padding: 8px 0;
  font-size: 12px;
  opacity: 0.6;
}

.calendar-cell {
  min-height: 84px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 6px;
  cursor: pointer;
}

.calendar-cell--muted {
  opacity: 0.4;
}

.calendar-cell--selected {
  background: rgba(var(--v-theme-primary), 0.08);
}

.calendar-cell__date--today {
  font-weight: 700;
  color: rgb(var(--v-theme-primary));
}

.calendar-cell__dots {
  display: flex;
  align-items: center;
  gap: 3px;
  margin-top: 4px;
}

.calendar-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}
</style>
