<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const name                 = ref('')
const email                = ref('')
const password             = ref('')
const passwordConfirmation = ref('')
const error                = ref('')
const fieldErrors          = ref<Record<string, string>>({})
const loading              = ref(false)

async function submit() {
  error.value       = ''
  fieldErrors.value = {}
  loading.value     = true

  try {
    await auth.register(name.value, email.value, password.value, passwordConfirmation.value)
    router.push('/')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      const errs = err.response.data.errors
      Object.keys(errs).forEach((k) => {
        fieldErrors.value[k] = (errs[k] ?? [])[0] ?? ''
      })
    } else {
      error.value = err.response?.data?.message ?? 'Registration failed. Please try again.'
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
        <p class="auth-sub">Create your account</p>
      </div>

      <form class="auth-form" @submit.prevent="submit">
        <div v-if="error" class="alert alert--error">{{ error }}</div>

        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input v-model="name" type="text" class="form-input" placeholder="Juan dela Cruz" required />
          <span v-if="fieldErrors.name" class="form-error">{{ fieldErrors.name }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="email" type="email" class="form-input" placeholder="you@example.com" required />
          <span v-if="fieldErrors.email" class="form-error">{{ fieldErrors.email }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input v-model="password" type="password" class="form-input" placeholder="Min. 8 characters" required />
          <span v-if="fieldErrors.password" class="form-error">{{ fieldErrors.password }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input v-model="passwordConfirmation" type="password" class="form-input" placeholder="Repeat password" required />
        </div>

        <button type="submit" class="btn btn--primary btn--full" :disabled="loading">
          {{ loading ? 'Creating account…' : 'Create Account' }}
        </button>
      </form>

      <p class="auth-footer">
        Already have an account?
        <RouterLink to="/login">Sign In</RouterLink>
      </p>
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
.auth-card { width: 100%; max-width: 420px; padding: 2.5rem; }
.auth-header { text-align: center; margin-bottom: 2rem; }
.auth-brand { font-size: 1.6rem; font-weight: 800; letter-spacing: .08em; }
.auth-brand span { color: var(--color-mine); }
.auth-sub { color: var(--color-muted); margin-top: .375rem; font-size: .9rem; }
.auth-form { display: flex; flex-direction: column; gap: 1.25rem; }
.auth-footer { text-align: center; margin-top: 1.5rem; font-size: .875rem; color: var(--color-muted); }
.auth-footer a { color: var(--color-text); font-weight: 600; }
</style>
