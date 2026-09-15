<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { attendanceService } from '@/services/attendanceService'
import { profileService, type ProfileCompletion } from '@/services/profileService'
import { useAuthStore } from '@/stores/auth'
import type { TodayAttendance } from '@/types/models'

const auth = useAuthStore()
const completion = ref<ProfileCompletion | null>(null)

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

onMounted(async () => {
  if (auth.user?.employee) {
    completion.value = await profileService.completion()
    await loadToday()
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
  </v-row>
</template>
