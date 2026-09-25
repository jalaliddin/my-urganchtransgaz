<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import type { TaskStatus } from '@/types/models'
import { daysUntil, formatDate } from '@/utils/date'

const props = defineProps<{
  dueDate: string | null
  status: TaskStatus
}>()

const { t } = useI18n()

const isClosed = computed(() => props.status === 'completed' || props.status === 'cancelled')
const days = computed(() => (props.dueDate ? daysUntil(props.dueDate) : null))

// Only open work gets an urgency hint; a finished task's due date is history.
const hint = computed(() => {
  if (days.value === null || isClosed.value) return null
  if (days.value < 0) return { text: t('tasks.overdueBy', { n: -days.value }), color: 'error' }
  if (days.value === 0) return { text: t('tasks.dueToday'), color: 'warning' }
  if (days.value <= 3) return { text: t('tasks.daysLeft', { n: days.value }), color: 'warning' }
  return null
})
</script>

<template>
  <div class="d-flex flex-column">
    <span :class="hint?.color === 'error' ? 'text-error font-weight-medium' : undefined">{{ formatDate(dueDate) }}</span>
    <span v-if="hint" class="text-caption" :class="`text-${hint.color}`">{{ hint.text }}</span>
  </div>
</template>
