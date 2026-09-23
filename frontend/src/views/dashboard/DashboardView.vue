<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { announcementService } from '@/services/announcementService'
import { attendanceService } from '@/services/attendanceService'
import { kpiService } from '@/services/kpiService'
import { profileService, type ProfileCompletion } from '@/services/profileService'
import { reportService } from '@/services/settingsService'
import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import type { Announcement, EmployeeKpi, ReportOverview, Task, TodayAttendance } from '@/types/models'
import logoMarkUrl from '@/assets/logo-mark.png'

const { t } = useI18n()
const auth = useAuthStore()
const completion = ref<ProfileCompletion | null>(null)

const myTasks = ref<Task[]>([])
const myActiveTasks = computed(() => myTasks.value.filter((t) => !['completed', 'cancelled'].includes(t.status)))
const myOpenTasksCount = computed(() => myActiveTasks.value.length)
const myOverdueTasksCount = computed(() => myTasks.value.filter((t) => t.status === 'overdue').length)
// The few due soonest, not the whole list — this is an orientation
// widget ("what needs me"), the Tasks page is where the full list lives.
const dueSoonTasks = computed(() =>
  [...myActiveTasks.value]
    .sort((a, b) => (a.due_date ?? '9999').localeCompare(b.due_date ?? '9999'))
    .slice(0, 4),
)

async function loadMyTasks() {
  const result = await taskService.list({ per_page: 100 })
  myTasks.value = result.data.filter((t) => t.assignees?.some((a) => a.id === auth.user?.employee?.id))
}

const today = ref<TodayAttendance[]>([])
const checkingInOut = ref(false)
const myAttendance = computed(
  () => today.value.find((row) => row.employee_id === auth.user?.employee?.id)?.attendance ?? null,
)

async function loadToday() {
  today.value = await attendanceService.today()
}

async function checkIn() {
  checkingInOut.value = true
  try {
    await attendanceService.checkIn()
    await loadToday()
  } finally {
    checkingInOut.value = false
  }
}

async function checkOut() {
  checkingInOut.value = true
  try {
    await attendanceService.checkOut()
    await loadToday()
  } finally {
    checkingInOut.value = false
  }
}

const latestKpi = ref<EmployeeKpi | null>(null)
async function loadMyKpi() {
  const result = await kpiService.my({ per_page: 1 })
  latestKpi.value = result.data[0] ?? null
}

// Creators/reviewers get their own management list on the Announcements
// page instead — this widget is the personal feed, so it's only shown to
// plain audience members to avoid surfacing an admin's own drafts here.
const showAnnouncements = computed(() => !!auth.user?.employee && !auth.can('announcements.create'))
const latestAnnouncements = ref<Announcement[]>([])
async function loadAnnouncements() {
  const result = await announcementService.list({ per_page: 3 })
  latestAnnouncements.value = result.data
}

// Company-wide charts (§31 "Employee distribution"/task status) — mirrors
// the backend's hasCentralAccess() gate on ReportController::overview()
// (super-admin/central-admin/hr/technical-policy).
const showOverview = computed(
  () =>
    auth.hasRole('super-admin') ||
    auth.hasRole('central-admin') ||
    auth.hasRole('hr') ||
    auth.hasRole('technical-policy'),
)
const overview = ref<ReportOverview | null>(null)
async function loadOverview() {
  try {
    overview.value = await reportService.overview()
  } catch {
    overview.value = null
  }
}

const loading = ref(true)

onMounted(async () => {
  try {
    if (auth.user?.employee) {
      completion.value = await profileService.completion()
      await loadToday()
      await loadMyTasks()
      await loadMyKpi()
      if (showAnnouncements.value) await loadAnnouncements()
    }
    if (showOverview.value) await loadOverview()
  } finally {
    loading.value = false
  }
})

// --- Hero: greeting + today's date, both said the same way this
// company's mobile app already says them, so the two clients read as one
// product rather than two independent designs.
const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return t('dashboard.greetingMorning')
  if (hour < 18) return t('dashboard.greetingAfternoon')
  return t('dashboard.greetingEvening')
})

// A plain numeric date (23.09.2026), not a spelled-out "weekday, day
// month" — the same day.month.year order reads correctly in Uzbek and
// Russian without depending on the browser's locale data for weekday/month
// names, which is inconsistent for Uzbek specifically (verified directly:
// Chromium's own Intl.DateTimeFormat('uz', {weekday:'long', month:'long'})
// falls back to English-ish fragments like "M09 23, Wed" instead of real
// Uzbek text).
const todayLabel = computed(() => {
  const date = new Date()
  return [date.getDate(), date.getMonth() + 1, date.getFullYear()].map((n) => String(n).padStart(2, '0')).join('.')
})

function timeOnly(value: string | null): string {
  return value ? value.slice(11, 16) : '—'
}
</script>

