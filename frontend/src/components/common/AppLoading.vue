<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 40 })
</script>

<template>
  <div class="d-flex justify-center align-center py-12">
    <v-progress-circular :size="size" color="primary" indeterminate />
  </div>
</template>
