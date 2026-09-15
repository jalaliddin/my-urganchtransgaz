<template>
  <v-app>
    <v-main class="d-flex align-center justify-center bg-background" style="min-height: 100vh">
      <v-container class="d-flex align-center justify-center">
        <v-card width="420" class="pa-2" elevation="4">
          <v-card-text>
            <div class="d-flex flex-column align-center mb-6">
              <v-avatar color="primary" size="56" class="mb-3">
                <span class="text-h6 font-weight-bold text-white">UTG</span>
              </v-avatar>
              <span class="text-subtitle-1 font-weight-bold text-center">{{ $t('app.name') }}</span>
            </div>
            <router-view />
          </v-card-text>
        </v-card>
      </v-container>
    </v-main>
  </v-app>
</template>
