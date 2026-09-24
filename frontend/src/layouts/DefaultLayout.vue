<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'
import { useRouter } from 'vue-router'

import { searchService } from '@/services/settingsService'
import { useAuthStore } from '@/stores/auth'
import type { SearchGroup } from '@/types/models'
import logoMarkUrl from '@/assets/logo-mark.png'

interface NavItem {
  title: string
  icon: string
  to: string
  permission?: string
}

interface NavGroup {
  header: string | null
  items: NavItem[]
}

const auth = useAuthStore()
const router = useRouter()
const { mobile } = useDisplay()

const drawer = ref(!mobile.value)
const rail = ref(false)

// `mobile` from useDisplay() is itself reactive to a resize, but a plain
// `ref(!mobile.value)` only reads it once at setup — crossing the
// breakpoint afterward (an actual window resize, not just a page load at
// a given width) left the drawer's own state stale: still open and
// full-width on top of newly-narrow content, or stuck closed after
// widening back out. Watching keeps it in sync either direction.
watch(mobile, (isMobile) => {
  drawer.value = !isMobile
})

const allNavGroups: NavGroup[] = [
  {
    header: null,
    items: [
      { title: 'nav.dashboard', icon: 'mdi-view-dashboard-outline', to: '/dashboard' },
    ],
  },
  {
    header: 'nav.groupOrganization',
    items: [
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
        title: 'nav.positions',
        icon: 'mdi-badge-account-outline',
        to: '/positions',
        permission: 'positions.view',
      },
    ],
  },
  {
    header: 'nav.groupHr',
    items: [
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
    ],
  },
  {
    header: 'nav.groupOperations',
    items: [
      {
        title: 'nav.tasks',
        icon: 'mdi-clipboard-check-multiple-outline',
        to: '/tasks',
        permission: 'tasks.view',
      },
      {
        title: 'nav.calendar',
        icon: 'mdi-calendar-month-outline',
        to: '/calendar',
      },
      {
        title: 'nav.exams',
        icon: 'mdi-school-outline',
        to: '/exams',
        permission: 'exams.view',
      },
      {
        title: 'nav.kpi',
        icon: 'mdi-chart-line',
        to: '/kpi',
        permission: 'kpi.view',
      },
      {
        title: 'nav.announcements',
        icon: 'mdi-bullhorn-outline',
        to: '/announcements',
        permission: 'announcements.view',
      },
      {
        title: 'nav.issues',
        icon: 'mdi-map-marker-alert-outline',
        to: '/issues',
        permission: 'issues.view',
      },
      {
        title: 'nav.issueCategories',
        icon: 'mdi-tag-multiple-outline',
        to: '/issue-categories',
        permission: 'issue_categories.manage',
      },
    ],
  },
  {
    header: 'nav.groupReports',
    items: [
      { title: 'nav.reports', icon: 'mdi-chart-box-outline', to: '/reports' },
    ],
  },
  {
    header: 'nav.groupSystem',
    items: [
      {
        title: 'nav.auditLogs',
        icon: 'mdi-history',
        to: '/audit-logs',
        permission: 'audit_logs.view',
      },
      {
        title: 'nav.settings',
        icon: 'mdi-cog-outline',
        to: '/settings',
        permission: 'settings.manage',
      },
    ],
  },
]

const navGroups = computed<NavGroup[]>(() =>
  allNavGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.permission || auth.can(item.permission)),
    }))
    .filter((group) => group.items.length > 0),
)

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

// ---- Global search ----
const searchQuery = ref('')
const searchResults = ref<SearchGroup[]>([])
const searchMenuOpen = ref(false)
const searching = ref(false)
let searchDebounce: ReturnType<typeof setTimeout> | undefined

const routeByType: Record<SearchGroup['type'], string> = {
  employees: 'employees',
  organizations: 'organizations',
  departments: 'departments',
  tasks: 'tasks',
  announcements: 'announcements',
  documents: 'documents',
}

watch(searchQuery, (value) => {
  clearTimeout(searchDebounce)

  if (value.trim().length < 2) {
    searchResults.value = []
    searchMenuOpen.value = false
    return
  }

  searchDebounce = setTimeout(async () => {
    searching.value = true
    try {
      searchResults.value = await searchService.search(value.trim())
      searchMenuOpen.value = true
    } finally {
      searching.value = false
    }
  }, 350)
})

function goToResult(type: SearchGroup['type']) {
  searchMenuOpen.value = false
  searchQuery.value = ''
  router.push({ name: routeByType[type] })
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
          <img :src="logoMarkUrl" alt="" width="24" height="24" />
        </v-avatar>
        <span v-if="!rail" class="text-subtitle-1 font-weight-bold text-white text-truncate">
          {{ $t('app.name') }}
        </span>
      </div>

      <v-divider opacity="0.1" />

      <v-list nav density="comfortable">
        <template v-for="group in navGroups" :key="group.header ?? 'main'">
          <v-list-subheader v-if="group.header && !rail" class="text-uppercase text-caption" style="opacity: 0.55; letter-spacing: 0.04em">
            {{ $t(group.header) }}
          </v-list-subheader>
          <v-list-item
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            :prepend-icon="item.icon"
            :title="$t(item.title)"
            rounded="lg"
            color="sidebar-active"
            active-color="white"
            base-color="rgba(255,255,255,0.72)"
          />
        </template>
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

      <v-menu v-model="searchMenuOpen" :close-on-content-click="false" location="bottom start" min-width="360">
        <template #activator="{ props: menuProps }">
          <v-text-field
            v-bind="menuProps"
            v-model="searchQuery"
            :placeholder="$t('search.placeholder')"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="solo-filled"
            flat
            hide-details
            single-line
            :loading="searching"
            class="ml-2 d-none d-sm-block"
            style="max-width: 360px"
          />
        </template>
        <v-list v-if="searchResults.length">
          <template v-for="group in searchResults" :key="group.type">
            <v-list-subheader>{{ $t(`search.types.${group.type}`) }}</v-list-subheader>
            <v-list-item
              v-for="result in group.results"
              :key="result.id"
              :title="result.title"
              :subtitle="result.subtitle ?? undefined"
              @click="goToResult(group.type)"
            />
          </template>
        </v-list>
        <v-card v-else>
          <v-card-text class="text-body-2 text-medium-emphasis">{{ $t('search.noResults') }}</v-card-text>
        </v-card>
      </v-menu>

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
