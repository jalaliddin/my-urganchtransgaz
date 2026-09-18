<script setup lang="ts">
import logoUrl from '@/assets/logo.svg'
</script>

<template>
  <v-app>
    <v-main class="auth-main">
      <div class="auth-shell">
        <div class="auth-brand d-none d-md-flex flex-column justify-space-between">
          <div class="d-flex align-center">
            <span class="auth-brand-logo-badge mr-3">
              <img :src="logoUrl" alt="" height="26" />
            </span>
            <span class="text-subtitle-1 font-weight-bold text-white">{{ $t('app.name') }}</span>
          </div>

          <div>
            <h1 class="text-h4 font-weight-bold text-white mb-3">{{ $t('app.tagline') }}</h1>
            <p class="text-body-1 auth-brand-subtitle">{{ $t('app.taglineSubtitle') }}</p>
          </div>

          <span class="text-caption auth-brand-subtitle">{{ $t('app.credit') }}</span>
        </div>

        <div class="auth-form-panel d-flex flex-column align-center justify-center">
          <v-card width="420" max-width="100%" class="pa-2" elevation="0" border>
            <v-card-text>
              <div class="d-flex flex-column align-center mb-6 d-md-none">
                <img :src="logoUrl" alt="" height="56" class="mb-3" />
                <span class="text-subtitle-1 font-weight-bold text-center">{{ $t('app.name') }}</span>
              </div>
              <router-view />
            </v-card-text>
          </v-card>

          <div class="text-center mt-6">
            <router-link
              :to="{ name: 'privacy-policy' }"
              class="text-caption text-medium-emphasis text-decoration-none"
            >
              {{ $t('privacyPolicy.title') }}
            </router-link>
            <div class="text-caption text-medium-emphasis mt-2 d-md-none">{{ $t('app.credit') }}</div>
          </div>
        </div>
      </div>
    </v-main>
  </v-app>
</template>

<style scoped>
.auth-main {
  min-height: 100vh;
}

.auth-shell {
  min-height: 100vh;
  display: flex;
}

.auth-brand {
  flex: 1 1 45%;
  padding: 56px;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #1e3a5f 0%, #3b6ea5 100%);
}

.auth-brand::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 85% 15%, rgba(154, 204, 72, 0.28), transparent 55%);
  pointer-events: none;
}

.auth-brand > * {
  position: relative;
}

.auth-brand-logo-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
}

.auth-brand-subtitle {
  color: rgba(255, 255, 255, 0.82);
}

.auth-form-panel {
  flex: 1 1 55%;
  padding: 24px;
}

@media (max-width: 959px) {
  .auth-shell {
    flex-direction: column;
  }
}
</style>
