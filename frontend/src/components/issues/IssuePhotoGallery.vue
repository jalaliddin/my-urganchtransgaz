<script setup lang="ts">
import { ref } from 'vue'

import IssuePhotoThumb from '@/components/issues/IssuePhotoThumb.vue'
import type { IssuePhoto } from '@/types/models'

defineProps<{ photos: IssuePhoto[] }>()

const opened = ref<string | null>(null)
</script>

<template>
  <div class="d-flex flex-wrap ga-2">
    <IssuePhotoThumb v-for="photo in photos" :key="photo.id" :url="photo.url" @open="(src) => (opened = src)" />
  </div>
  <v-dialog :model-value="opened !== null" max-width="1000" @update:model-value="opened = null">
    <v-card>
      <v-img v-if="opened" :src="opened" max-height="85vh" contain />
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="opened = null">{{ $t('common.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
