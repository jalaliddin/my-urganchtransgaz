<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { isAxiosError } from 'axios'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const login = ref('')
const password = ref('')
const showPassword = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const submitting = ref(false)

async function handleSubmit() {
  hasError.value = false
  submitting.value = true

  try {
    await auth.login({ login: login.value, password: password.value })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    router.push(redirect)
  } catch (error) {
    hasError.value = true
    errorMessage.value = isAxiosError(error) ? (error.response?.data?.message ?? '') : ''
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <v-form @submit.prevent="handleSubmit">
    <v-alert v-if="hasError" type="error" variant="tonal" density="comfortable" class="mb-4">
      {{ errorMessage || $t('auth.loginError') }}
    </v-alert>

    <v-text-field
      v-model="login"
      :label="$t('auth.loginField')"
      prepend-inner-icon="mdi-account-outline"
      autofocus
      required
      class="mb-2"
    />

    <v-text-field
      v-model="password"
      :label="$t('auth.password')"
      prepend-inner-icon="mdi-lock-outline"
      :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
      :type="showPassword ? 'text' : 'password'"
      required
      class="mb-2"
      @click:append-inner="showPassword = !showPassword"
    />

    <v-btn block color="primary" size="large" type="submit" :loading="submitting" class="mt-2">
      {{ $t('auth.loginButton') }}
    </v-btn>

    <div class="text-center mt-4">
      <router-link :to="{ name: 'reset-password' }" class="text-body-2 text-decoration-none">
        {{ $t('auth.forgotPassword') }}
      </router-link>
    </div>
  </v-form>
</template>
