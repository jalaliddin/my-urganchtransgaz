<script setup lang="ts">
import { toRef } from 'vue'

import { useAuthenticatedImage } from '@/composables/useAuthenticatedImage'

const props = defineProps<{ url: string }>()
const emit = defineEmits<{ open: [src: string] }>()

// The photo endpoint needs the Bearer token, so it's fetched as a blob.
const { objectUrl, loading } = useAuthenticatedImage(toRef(props, 'url'))
</script>

<template>
  <v-card
    class="issue-photo"
    variant="outlined"
    :disabled="!objectUrl"
    :aria-label="$t('issues.openPhoto')"
    @click="objectUrl && emit('open', objectUrl)"
  >
    <v-img v-if="objectUrl" :src="objectUrl" cover height="110" width="140" />
    <div v-else class="d-flex align-center justify-center" style="height: 110px; width: 140px">
      <v-progress-circular v-if="loading" indeterminate size="24" />
      <v-icon v-else icon="mdi-image-broken-variant" />
    </div>
  </v-card>
</template>
