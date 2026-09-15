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
  terminated: 'error',
  central: 'primary',
  subordinate: 'secondary',
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  present: 'success',
  late: 'warning',
  early_leave: 'warning',
  absent: 'error',
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
