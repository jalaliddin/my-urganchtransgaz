import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { authService, type LoginPayload } from '@/services/authService'
import { getStoredToken, setStoredToken } from '@/services/http'
import type { AuthUser } from '@/types/models'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(getStoredToken())
  const isLoading = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))

  /**
   * super-admin bypasses every permission check on the backend (Gate::before),
   * so it never shows up in `permissions` — the frontend has to know about
   * the same bypass to keep admin-only UI visible to that role.
   */
  function can(permission: string): boolean {
    if (!user.value) return false
    if (user.value.roles.includes('super-admin')) return true
    return user.value.permissions.includes(permission)
  }

  function hasRole(role: string): boolean {
    return user.value?.roles.includes(role) ?? false
  }

  async function login(payload: LoginPayload): Promise<void> {
    isLoading.value = true
    try {
      const result = await authService.login(payload)
      token.value = result.token
      user.value = result.user
      setStoredToken(result.token)
    } finally {
      isLoading.value = false
    }
  }

  async function fetchCurrentUser(): Promise<void> {
    user.value = await authService.me()
  }

  function clearSession(): void {
    user.value = null
    token.value = null
    setStoredToken(null)
  }

  async function logout(): Promise<void> {
    try {
      await authService.logout()
    } finally {
      clearSession()
    }
  }

  return {
    user,
    token,
    isLoading,
    isAuthenticated,
    can,
    hasRole,
    login,
    logout,
    clearSession,
    fetchCurrentUser,
  }
})
