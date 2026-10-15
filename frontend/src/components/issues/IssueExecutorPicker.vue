<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { issueService } from '@/services/issueService'
import type { IssueExecutorCandidate } from '@/types/models'

const props = withDefaults(
  defineProps<{
    organizationId: number | null
    /** Employees already chosen, kept selectable even if no longer a candidate. */
    current?: { id: number; full_name: string }[]
    errorMessages?: string[]
  }>(),
  { current: () => [], errorMessages: () => [] },
)

const selected = defineModel<number[]>({ required: true })

const candidates = ref<IssueExecutorCandidate[]>([])
const loading = ref(false)

const items = computed(() => {
  const known = new Set(candidates.value.map((candidate) => candidate.id))
  return [
    ...candidates.value,
    ...props.current.filter((employee) => !known.has(employee.id)).map((employee) => ({ ...employee, department: null, position: null })),
  ]
})

// Executors must belong to the issue's organization: switching it drops the
// earlier selection, while the first load keeps whatever was passed in.
watch(
  () => props.organizationId,
  async (organizationId, previous) => {
    if (previous !== undefined) selected.value = []
    candidates.value = []
    if (!organizationId) return

    loading.value = true
    try {
      candidates.value = await issueService.executorCandidates(organizationId)
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)
</script>

<template>
  <v-autocomplete
    v-model="selected"
    :items="items"
    item-title="full_name"
    item-value="id"
    :label="$t('issues.executors')"
    multiple
    chips
    closable-chips
    :loading="loading"
    :disabled="!organizationId"
    :hint="!organizationId ? $t('issues.pickOrganizationFirst') : items.length || loading ? $t('issues.selectExecutors') : $t('issues.noCandidates')"
    persistent-hint
    :error-messages="errorMessages"
  >
    <template #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps" :title="item.full_name" :subtitle="[item.position, item.department].filter(Boolean).join(' · ')" />
    </template>
  </v-autocomplete>
</template>
