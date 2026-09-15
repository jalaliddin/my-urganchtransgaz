<script setup lang="ts">
withDefaults(
  defineProps<{
    icon?: string
    message?: string
  }>(),
  {
    icon: 'mdi-database-off-outline',
  },
)
</script>

<template>
  <div class="d-flex flex-column align-center justify-center text-center py-12 text-medium-emphasis">
    <v-icon :icon="icon" size="48" class="mb-3" />
    <span>{{ message ?? $t('common.noData') }}</span>
  </div>
</template>
