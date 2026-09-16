<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { usePaginatedResource } from '@/composables/usePaginatedResource'
import { auditLogService } from '@/services/settingsService'
import type { ListParams, PaginatedResult } from '@/types/api'
import type { AuditLog } from '@/types/models'

const { t } = useI18n()

const filters = reactive({ module: '', action: '', from: '', to: '' })

async function fetchLogs(params: ListParams): Promise<PaginatedResult<AuditLog>> {
  const result = await auditLogService.list({
    ...params,
    module: filters.module || undefined,
    action: filters.action || undefined,
    from: filters.from || undefined,
    to: filters.to || undefined,
  })
  return { data: result.data, meta: { total: result.total, current_page: 1, last_page: 1, per_page: result.data.length } }
}

const { items, total, loading, page, itemsPerPage, reload } = usePaginatedResource(fetchLogs)

watch(filters, () => {
  page.value = 1
  reload()
})

const headers = computed(() => [
  { title: t('auditLogs.date'), key: 'created_at', sortable: false },
  { title: t('auditLogs.user'), key: 'user_name', sortable: false },
  { title: t('auditLogs.action'), key: 'action', sortable: false },
  { title: t('auditLogs.module'), key: 'module', sortable: false },
  { title: t('auditLogs.entity'), key: 'entity', sortable: false },
])

async function exportAs(format: 'csv' | 'xlsx' | 'pdf') {
  await auditLogService.export(format, {
    module: filters.module || undefined,
    action: filters.action || undefined,
    from: filters.from || undefined,
    to: filters.to || undefined,
  })
}
</script>

<template>
  <AppPageHeader :title="$t('nav.auditLogs')">
    <template #actions>
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="tonal" prepend-icon="mdi-download">{{ $t('export.export') }}</v-btn>
        </template>
        <v-list>
          <v-list-item title="CSV" @click="exportAs('csv')" />
          <v-list-item title="Excel" @click="exportAs('xlsx')" />
          <v-list-item title="PDF" @click="exportAs('pdf')" />
        </v-list>
      </v-menu>
    </template>
  </AppPageHeader>

  <v-card class="mb-4">
    <v-card-text class="d-flex flex-wrap ga-3">
      <v-text-field v-model="filters.module" :label="$t('auditLogs.module')" density="compact" hide-details style="max-width: 180px" />
      <v-text-field v-model="filters.action" :label="$t('auditLogs.action')" density="compact" hide-details style="max-width: 180px" />
      <v-text-field v-model="filters.from" type="date" :label="$t('attendance.from')" density="compact" hide-details style="max-width: 180px" />
      <v-text-field v-model="filters.to" type="date" :label="$t('attendance.to')" density="compact" hide-details style="max-width: 180px" />
    </v-card-text>
  </v-card>

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
    <template #item.action="{ item }">
      <AppStatusChip :status="item.action" />
    </template>
    <template #item.entity="{ item }">
      {{ item.entity_type ? `${item.entity_type.split('\\').pop()} #${item.entity_id}` : '—' }}
    </template>
    <template #empty>
      <AppEmptyState icon="mdi-history" />
    </template>
  </AppDataTable>
</template>
