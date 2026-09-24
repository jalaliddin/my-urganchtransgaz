<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { documentService, documentTypeService } from '@/services/documentService'
import { useAuthStore } from '@/stores/auth'
import type { DocumentType, EmployeeDocument } from '@/types/models'

const { t } = useI18n()
const auth = useAuthStore()
const canReview = computed(() => auth.can('documents.approve'))

const { items, total, loading, page, itemsPerPage, search, reload } =
  usePaginatedResource(documentService.list)

const documentTypes = ref<DocumentType[]>([])
onMounted(async () => {
  documentTypes.value = await documentTypeService.list()
})

/**
 * Mirrors the thresholds `documents:check-expiration` notifies on
 * (30/7/1 days, expired), so the badge a reviewer sees on this list
 * matches what the document's owner was already told about.
 */
function expiryBadge(expiryDate: string | null): { text: string; color: string } | null {
  if (!expiryDate) return null

  const daysLeft = Math.ceil((new Date(expiryDate).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)

  if (daysLeft < 0) return { text: t('documents.expired'), color: 'error' }
  if (daysLeft === 0) return { text: t('documents.expiresToday'), color: 'error' }
  if (daysLeft <= 7) return { text: t('documents.expiresInDays', { days: daysLeft }), color: 'error' }
  if (daysLeft <= 30) return { text: t('documents.expiresInDays', { days: daysLeft }), color: 'warning' }

  return null
}

const headers = computed(() => [
  { title: t('documents.documentTitle'), key: 'title' },
  { title: t('documents.documentType'), key: 'document_type.name', sortable: false },
  ...(canReview.value ? [{ title: t('documents.employee'), key: 'employee.full_name', sortable: false }] : []),
  { title: t('documents.expiryDate'), key: 'expiry_date', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const uploadOpen = ref(false)
const uploadForm = ref({ document_type_id: null as number | null, title: '', file: null as File | null })
const uploadErrors = ref<Record<string, string[]>>({})
const uploading = ref(false)

function openUpload() {
  uploadForm.value = { document_type_id: documentTypes.value[0]?.id ?? null, title: '', file: null }
  uploadErrors.value = {}
  uploadOpen.value = true
}

async function upload() {
  if (!uploadForm.value.document_type_id || !uploadForm.value.file) return
  uploading.value = true
  uploadErrors.value = {}
  try {
    await documentService.upload({
      document_type_id: uploadForm.value.document_type_id,
      title: uploadForm.value.title,
      file: uploadForm.value.file,
    })
    uploadOpen.value = false
    await reload()
  } catch (error: unknown) {
    const axiosError = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    uploadErrors.value = axiosError.response?.data?.errors ?? {}
  } finally {
    uploading.value = false
  }
}

const rejectTarget = ref<EmployeeDocument | null>(null)
const rejectReason = ref('')
const rejecting = ref(false)

async function approve(document: EmployeeDocument) {
  await documentService.approve(document.id)
  await reload()
}

async function submitReject() {
  if (!rejectTarget.value) return
  rejecting.value = true
  try {
    await documentService.reject(rejectTarget.value.id, rejectReason.value)
    rejectTarget.value = null
    rejectReason.value = ''
    await reload()
  } finally {
    rejecting.value = false
  }
}

const deleteTarget = ref<EmployeeDocument | null>(null)
const deleting = ref(false)

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await documentService.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await reload()
  } finally {
    deleting.value = false
  }
}

async function download(document: EmployeeDocument) {
  await documentService.download(document, document.title)
}
</script>

<template>
  <AppPageHeader :title="$t('documents.title')">
    <template #actions>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openUpload">
        {{ $t('documents.upload') }}
      </v-btn>
    </template>
  </AppPageHeader>

  <AppDataTable
    :headers="headers"
    :items="items"
    :items-length="total"
    :loading="loading"
    :page="page"
    :items-per-page="itemsPerPage"
    @update:page="(v: number) => (page = v)"
    @update:items-per-page="(v: number) => (itemsPerPage = v)"
    @update:search="(v: string) => (search = v)"
  >
    <template #item.document_type.name="{ item }">
      {{ item.document_type?.name ?? '—' }}
    </template>
    <template #item.employee.full_name="{ item }">
      {{ item.employee?.full_name ?? '—' }}
    </template>
    <template #item.expiry_date="{ item }">
      <div class="d-flex align-center ga-2">
        <span>{{ item.expiry_date ?? $t('documents.noExpiry') }}</span>
        <v-chip v-if="expiryBadge(item.expiry_date)" :color="expiryBadge(item.expiry_date)!.color" size="small" variant="tonal" label>
          {{ expiryBadge(item.expiry_date)!.text }}
        </v-chip>
      </div>
    </template>
    <template #item.status="{ item }">
      <AppStatusChip :status="item.status" />
    </template>
    <template #item.actions="{ item }">
      <v-btn icon="mdi-download-outline" variant="text" size="small" @click="download(item)" />
      <v-btn
        v-if="canReview && item.status === 'pending'"
        icon="mdi-check"
        variant="text"
        size="small"
        color="success"
        @click="approve(item)"
      />
      <v-btn
        v-if="canReview && item.status === 'pending'"
        icon="mdi-close"
        variant="text"
        size="small"
        color="error"
        @click="rejectTarget = item"
      />
      <v-btn
        v-if="item.status === 'pending' && (auth.can('documents.delete') || item.employee_id === auth.user?.employee?.id)"
        icon="mdi-delete-outline"
        variant="text"
        size="small"
        color="error"
        @click="deleteTarget = item"
      />
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-file-document-outline" />
    </template>
  </AppDataTable>

  <v-dialog v-model="uploadOpen" max-width="480">
    <v-card>
      <v-card-title>{{ $t('documents.upload') }}</v-card-title>
      <v-card-text>
        <v-select
          v-model="uploadForm.document_type_id"
          :items="documentTypes.map((t) => ({ title: t.name, value: t.id }))"
          :label="$t('documents.documentType')"
          :error-messages="uploadErrors.document_type_id"
        />
        <v-text-field
          v-model="uploadForm.title"
          :label="$t('documents.documentTitle')"
          :error-messages="uploadErrors.title"
        />
        <AppFileUpload
          v-model="uploadForm.file"
          accept="application/pdf,image/jpeg,image/png"
          :label="$t('documents.file')"
          :error-messages="uploadErrors.file"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="uploadOpen = false">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="primary" variant="flat" :loading="uploading" @click="upload">{{ $t('common.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog :model-value="rejectTarget !== null" max-width="440" @update:model-value="(v) => !v && (rejectTarget = null)">
    <v-card>
      <v-card-title>{{ $t('documents.reject') }}</v-card-title>
      <v-card-text>
        <v-textarea v-model="rejectReason" :label="$t('documents.rejectReason')" rows="3" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="rejectTarget = null">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="error" variant="flat" :loading="rejecting" @click="submitReject">{{ $t('documents.reject') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <AppConfirmDialog
    :model-value="deleteTarget !== null"
    :loading="deleting"
    @update:model-value="(value: boolean) => !value && (deleteTarget = null)"
    @confirm="confirmDelete"
  />
</template>
