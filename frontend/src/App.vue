<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

import { registerUnauthorizedHandler } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

onMounted(() => {
  registerUnauthorizedHandler(() => {
    if (auth.isAuthenticated) {
      auth.clearSession()
      router.push({ name: 'login' })
    }
  })
})
</script>

<template>
  <router-view />
</template>
