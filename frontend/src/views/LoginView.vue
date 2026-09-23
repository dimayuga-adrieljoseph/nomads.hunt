<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

const email    = ref('')
const password = ref('')
const error    = ref('')
const loading  = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const user = await auth.login(email.value, password.value)
    const redirect = (route.query.redirect as string) || (user.role === 'admin' ? '/admin' : '/')
    router.push(redirect)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      const errs = err.response.data.errors
      if (errs.email) {
        error.value = errs.email[0] ?? 'Login failed.'
      }
    } else {
      error.value = err.response?.data?.message ?? 'Login failed. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card card">
      <div class="auth-header">
        <h1 class="auth-brand">NOMADS<span>.</span>HUNT</h1>
        <p class="auth-sub">Sign in to your account</p>
      </div>

      <form class="auth-form" @submit.prevent="submit">
        <div v-if="error" class="alert alert--error">{{ error }}</div>

        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="form-input"
            placeholder="you@example.com"
            autocomplete="email"
            required
          />
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="form-input"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          />
        </div>

        <button
          type="submit"
          class="btn btn--primary btn--full"
          :disabled="loading"
        >
          {{ loading ? 'Signing in…' : 'Sign In' }}
        </button>
      </form>

      <p class="auth-footer">
        Don't have an account?
        <RouterLink to="/register">Register</RouterLink>
      </p>

      <div class="auth-demo">
        <p class="auth-demo-title">Demo accounts</p>
        <p>Admin: admin@nomads.hunt / password</p>
        <p>Customer: pedro@demo.com / password</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  padding: 2.5rem;
}

.auth-header { text-align: center; margin-bottom: 2rem; }

.auth-brand {
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: .08em;
}
.auth-brand span { color: var(--color-mine); }

.auth-sub {
  color: var(--color-muted);
  margin-top: .375rem;
  font-size: .9rem;
}

.auth-form { display: flex; flex-direction: column; gap: 1.25rem; }

.auth-footer {
  text-align: center;
  margin-top: 1.5rem;
  font-size: .875rem;
  color: var(--color-muted);
}
.auth-footer a { color: var(--color-text); font-weight: 600; }

.auth-demo {
  margin-top: 1.5rem;
  padding: .875rem 1rem;
  background: var(--color-surface-2);
  border-radius: var(--radius);
  font-size: .75rem;
  color: var(--color-muted);
  line-height: 1.8;
}
.auth-demo-title { font-weight: 600; color: var(--color-text); margin-bottom: .25rem; }
</style>
