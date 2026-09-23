import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

import { createVuetify } from 'vuetify'

/**
 * "sidebar" isn't a Vuetify semantic color, so it's defined here as a theme
 * extension and referenced directly (bg-sidebar / text-sidebar) wherever the
 * dark-navy navigation drawer needs it — the content area stays on the
 * standard light theme per spec (light content, dark-navy sidebar).
 */
export const vuetify = createVuetify({
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#1E3A5F',
          secondary: '#3B6EA5',
          background: '#F4F6F9',
          surface: '#FFFFFF',
          sidebar: '#111C33',
          'sidebar-active': '#1E3A5F',
          // The corporate flame mark's leaf green (matches the mobile app's
          // `AppTheme._brandGreen`) — a genuine brand accent, kept separate
          // from Vuetify's own duller Material "success" green so it reads
          // as *the* brand color when used deliberately (a highlight, the
          // dashboard hero's accent chip), not diluted into every success state.
          'brand-accent': '#9ACC48',
          success: '#2E7D32',
          warning: '#ED6C02',
          error: '#D32F2F',
          info: '#0288D1',
        },
      },
      dark: {
        dark: true,
        colors: {
          primary: '#3B6EA5',
          secondary: '#1E3A5F',
          background: '#101418',
          surface: '#181C22',
          sidebar: '#0B1220',
          'sidebar-active': '#1E3A5F',
          'brand-accent': '#9ACC48',
          success: '#4CAF50',
          warning: '#FFA726',
          error: '#EF5350',
          info: '#29B6F6',
        },
      },
    },
  },
  defaults: {
    // A thin border reads calmer than a drop shadow repeated under every
    // card on the page — the shadow is reserved for the few surfaces that
    // should visibly float (menus, dialogs), not the whole page's content.
    VCard: { rounded: 'lg', elevation: 0, border: true },
    VBtn: { rounded: 'lg' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VTextarea: { variant: 'outlined', density: 'comfortable' },
  },
})
