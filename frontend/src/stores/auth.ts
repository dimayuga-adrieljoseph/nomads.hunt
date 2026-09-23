import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService, type AuthUser } from '@/services/auth.service'

export const useAuthStore = defineStore('auth', () => {
  // ── State ─────────────────────────────────────────────────────────────────

  const user = ref<AuthUser | null>(
    (() => {
      try {
        const stored = localStorage.getItem('auth_user')
        return stored ? (JSON.parse(stored) as AuthUser) : null
      } catch {
        return null
      }
    })(),
  )

  const token = ref<string | null>(localStorage.getItem('auth_token'))

  // ── Computed ──────────────────────────────────────────────────────────────

  const isLoggedIn  = computed(() => !!token.value && !!user.value)
  const isAdmin     = computed(() => user.value?.role === 'admin')
  const isCustomer  = computed(() => user.value?.role === 'customer')

  // ── Actions ───────────────────────────────────────────────────────────────

  function setAuth(authUser: AuthUser, authToken: string) {
    user.value  = authUser
    token.value = authToken
    localStorage.setItem('auth_user',  JSON.stringify(authUser))
    localStorage.setItem('auth_token', authToken)
  }

  function clearAuth() {
    user.value  = null
    token.value = null
    localStorage.removeItem('auth_user')
    localStorage.removeItem('auth_token')
  }

  async function login(email: string, password: string) {
    const res = await authService.login(email, password)
    setAuth(res.user, res.token)
    return res.user
  }

  async function register(
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
  ) {
    const res = await authService.register(name, email, password, passwordConfirmation)
    setAuth(res.user, res.token)
    return res.user
  }

  async function logout() {
    try {
      await authService.logout()
    } finally {
      clearAuth()
    }
  }

  async function fetchMe() {
    try {
      const me = await authService.me()
      user.value = me
      localStorage.setItem('auth_user', JSON.stringify(me))
      return me
    } catch {
      clearAuth()
      return null
    }
  }

  return {
    user,
    token,
    isLoggedIn,
    isAdmin,
    isCustomer,
    login,
    register,
    logout,
    fetchMe,
    clearAuth,
  }
})
