<script setup lang="ts">
import type { TaskCategory } from '@/types/models'

defineProps<{
  category: Pick<TaskCategory, 'name' | 'color'> | null | undefined
}>()
</script>

<template>
  <v-chip v-if="category" size="small" variant="tonal" label :color="category.color">
    <span class="task-category-dot mr-1" :style="{ background: category.color }" />
    {{ category.name }}
  </v-chip>
  <span v-else class="text-medium-emphasis">—</span>
</template>

<style scoped>
.task-category-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
</style>
