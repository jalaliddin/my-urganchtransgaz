<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { attendanceService } from '@/services/attendanceService'
import { documentService } from '@/services/documentService'
import { employeeService } from '@/services/employeeService'
import { taskService } from '@/services/taskService'
import type { AttendanceRecord, Employee, EmployeeDocument, Task } from '@/types/models'

const route = useRoute()
const router = useRouter()

const employeeId = Number(route.params.id)
const employee = ref<Employee | null>(null)
const attendance = ref<AttendanceRecord[]>([])
const documents = ref<EmployeeDocument[]>([])
const tasks = ref<Task[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const [employeeResult, attendanceResult, documentsResult, tasksResult] = await Promise.all([
      employeeService.get(employeeId),
      attendanceService.list({ 'filter[employee_id]': employeeId, per_page: 30, sort: '-date' }),
      documentService.list({ 'filter[employee_id]': employeeId, per_page: 50 }),
      taskService.list({ 'filter[assignee_id]': employeeId, per_page: 50 }),
    ])
    employee.value = employeeResult
    attendance.value = attendanceResult.data
    documents.value = documentsResult.data
    tasks.value = tasksResult.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

function goBack() {
  router.push({ name: 'employees' })
}

function timeOnly(value: string | null): string {
  return value ? value.slice(11, 16) : '—'
}

async function downloadDocument(document: EmployeeDocument) {
  await documentService.download(document, document.title)
}
</script>

<template>
  <AppLoading v-if="loading" />

  <template v-else-if="employee">
    <AppPageHeader :title="employee.full_name">
      <template #actions>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="goBack">{{ $t('common.close') }}</v-btn>
      </template>
    </AppPageHeader>

    <v-row>
      <v-col cols="12" md="4">
        <v-card class="mb-4">
          <v-card-text class="text-center pt-6">
            <v-avatar size="88" color="primary" class="mb-3">
              <v-img v-if="employee.photo_url" :src="employee.photo_url" :alt="employee.full_name" />
              <span v-else class="text-h5">{{ employee.first_name?.[0] }}{{ employee.last_name?.[0] }}</span>
            </v-avatar>
            <div class="text-h6">{{ employee.full_name }}</div>
            <div class="text-body-2 text-medium-emphasis mb-2">{{ employee.position?.title ?? '—' }}</div>
            <AppStatusChip :status="employee.status" />
          </v-card-text>
          <v-divider />
          <v-list density="compact">
            <v-list-item :title="employee.employee_number" :subtitle="$t('employees.employeeNumber')" prepend-icon="mdi-badge-account-outline" />
            <v-list-item :title="employee.organization?.name ?? '—'" :subtitle="$t('employees.organization')" prepend-icon="mdi-domain" />
            <v-list-item :title="employee.department?.name ?? '—'" :subtitle="$t('employees.department')" prepend-icon="mdi-sitemap-outline" />
            <v-list-item :title="employee.phone ?? '—'" :subtitle="$t('employees.phone')" prepend-icon="mdi-phone-outline" />
            <v-list-item :title="employee.corporate_email ?? employee.email ?? '—'" :subtitle="$t('employees.email')" prepend-icon="mdi-email-outline" />
            <v-list-item :title="employee.hire_date ?? '—'" :subtitle="$t('employees.hireDate')" prepend-icon="mdi-calendar-outline" />
          </v-list>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">{{ $t('nav.attendance') }}</v-card-title>
          <v-card-text>
            <v-table density="compact">
              <thead>
                <tr>
                  <th>{{ $t('attendance.date') }}</th>
                  <th>{{ $t('attendance.checkIn') }}</th>
                  <th>{{ $t('attendance.checkOut') }}</th>
                  <th>{{ $t('employees.visitsCount') }}</th>
                  <th>{{ $t('common.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="record in attendance" :key="record.id">
                  <td>{{ record.date }}</td>
                  <td>{{ timeOnly(record.check_in) }}</td>
                  <td>{{ timeOnly(record.check_out) }}</td>
                  <td>{{ record.visits_count ?? '—' }}</td>
                  <td><AppStatusChip :status="record.status" /></td>
                </tr>
              </tbody>
            </v-table>
            <AppEmptyState v-if="!attendance.length" icon="mdi-calendar-blank-outline" />
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1">{{ $t('nav.documents') }}</v-card-title>
          <v-list density="compact">
            <v-list-item v-for="document in documents" :key="document.id" :title="document.title" :subtitle="document.document_type?.name">
              <template #append>
                <AppStatusChip :status="document.status" class="mr-2" />
                <v-btn icon="mdi-download-outline" variant="text" size="small" @click="downloadDocument(document)" />
              </template>
            </v-list-item>
          </v-list>
          <AppEmptyState v-if="!documents.length" icon="mdi-file-document-outline" />
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1">{{ $t('nav.tasks') }}</v-card-title>
          <v-list density="compact">
            <v-list-item
              v-for="task in tasks"
              :key="task.id"
              :title="task.title"
              :subtitle="task.due_date ? `${$t('tasks.dueDate')}: ${task.due_date}` : undefined"
              :to="{ name: 'task-detail', params: { id: task.id } }"
            >
              <template #append>
                <AppStatusChip :status="task.status" />
              </template>
            </v-list-item>
          </v-list>
          <AppEmptyState v-if="!tasks.length" icon="mdi-clipboard-text-outline" />
        </v-card>
      </v-col>
    </v-row>
  </template>
</template>
