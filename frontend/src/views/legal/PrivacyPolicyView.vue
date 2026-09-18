<script setup lang="ts">
import logoUrl from '@/assets/logo.svg'

const sections = ['dataCollected', 'location', 'storage', 'access', 'retention', 'contact'] as const
</script>

<template>
  <v-app>
    <v-main class="bg-background">
      <v-container class="py-10" style="max-width: 760px">
        <div class="d-flex align-center ga-3 mb-6">
          <img :src="logoUrl" alt="" height="40" />
          <span class="text-subtitle-1 font-weight-bold">{{ $t('app.name') }}</span>
        </div>

        <v-card class="pa-6">
          <h1 class="text-h5 font-weight-bold mb-1">{{ $t('privacyPolicy.title') }}</h1>
          <p class="text-body-2 text-medium-emphasis mb-6">{{ $t('privacyPolicy.updated') }}</p>

          <p class="text-body-1 mb-6">{{ $t('privacyPolicy.intro') }}</p>

          <template v-for="section in sections" :key="section">
            <h2 class="text-subtitle-1 font-weight-bold mt-4 mb-1">
              {{ $t(`privacyPolicy.${section}Title`) }}
            </h2>
            <p class="text-body-1 mb-2">{{ $t(`privacyPolicy.${section}`) }}</p>
          </template>
        </v-card>
      </v-container>
    </v-main>
  </v-app>
</template>
