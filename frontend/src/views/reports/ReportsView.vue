<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useAuthStore } from '@/stores/auth'

interface ReportCard {
  title: string
  text: string
  icon: string
  to: string
  permission?: string
}

interface ReportGroup {
  header: string
  cards: ReportCard[]
}

const { t } = useI18n()
const auth = useAuthStore()

const groups = computed<ReportGroup[]>(() =>
  [
    {
      header: t('reports.hub.attendanceGroup'),
      cards: [
        {
          title: t('reports.hub.attendanceReport'),
          text: t('reports.hub.attendanceReportText'),
          icon: 'mdi-calendar-check-outline',
          to: '/attendance?tab=report',
          permission: 'attendance.view',
        },
        {
          title: t('reports.hub.timesheet'),
          text: t('reports.hub.timesheetText'),
          icon: 'mdi-table-large',
          to: '/attendance?tab=timesheet',
          permission: 'attendance.view',
        },
      ],
    },
    {
      header: t('reports.hub.hrGroup'),
      cards: [
        {
          title: t('reports.hub.employeesList'),
          text: t('reports.hub.employeesListText'),
          icon: 'mdi-account-group-outline',
          to: '/employees',
          permission: 'employees.view',
        },
      ],
    },
    {
      header: t('reports.hub.operationsGroup'),
      cards: [
        {
          title: t('reports.hub.kpiReport'),
          text: t('reports.hub.kpiReportText'),
          icon: 'mdi-chart-line',
          to: '/kpi/report',
          permission: 'kpi.view',
        },
        {
          title: t('reports.hub.issuesReport'),
          text: t('reports.hub.issuesReportText'),
          icon: 'mdi-map-search-outline',
          to: '/issues/report',
          permission: 'issues.report',
        },
      ],
    },
    {
      header: t('reports.hub.systemGroup'),
      cards: [
        {
          title: t('reports.hub.auditLog'),
          text: t('reports.hub.auditLogText'),
          icon: 'mdi-history',
          to: '/audit-logs',
          permission: 'audit_logs.view',
        },
      ],
    },
  ]
    .map((group) => ({ ...group, cards: group.cards.filter((card) => !card.permission || auth.can(card.permission)) }))
    .filter((group) => group.cards.length > 0),
)
</script>

<template>
  <AppPageHeader :title="$t('nav.reports')" />

  <div v-for="group in groups" :key="group.header" class="mb-6">
    <div class="text-subtitle-2 text-medium-emphasis mb-3">{{ group.header }}</div>
    <v-row>
      <v-col v-for="card in group.cards" :key="card.to" cols="12" sm="6" md="4">
        <v-card :to="card.to" hover class="h-100">
          <v-card-text class="d-flex flex-column ga-2">
            <v-icon :icon="card.icon" size="28" color="primary" />
            <div class="text-subtitle-1 font-weight-medium">{{ card.title }}</div>
            <p class="text-body-2 text-medium-emphasis mb-0">{{ card.text }}</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>

  <AppEmptyState v-if="!groups.length" icon="mdi-chart-box-outline" />
</template>
