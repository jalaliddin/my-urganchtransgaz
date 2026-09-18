<script setup lang="ts">
import logoUrl from '@/assets/logo.svg'
</script>

<template>
  <v-app>
    <v-main class="d-flex align-center justify-center bg-background" style="min-height: 100vh">
      <v-container class="d-flex flex-column align-center justify-center">
        <v-card width="420" class="pa-2" elevation="4">
          <v-card-text>
            <div class="d-flex flex-column align-center mb-6">
              <img :src="logoUrl" alt="" height="64" class="mb-3" />
              <span class="text-subtitle-1 font-weight-bold text-center">{{ $t('app.name') }}</span>
            </div>
            <router-view />
          </v-card-text>
        </v-card>
        <router-link
          :to="{ name: 'privacy-policy' }"
          class="text-caption text-medium-emphasis text-decoration-none d-block text-center mt-4"
        >
          {{ $t('privacyPolicy.title') }}
        </router-link>
      </v-container>
    </v-main>
  </v-app>
</template>
