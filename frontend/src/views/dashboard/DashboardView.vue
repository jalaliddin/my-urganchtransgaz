<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { profileService, type ProfileCompletion } from '@/services/profileService'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const completion = ref<ProfileCompletion | null>(null)

onMounted(async () => {
  if (auth.user?.employee) {
    completion.value = await profileService.completion()
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
  </v-row>
</template>