<template>
  <div class="dashboard-hero mb-6">
    <img :src="logoMarkUrl" alt="" class="dashboard-hero__mark" />
    <div class="dashboard-hero__content">
      <p class="text-body-2 dashboard-hero__date">{{ todayLabel }}</p>
      <h1 class="text-h5 font-weight-bold mt-1">
        {{ greeting }}, {{ auth.user?.employee?.first_name ?? auth.user?.name }}
      </h1>
      <p class="text-body-2 dashboard-hero__role mt-1">
        {{ auth.user?.employee?.position?.title ?? auth.user?.roles?.join(', ') }}
        <template v-if="auth.user?.employee?.organization"> · {{ auth.user.employee.organization.name }}</template>
      </p>
    </div>
    <AppAvatar
      :photo-url="auth.user?.employee?.photo_url"
      :name="auth.user?.name"
      :size="56"
      class="dashboard-hero__avatar"
    />
  </div>

  <v-row v-if="auth.user?.employee" class="mb-2">
    <v-col cols="6" sm="3">
      <div class="stat-tile">
        <div class="stat-tile__value">{{ myOpenTasksCount }}</div>
        <div class="stat-tile__label">{{ $t('dashboard.activeTasks') }}</div>
      </div>
    </v-col>
    <v-col cols="6" sm="3">
      <div class="stat-tile" :class="{ 'stat-tile--warn': myOverdueTasksCount > 0 }">
        <div class="stat-tile__value">{{ myOverdueTasksCount }}</div>
        <div class="stat-tile__label">{{ $t('status.overdue') }}</div>
      </div>
    </v-col>
    <v-col cols="6" sm="3">
      <div class="stat-tile">
        <div class="stat-tile__value">
          <AppStatusChip v-if="myAttendance" :status="myAttendance.status" />
          <span v-else class="text-body-2 text-medium-emphasis">—</span>
        </div>
        <div class="stat-tile__label">{{ $t('attendance.myStatusToday') }}</div>
      </div>
    </v-col>
    <v-col cols="6" sm="3">
      <div class="stat-tile">
        <div class="stat-tile__value">{{ latestKpi ? `${latestKpi.score}%` : '—' }}</div>
        <div class="stat-tile__label">{{ $t('kpi.score') }}</div>
      </div>
    </v-col>
  </v-row>

  <v-row v-if="!loading">
    <v-col cols="12" lg="8">
      <v-card v-if="auth.user?.employee" class="mb-4">
        <v-card-item>
          <template #title>
            <span class="text-subtitle-1 font-weight-bold">{{ $t('attendance.myStatusToday') }}</span>
          </template>
        </v-card-item>
        <v-card-text>
          <div class="d-flex flex-wrap align-center ga-6">
            <div>
              <div class="text-caption text-medium-emphasis">{{ $t('attendance.checkIn') }}</div>
              <div class="text-h6">{{ timeOnly(myAttendance?.check_in ?? null) }}</div>
            </div>
            <div>
              <div class="text-caption text-medium-emphasis">{{ $t('attendance.checkOut') }}</div>
              <div class="text-h6">{{ timeOnly(myAttendance?.check_out ?? null) }}</div>
            </div>
            <v-spacer />
            <v-btn
              color="primary"
              variant="flat"
              :loading="checkingInOut"
              :disabled="Boolean(myAttendance?.check_in)"
              @click="checkIn"
            >
              {{ $t('attendance.checkIn') }}
            </v-btn>
            <v-btn
              variant="tonal"
              :loading="checkingInOut"
              :disabled="!myAttendance?.check_in || Boolean(myAttendance?.check_out)"
              @click="checkOut"
            >
              {{ $t('attendance.checkOut') }}
            </v-btn>
          </div>
        </v-card-text>
      </v-card>

      <v-card v-if="auth.user?.employee" class="mb-4">
        <v-card-item>
          <template #title>
            <span class="text-subtitle-1 font-weight-bold">{{ $t('dashboard.dueSoon') }}</span>
          </template>
          <template #append>
            <v-btn variant="text" size="small" :to="{ name: 'tasks' }" append-icon="mdi-arrow-right">
              {{ $t('dashboard.seeAll') }}
            </v-btn>
          </template>
        </v-card-item>
        <v-list v-if="dueSoonTasks.length" density="comfortable" lines="two">
          <v-list-item
            v-for="task in dueSoonTasks"
            :key="task.id"
            :to="{ name: 'task-detail', params: { id: task.id } }"
            :title="task.title"
          >
            <template #subtitle>
              <span :class="{ 'text-error': task.status === 'overdue' }">
                {{ task.due_date ?? $t('dashboard.noDueDate') }}
              </span>
            </template>
            <template #append>
              <span class="text-body-2 text-medium-emphasis">{{ task.progress }}%</span>
            </template>
          </v-list-item>
        </v-list>
        <v-card-text v-else class="text-body-2 text-medium-emphasis">{{ $t('dashboard.allCaughtUp') }}</v-card-text>
      </v-card>

      <v-card v-if="showAnnouncements && latestAnnouncements.length">
        <v-card-item>
          <template #title>
            <span class="text-subtitle-1 font-weight-bold">{{ $t('nav.announcements') }}</span>
          </template>
          <template #append>
            <v-btn variant="text" size="small" :to="{ name: 'announcements' }" append-icon="mdi-arrow-right">
              {{ $t('dashboard.seeAll') }}
            </v-btn>
          </template>
        </v-card-item>
        <v-list density="comfortable">
          <v-list-item
            v-for="announcement in latestAnnouncements"
            :key="announcement.id"
            :title="announcement.title"
            :to="{ name: 'announcement-detail', params: { id: announcement.id } }"
          >
            <template #prepend>
              <v-icon v-if="!announcement.is_read" color="primary" icon="mdi-circle-medium" />
            </template>
          </v-list-item>
        </v-list>
      </v-card>
    </v-col>

    <v-col cols="12" lg="4">
      <v-card v-if="completion" class="mb-4">
        <v-card-text>
          <div class="d-flex justify-space-between mb-2">
            <span class="text-subtitle-2">{{ $t('dashboard.profileCompletion') }}</span>
            <span class="text-subtitle-2 font-weight-bold">{{ completion.percentage }}%</span>
          </div>
          <v-progress-linear :model-value="completion.percentage" color="primary" height="10" rounded class="mb-3" />
          <v-btn v-if="completion.percentage < 100" variant="text" size="small" :to="{ name: 'profile' }" class="px-0" append-icon="mdi-arrow-right">
            {{ $t('profile.title') }}
          </v-btn>
        </v-card-text>
      </v-card>

      <v-card v-if="latestKpi" class="mb-4">
        <v-card-text>
          <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('nav.kpi') }}</div>
          <div class="text-h4 font-weight-bold mb-1">{{ latestKpi.score }}%</div>
          <div class="text-body-2 text-medium-emphasis mb-3">{{ latestKpi.indicator?.name }} · {{ latestKpi.period?.name }}</div>
          <v-btn variant="text" size="small" :to="{ name: 'kpi' }" class="px-0" append-icon="mdi-arrow-right">
            {{ $t('nav.kpi') }}
          </v-btn>
        </v-card-text>
      </v-card>

      <v-card v-if="!auth.user?.employee">
        <v-card-text class="text-body-2 text-medium-emphasis">{{ $t('dashboard.adminAccount') }}</v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <template v-if="overview">
    <h2 class="text-subtitle-1 font-weight-bold mt-2 mb-3">{{ $t('dashboard.companyOverview') }}</h2>
    <v-row>
      <v-col v-if="overview.employees_by_organization.length" cols="12" md="6">
        <v-card>
          <v-card-text>
            <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('reports.employeeDistribution') }}</div>
            <AppChart
              type="bar"
              :labels="overview.employees_by_organization.map((r) => r.label)"
              :data="overview.employees_by_organization.map((r) => r.total)"
              :label="$t('employees.title')"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="overview.tasks_by_status.length" cols="12" md="6">
        <v-card>
          <v-card-text>
            <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('reports.taskStatusBreakdown') }}</div>
            <AppChart
              type="pie"
              :labels="overview.tasks_by_status.map((r) => $t(`status.${r.status}`))"
              :data="overview.tasks_by_status.map((r) => r.total)"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </template>
