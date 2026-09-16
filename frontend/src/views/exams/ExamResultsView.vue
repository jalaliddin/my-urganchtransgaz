<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { examService } from '@/services/examService'
import type { Exam, ExamResultRow } from '@/types/models'

const route = useRoute()
const router = useRouter()

const examId = Number(route.params.id)
const exam = ref<Exam | null>(null)
const rows = ref<ExamResultRow[]>([])
const stats = ref({ total_eligible: 0, passed: 0, failed: 0, not_taken: 0 })
const loading = ref(true)

onMounted(async () => {
  loading.value = true
  try {
    exam.value = await examService.get(examId)
    const result = await examService.results(examId, { per_page: 50 })
    rows.value = result.rows
    stats.value = result.stats
  } finally {
    loading.value = false
  }
})

function goBack() {
  router.push({ name: 'exams' })
}
</script>

<template>
  <AppPageHeader :title="`${exam?.title ?? ''} — ${$t('exams.results')}`">
    <template #actions>
      <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="goBack">{{ $t('common.close') }}</v-btn>
    </template>
  </AppPageHeader>

  <AppLoading v-if="loading" />

  <template v-else>
    <v-row class="mb-4">
      <v-col cols="6" sm="3">
        <v-card><v-card-text class="text-center">
          <div class="text-h5 font-weight-bold">{{ stats.total_eligible }}</div>
          <div class="text-caption text-medium-emphasis">{{ $t('exams.totalEligible') }}</div>
        </v-card-text></v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card><v-card-text class="text-center">
          <div class="text-h5 font-weight-bold text-success">{{ stats.passed }}</div>
          <div class="text-caption text-medium-emphasis">{{ $t('status.passed') }}</div>
        </v-card-text></v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card><v-card-text class="text-center">
          <div class="text-h5 font-weight-bold text-error">{{ stats.failed }}</div>
          <div class="text-caption text-medium-emphasis">{{ $t('status.failed') }}</div>
        </v-card-text></v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card><v-card-text class="text-center">
          <div class="text-h5 font-weight-bold">{{ stats.not_taken }}</div>
          <div class="text-caption text-medium-emphasis">{{ $t('exams.notTaken') }}</div>
        </v-card-text></v-card>
      </v-col>
    </v-row>

    <v-card v-if="stats.total_eligible" class="mb-4">
      <v-card-text>
        <AppChart
          type="pie"
          :labels="[$t('status.passed'), $t('status.failed'), $t('exams.notTaken')]"
          :data="[stats.passed, stats.failed, stats.not_taken]"
        />
      </v-card-text>
    </v-card>

    <v-card>
      <v-table>
        <thead>
          <tr>
            <th>{{ $t('employees.fullName') }}</th>
            <th>{{ $t('employees.employeeNumber') }}</th>
            <th>{{ $t('exams.attemptsUsed') }}</th>
            <th>{{ $t('exams.bestScore') }}</th>
            <th>{{ $t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.employee_id">
            <td>{{ row.full_name }}</td>
            <td>{{ row.employee_number }}</td>
            <td>{{ row.attempts_used }}</td>
            <td>{{ row.best_percentage ?? '—' }}{{ row.best_percentage !== null ? '%' : '' }}</td>
            <td><AppStatusChip :status="row.status" /></td>
          </tr>
        </tbody>
      </v-table>
      <AppEmptyState v-if="rows.length === 0" icon="mdi-account-group-outline" />
    </v-card>
  </template>
</template>
