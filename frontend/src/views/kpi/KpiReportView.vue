<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { kpiPeriodService, kpiService } from '@/services/kpiService'
import type { KpiPeriod, KpiReportRow } from '@/types/models'

const router = useRouter()

const periods = ref<KpiPeriod[]>([])
const selectedPeriodId = ref<number | null>(null)
const rows = ref<KpiReportRow[]>([])
const loading = ref(false)

async function loadReport() {
  if (!selectedPeriodId.value) return
  loading.value = true
  try {
    rows.value = await kpiService.report(selectedPeriodId.value)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  periods.value = (await kpiPeriodService.list({ per_page: 50 })).data
  selectedPeriodId.value = periods.value[0]?.id ?? null
  if (selectedPeriodId.value) await loadReport()
})

function goBack() {
  router.push({ name: 'kpi' })
}
</script>

<template>
  <AppPageHeader :title="$t('kpi.report')">
    <template #actions>
      <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="goBack">{{ $t('common.close') }}</v-btn>
    </template>
  </AppPageHeader>

  <v-card class="mb-4">
    <v-card-text class="d-flex flex-wrap align-center ga-3">
      <v-select
        v-model="selectedPeriodId"
        :items="periods.map((p) => ({ title: p.name, value: p.id }))"
        :label="$t('kpi.period')"
        style="max-width: 240px"
        hide-details
        @update:model-value="loadReport"
      />
      <v-btn color="primary" variant="flat" :loading="loading" @click="loadReport">{{ $t('common.search') }}</v-btn>
    </v-card-text>
  </v-card>

  <v-card>
    <v-table>
      <thead>
        <tr>
          <th>{{ $t('employees.department') }}</th>
          <th>{{ $t('exams.totalEligible') }}</th>
          <th>{{ $t('kpi.averageScore') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.department_id ?? 'none'">
          <td>{{ row.department_name }}</td>
          <td>{{ row.employees_count }}</td>
          <td class="font-weight-bold">{{ row.average_score }}%</td>
        </tr>
      </tbody>
    </v-table>
    <AppEmptyState v-if="!loading && rows.length === 0" icon="mdi-chart-bar" />
  </v-card>
</template>