</template>

<style scoped>
/* The one bold gesture on this page (see frontend-design's "spend your
 * boldness in one place") — everything below it stays plain cards, no
 * second gradient or accent color competing for attention. */
.dashboard-hero {
  position: relative;
  overflow: hidden;
  border-radius: 20px;
  padding: 28px 28px 24px;
  display: flex;
  align-items: center;
  gap: 20px;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #101d33 100%);
  color: #fff;
}

.dashboard-hero__mark {
  position: absolute;
  right: -18px;
  bottom: -28px;
  height: 150px;
  width: auto;
  opacity: 0.1;
  pointer-events: none;
}

.dashboard-hero__content {
  flex: 1;
  min-width: 0;
  z-index: 1;
}

.dashboard-hero__date {
  color: rgba(255, 255, 255, 0.7);
  margin: 0;
}

.dashboard-hero__role {
  color: rgba(255, 255, 255, 0.82);
}

.dashboard-hero__avatar {
  z-index: 1;
  border: 2px solid rgba(255, 255, 255, 0.35);
}

.stat-tile {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  padding: 14px 16px;
  background: rgb(var(--v-theme-surface));
}

.stat-tile--warn .stat-tile__value {
  color: rgb(var(--v-theme-error));
}

.stat-tile__value {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.2;
}

.stat-tile__label {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  margin-top: 2px;
}

@media (max-width: 600px) {
  .dashboard-hero {
    padding: 20px;
  }

  .dashboard-hero__avatar {
    display: none;
  }
}
</style>
