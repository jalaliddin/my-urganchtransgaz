<script setup lang="ts">
import { computed, toRef } from 'vue'

import { useAuthenticatedImage } from '@/composables/useAuthenticatedImage'

const props = withDefaults(
  defineProps<{
    photoUrl?: string | null
    name?: string | null
    size?: number | string
    color?: string
  }>(),
  {
    photoUrl: null,
    name: null,
    size: 40,
    color: 'primary',
  },
)

const { objectUrl } = useAuthenticatedImage(toRef(props, 'photoUrl'))

const initial = computed(() => props.name?.trim()?.[0]?.toUpperCase() ?? '?')
</script>

<template>
  <v-avatar :size="size" :color="objectUrl ? undefined : color">
    <v-img v-if="objectUrl" :src="objectUrl" :alt="name ?? ''" cover />
    <span v-else class="text-white">{{ initial }}</span>
  </v-avatar>
</template>
