<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <AppPageHeader :title="$t('dashboard.title')" />

  <v-row>
    <v-col cols="12" md="6" lg="4">
      <v-card>
        <v-card-item>
          <div class="d-flex align-center ga-4">
            <v-avatar color="primary" size="56">
              <span class="text-h6 text-white">{{ auth.user?.name?.[0] ?? '?' }}</span>
            </v-avatar>
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
  </v-row>
</template>
