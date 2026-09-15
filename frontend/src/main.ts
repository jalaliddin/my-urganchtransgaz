import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from '@/App.vue'
import { i18n } from '@/plugins/i18n'
import { registerGlobalComponents } from '@/plugins/globalComponents'
import { vuetify } from '@/plugins/vuetify'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(i18n)
registerGlobalComponents(app)

// A stored token only proves a session existed; the user (roles,
// permissions, employee) must be re-fetched on every fresh page load
// before the router and its permission-gated views render, otherwise a
// direct URL visit or a reload silently looks logged-out-but-not.
async function bootstrap() {
  const auth = useAuthStore()

  if (auth.isAuthenticated) {
    try {
      await auth.fetchCurrentUser()
    } catch {
      auth.clearSession()
    }
  }

  await router.isReady()
  app.mount('#app')
}

bootstrap()
