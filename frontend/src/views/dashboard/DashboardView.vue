<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { announcementService } from '@/services/announcementService'
import { attendanceService } from '@/services/attendanceService'
import { businessTripService } from '@/services/businessTripService'
import { kpiService } from '@/services/kpiService'
import { profileService, type ProfileCompletion } from '@/services/profileService'
import { taskService } from '@/services/taskService'
import { useAuthStore } from '@/stores/auth'
import type { Announcement, BusinessTrip, EmployeeKpi, Task, TodayAttendance } from '@/types/models'

const auth = useAuthStore()
const completion = ref<ProfileCompletion | null>(null)

const myTasks = ref<Task[]>([])
const myOpenTasksCount = computed(
  () => myTasks.value.filter((t) => !['completed', 'cancelled'].includes(t.status)).length,
)
const myOverdueTasksCount = computed(() => myTasks.value.filter((t) => t.status === 'overdue').length)

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

const upcomingTrips = ref<BusinessTrip[]>([])
async function loadUpcomingTrips() {
  upcomingTrips.value = await businessTripService.upcoming()
}

onMounted(async () => {
  if (auth.user?.employee) {
    completion.value = await profileService.completion()
    await loadToday()
    await loadMyTasks()
    await loadMyKpi()
    await loadUpcomingTrips()
    if (showAnnouncements.value) await loadAnnouncements()
  }
})
</script>

<template>
  <AppPageHeader :title="$t('dashboard.title')" />

  <v-row>
    <v-col cols="12" md="6" lg="4">
      <v-card>
        <v-card-item>
          <div class="d-flex align-center ga-4">
            <AppAvatar :photo-url="auth.user?.employee?.photo_url" :name="auth.user?.name" :size="56" />
            <div>
              <div class="text-subtitle-1 font-weight-bold">
                {{ $t('dashboard.welcome') }}, {{ auth.user?.name }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ auth.user?.employee?.position?.title ?? auth.user?.roles?.join(', ') }}
              </div>
            </div>
          </div>
        </v-card-item>
        <v-divider />
        <v-list density="compact">
          <v-list-item
            v-if="auth.user?.employee?.organization"
            :title="auth.user.employee.organization.name"
            :subtitle="$t('employees.organization')"
            prepend-icon="mdi-domain"
          />
          <v-list-item
            v-if="auth.user?.employee?.department"
            :title="auth.user.employee.department.name"
            :subtitle="$t('employees.department')"
            prepend-icon="mdi-sitemap-outline"
          />
          <v-list-item
            v-if="auth.user?.employee?.employee_number"
            :title="auth.user.employee.employee_number"
            :subtitle="$t('employees.employeeNumber')"
            prepend-icon="mdi-card-account-details-outline"
          />
        </v-list>
      </v-card>
    </v-col>

    <v-col v-if="completion" cols="12" md="6" lg="4">
      <v-card>
        <v-card-text>
          <div class="d-flex justify-space-between mb-2">
            <span class="text-subtitle-2">{{ $t('dashboard.profileCompletion') }}</span>
            <span class="text-subtitle-2 font-weight-bold">{{ completion.percentage }}%</span>
          </div>
          <v-progress-linear :model-value="completion.percentage" color="primary" height="10" rounded class="mb-3" />
          <router-link v-if="completion.percentage < 100" :to="{ name: 'profile' }" class="text-body-2">
            {{ $t('profile.title') }} →
          </router-link>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col v-if="auth.user?.employee" cols="12" md="6" lg="4">
      <v-card>
        <v-card-text>
          <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('attendance.myStatusToday') }}</div>
          <AppStatusChip v-if="myAttendance" :status="myAttendance.status" class="mb-3" />
          <div v-else class="text-body-2 mb-3">{{ $t('attendance.noRecordYet') }}</div>
          <div class="d-flex ga-2">
            <v-btn
              color="primary"
              variant="flat"
              size="small"
              :loading="checkingInOut"
              :disabled="Boolean(myAttendance?.check_in)"
              @click="checkIn"
            >
              {{ $t('attendance.checkIn') }}
            </v-btn>
            <v-btn
              color="secondary"
              variant="flat"
              size="small"
              :loading="checkingInOut"
              :disabled="!myAttendance?.check_in || Boolean(myAttendance?.check_out)"
              @click="checkOut"
            >
              {{ $t('attendance.checkOut') }}
            </v-btn>
          </div>
          <router-link :to="{ name: 'attendance' }" class="text-body-2 d-inline-block mt-3">
            {{ $t('nav.attendance') }} →
          </router-link>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col v-if="auth.user?.employee" cols="12" md="6" lg="4">
      <v-card>
        <v-card-text>
          <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('nav.tasks') }}</div>
          <div class="d-flex ga-4 mb-3">
            <div>
              <div class="text-h6 font-weight-bold">{{ myOpenTasksCount }}</div>
              <div class="text-caption text-medium-emphasis">{{ $t('status.in_progress') }}</div>
            </div>
            <div>
              <div class="text-h6 font-weight-bold text-error">{{ myOverdueTasksCount }}</div>
              <div class="text-caption text-medium-emphasis">{{ $t('status.overdue') }}</div>
            </div>
          </div>
          <router-link :to="{ name: 'tasks' }" class="text-body-2 d-inline-block">
            {{ $t('nav.tasks') }} →
          </router-link>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col v-if="auth.user?.employee && latestKpi" cols="12" md="6" lg="4">
      <v-card>
        <v-card-text>
          <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('nav.kpi') }}</div>
          <div class="text-h4 font-weight-bold mb-1">{{ latestKpi.score }}%</div>
          <div class="text-body-2 text-medium-emphasis mb-3">{{ latestKpi.indicator?.name }} · {{ latestKpi.period?.name }}</div>
          <router-link :to="{ name: 'kpi' }" class="text-body-2 d-inline-block">
            {{ $t('nav.kpi') }} →
          </router-link>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col v-if="showAnnouncements && latestAnnouncements.length" cols="12" md="6" lg="4">
      <v-card>
        <v-card-text>
          <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('nav.announcements') }}</div>
          <v-list density="compact" class="pa-0">
            <v-list-item
              v-for="announcement in latestAnnouncements"
              :key="announcement.id"
              :title="announcement.title"
              class="px-0"
              :to="{ name: 'announcement-detail', params: { id: announcement.id } }"
            >
              <template #prepend>
                <v-icon v-if="!announcement.is_read" color="primary" icon="mdi-circle-medium" />
              </template>
            </v-list-item>
          </v-list>
          <router-link :to="{ name: 'announcements' }" class="text-body-2 d-inline-block mt-2">
            {{ $t('nav.announcements') }} →
          </router-link>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col v-if="upcomingTrips.length" cols="12" md="6" lg="4">
      <v-card>
        <v-card-text>
          <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('businessTrips.upcoming') }}</div>
          <v-list density="compact" class="pa-0">
            <v-list-item
              v-for="trip in upcomingTrips"
              :key="trip.id"
              :title="trip.destination"
              :subtitle="`${trip.start_date} — ${trip.end_date}`"
              class="px-0"
            />
          </v-list>
          <router-link :to="{ name: 'business-trips' }" class="text-body-2 d-inline-block mt-2">
            {{ $t('nav.businessTrips') }} →
          </router-link>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>
