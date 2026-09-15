<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { authService } from '@/services/authService'

const route = useRoute()
const router = useRouter()

const token = computed(() => String(route.query.token ?? ''))
const isRequestMode = computed(() => !token.value)

const email = ref(String(route.query.email ?? ''))
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)
const done = ref(false)
const errorMessage = ref('')

async function submitRequest() {
  submitting.value = true
  errorMessage.value = ''
  try {
    await authService.forgotPassword(email.value)
    done.value = true
  } catch {
    errorMessage.value = ''
  } finally {
    submitting.value = false
  }
}

async function submitReset() {
  submitting.value = true
  errorMessage.value = ''
  try {
    await authService.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    router.push({ name: 'login' })
  } catch {
    errorMessage.value = 'common.genericError'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <v-alert v-if="done" type="success" variant="tonal" class="mb-4">
    {{ $t('common.updatedSuccess') }}
  </v-alert>

  <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
    {{ $t(errorMessage) }}
  </v-alert>

  <v-form v-if="isRequestMode && !done" @submit.prevent="submitRequest">
    <v-text-field v-model="email" label="Email" type="email" required class="mb-2" />
    <v-btn block color="primary" size="large" type="submit" :loading="submitting">
      {{ $t('auth.forgotPassword') }}
    </v-btn>
  </v-form>

  <v-form v-else-if="!isRequestMode" @submit.prevent="submitReset">
    <v-text-field v-model="email" label="Email" type="email" required class="mb-2" />
    <v-text-field
      v-model="password"
      :label="$t('auth.password')"
      type="password"
      required
      class="mb-2"
    />
    <v-text-field
      v-model="passwordConfirmation"
      :label="$t('auth.password')"
      type="password"
      required
      class="mb-2"
    />
    <v-btn block color="primary" size="large" type="submit" :loading="submitting">
      {{ $t('common.save') }}
    </v-btn>
  </v-form>

  <div class="text-center mt-4">
    <router-link :to="{ name: 'login' }" class="text-body-2 text-decoration-none">
      {{ $t('auth.login') }}
    </router-link>
  </div>
</template>
