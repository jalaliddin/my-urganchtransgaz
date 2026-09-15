import type { App, Component } from 'vue'

/**
 * Registers every component under components/common globally, keyed by
 * filename (AppDataTable.vue -> <AppDataTable>). These are the shared,
 * reusable building blocks (tables, dialogs, headers, etc.) used across
 * nearly every view, so requiring an explicit import everywhere would be
 * pure boilerplate.
 */
export function registerGlobalComponents(app: App): void {
  const modules = import.meta.glob<{ default: Component }>('@/components/common/*.vue', { eager: true })

  for (const path in modules) {
    const name = path.split('/').pop()?.replace('.vue', '')
    if (name) {
      app.component(name, modules[path].default)
    }
  }
}
