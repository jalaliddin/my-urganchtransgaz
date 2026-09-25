<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  status: string
}>()

const { t } = useI18n()

const colorMap: Record<string, string> = {
  active: 'success',
  inactive: 'default',
  vacation: 'info',
  business_trip: 'info',
  sick_leave: 'warning',
  excused: 'secondary',
  terminated: 'error',
  central: 'primary',
  subordinate: 'secondary',
  pending: 'warning',
  department_approved: 'info',
  approved: 'success',
  rejected: 'error',
  scheduled: 'info',
  present: 'success',
  late: 'warning',
  early_leave: 'warning',
  absent: 'error',
  new: 'info',
  in_progress: 'primary',
  waiting: 'warning',
  completed: 'success',
  cancelled: 'default',
  overdue: 'error',
  low: 'default',
  normal: 'info',
  high: 'warning',
  urgent: 'error',
  draft: 'default',
  closed: 'default',
  published: 'success',
  archived: 'default',
  passed: 'success',
  failed: 'error',
  not_taken: 'default',
  open: 'error',
  resolved: 'success',
}

const color = computed(() => colorMap[props.status] ?? 'default')
const label = computed(() => {
  const key = `status.${props.status}`
  const translated = t(key)
  return translated === key ? props.status : translated
})
</script>

<template>
  <v-chip :color="color" size="small" variant="tonal" label>{{ label }}</v-chip>
</template>
