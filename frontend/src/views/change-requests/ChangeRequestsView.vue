<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { changeRequestService } from '@/services/changeRequestService'
import type { EmployeeChangeRequest } from '@/types/models'

const { t } = useI18n()

const { items, total, loading, page, itemsPerPage, reload } =
  usePaginatedResource(changeRequestService.list)

const headers = computed(() => [
  { title: t('documents.employee'), key: 'employee.full_name', sortable: false },
  { title: t('changeRequests.changes'), key: 'changes', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

async function approve(request: EmployeeChangeRequest) {
  await changeRequestService.approve(request.id)
  await reload()
}

const rejectTarget = ref<EmployeeChangeRequest | null>(null)
const rejectReason = ref('')
const rejecting = ref(false)

async function submitReject() {
  if (!rejectTarget.value) return
  rejecting.value = true
  try {
    await changeRequestService.reject(rejectTarget.value.id, rejectReason.value)
    rejectTarget.value = null
    rejectReason.value = ''
    await reload()
  } finally {
    rejecting.value = false
  }
}
</script>

<template>
  <AppPageHeader :title="$t('changeRequests.title')" />

  <AppDataTable
    :headers="headers"
    :items="items"
    :items-length="total"
    :loading="loading"
    :page="page"
    :items-per-page="itemsPerPage"
    @update:page="(v: number) => (page = v)"
    @update:items-per-page="(v: number) => (itemsPerPage = v)"
  >
    <template #item.employee.full_name="{ item }">
      {{ item.employee?.full_name ?? '—' }}
    </template>
    <template #item.changes="{ item }">
      <v-chip v-for="(value, field) in item.changes" :key="field" size="small" class="mr-1 mb-1" variant="tonal">
        {{ field }}: {{ value }}
      </v-chip>
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <template v-if="item.status === 'pending'">
        <v-btn icon="mdi-check" variant="text" size="small" color="success" @click="approve(item)" />
        <v-btn icon="mdi-close" variant="text" size="small" color="error" @click="rejectTarget = item" />
      </template>
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-file-document-edit-outline" />
    </template>
  </AppDataTable>

  <v-dialog :model-value="rejectTarget !== null" max-width="440" @update:model-value="(v) => !v && (rejectTarget = null)">
    <v-card>
      <v-card-title>{{ $t('changeRequests.reject') }}</v-card-title>
      <v-card-text>
        <v-textarea v-model="rejectReason" :label="$t('changeRequests.reviewComment')" rows="3" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="rejectTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="error" variant="flat" :loading="rejecting" @click="submitReject">{{ $t('changeRequests.reject') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
