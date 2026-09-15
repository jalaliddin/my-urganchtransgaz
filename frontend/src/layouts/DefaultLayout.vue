<script setup lang="ts">
import { computed, ref } from 'vue'
import { useDisplay } from 'vuetify'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

interface NavItem {
  title: string
  icon: string
  to: string
  permission?: string
}

const auth = useAuthStore()
const router = useRouter()
const { mobile } = useDisplay()

const drawer = ref(!mobile.value)
const rail = ref(false)

const navItems = computed<NavItem[]>(() =>
  [
    { title: 'nav.dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
    {
      title: 'nav.organizations',
      icon: 'mdi-domain',
      to: '/organizations',
      permission: 'organizations.view',
    },
    {
      title: 'nav.departments',
      icon: 'mdi-sitemap-outline',
      to: '/departments',
      permission: 'departments.view',
    },
    {
      title: 'nav.employees',
      icon: 'mdi-account-group-outline',
      to: '/employees',
      permission: 'employees.view',
    },
    {
      title: 'nav.attendance',
      icon: 'mdi-calendar-check-outline',
      to: '/attendance',
      permission: 'attendance.view',
    },
    {
      title: 'nav.documents',
      icon: 'mdi-file-document-outline',
      to: '/documents',
    },
    {
      title: 'nav.changeRequests',
      icon: 'mdi-file-document-edit-outline',
      to: '/change-requests',
      permission: 'employees.update',
    },
  ].filter((item) => !item.permission || auth.can(item.permission)),
)

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-app>
    <v-navigation-drawer
      v-model="drawer"
      :rail="rail && !mobile"
      :temporary="mobile"
      color="sidebar"
      theme="dark"
      permanent
      width="260"
    >
      <div class="d-flex align-center pa-4 ga-2">
        <v-avatar color="sidebar-active" size="36">
          <span class="text-body-1 font-weight-bold">UTG</span>
        </v-avatar>
        <span v-if="!rail" class="text-subtitle-1 font-weight-bold text-white text-truncate">
          {{ $t('app.name') }}
        </span>
      </div>

      <v-divider opacity="0.1" />

      <v-list nav density="comfortable">
        <v-list-item
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :prepend-icon="item.icon"
          :title="$t(item.title)"
          rounded="lg"
          active-color="white"
          base-color="rgba(255,255,255,0.72)"
        />
      </v-list>

      <template #append>
        <v-list nav density="comfortable">
          <v-list-item
            prepend-icon="mdi-logout"
            :title="$t('nav.logout')"
            rounded="lg"
            base-color="rgba(255,255,255,0.72)"
            @click="handleLogout"
          />
        </v-list>
      </template>
    </v-navigation-drawer>

    <v-app-bar color="surface" flat border>
      <v-app-bar-nav-icon
        @click="mobile ? (drawer = !drawer) : (rail = !rail)"
      />
      <v-spacer />
      <NotificationBell />
      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" variant="text" class="ml-2">
            <AppAvatar :photo-url="auth.user?.employee?.photo_url" :name="auth.user?.name" :size="32" class="mr-2" />
            <span class="text-body-2 d-none d-sm-inline">{{ auth.user?.name }}</span>
          </v-btn>
        </template>
        <v-list density="comfortable" min-width="220">
          <v-list-item :title="auth.user?.name" :subtitle="auth.user?.email" />
          <v-divider />
          <v-list-item :to="'/profile'" :title="$t('nav.myProfile')" prepend-icon="mdi-account-outline" />
          <v-list-item :to="'/documents'" :title="$t('nav.myDocuments')" prepend-icon="mdi-file-document-outline" />
          <v-divider />
          <v-list-item :title="$t('nav.logout')" prepend-icon="mdi-logout" @click="handleLogout" />
        </v-list>
      </v-menu>
    </v-app-bar>

    <v-main>
      <v-container fluid class="pa-4 pa-md-6">
        <router-view />
      </v-container>
    </v-main>
  </v-app>
</template>
