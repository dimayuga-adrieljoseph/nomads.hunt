<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
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
    const redirect = (route.query.redirect as string) || (user.role === 'admin' ? '/admin' : '/catalog')
    router.push(redirect)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors?.email) {
      error.value = err.response.data.errors.email[0] ?? 'Login failed.'
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
    <!-- Left editorial panel -->
    <div class="auth-panel" aria-hidden="true">
      <div class="auth-panel__inner">
        <span class="auth-panel__eyebrow">/ Archive</span>
        <h2 class="auth-panel__headline display">THE HUNT<br/>BEGINS<br/>HERE</h2>
        <p class="auth-panel__sub">Curated vintage pieces hunted from one destination to the next.</p>
        <div class="auth-panel__marks">
          <span class="auth-panel__mark" />
          <span class="auth-panel__mark auth-panel__mark--2" />
        </div>
      </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-form-panel">
      <div class="auth-form-wrap">
        <RouterLink to="/" class="auth-brand display" aria-label="NOMADS.HUNT — Home">
          NOMADS<span class="auth-brand__dot">.</span>HUNT
        </RouterLink>

        <div class="auth-header">
          <h1 class="auth-title">Sign In</h1>
          <p class="auth-sub">Welcome back, hunter.</p>
        </div>

        <form class="auth-form" @submit.prevent="submit" novalidate aria-label="Sign in form">
          <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

          <div class="form-group">
            <label for="email" class="auth-label">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              class="auth-input"
              placeholder="you@example.com"
              autocomplete="email"
              required
              aria-required="true"
            />
          </div>

          <div class="form-group">
            <label for="password" class="auth-label">Password</label>
            <input
              id="password"
              v-model="password"
              type="password"
              class="auth-input"
              placeholder="••••••••"
              autocomplete="current-password"
              required
              aria-required="true"
            />
          </div>

          <button
            type="submit"
            class="auth-submit"
            :disabled="loading"
            :aria-busy="loading"
          >
            {{ loading ? 'Signing in…' : 'Sign In →' }}
          </button>
        </form>

        <p class="auth-footer-link">
          No account?
          <RouterLink to="/register">Register here</RouterLink>
        </p>

        <div class="auth-demo" role="note" aria-label="Demo credentials">
          <p class="auth-demo__title">Demo credentials</p>
          <p>Admin: admin@nomads.hunt / password</p>
          <p>Customer: pedro@demo.com / password</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--color-balsamico);
}

/* ── Left editorial panel ──────────────────────────────────────────────────── */
.auth-panel {
  background: var(--color-balsamico-light);
  border-right: 1px solid var(--color-balsamico-border);
  display: flex;
  align-items: center;
  padding: 4rem 3rem;
  position: relative;
  overflow: hidden;
}
.auth-panel__inner { position: relative; z-index: 1; max-width: 400px; }
.auth-panel__eyebrow {
  font-size: .7rem;
  letter-spacing: .25em;
  text-transform: uppercase;
  color: var(--color-spice-market);
  display: block;
  margin-bottom: 2rem;
  font-family: var(--font-body);
  font-weight: 700;
}
.auth-panel__headline {
  font-size: clamp(2.5rem, 5vw, 4.5rem);
  color: var(--color-seashell);
  line-height: 1.0;
  margin-bottom: 1.5rem;
}
.auth-panel__sub {
  font-size: .9rem;
  line-height: 1.8;
  color: var(--color-seashell-muted);
  max-width: 280px;
  font-family: var(--font-body);
}
.auth-panel__marks {
  display: flex;
  gap: .75rem;
  align-items: center;
  margin-top: 3rem;
}
.auth-panel__mark { display: block; height: 1px; background: var(--color-spice-market); width: 40px; opacity: .35; }
.auth-panel__mark--2 { width: 20px; opacity: .15; }

/* ── Right form ────────────────────────────────────────────────────────────── */
.auth-form-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
}
.auth-form-wrap {
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.auth-brand {
  font-size: 1.1rem;
  letter-spacing: .08em;
  color: var(--color-seashell-muted);
  margin-bottom: 3rem;
  text-decoration: none;
  display: block;
  transition: color var(--transition-fast);
}
.auth-brand:hover { color: var(--color-seashell); }
.auth-brand__dot { color: var(--color-spice-market); }

.auth-header { margin-bottom: 2.5rem; }
.auth-title {
  font-family: var(--font-body);
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-seashell);
  margin-bottom: .375rem;
}
.auth-sub { font-size: .875rem; color: var(--color-seashell-muted); }

.auth-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  display: block;
  margin-bottom: .5rem;
}

.auth-input {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 1px solid var(--color-balsamico-border);
  border-radius: 0;
  color: var(--color-seashell);
  font-family: var(--font-body);
  font-size: 1rem;
  padding: .625rem 0;
  transition: border-color var(--transition-fast);
  outline: none;
}
.auth-input::placeholder { color: rgba(254,243,238,.2); }
.auth-input:focus { border-bottom-color: var(--color-spice-market); }

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
  margin-bottom: 2rem;
}

.auth-submit {
  width: 100%;
  background: var(--color-spice-market);
  color: var(--color-seashell);
  border: none;
  font-family: var(--font-body);
  font-size: .82rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  padding: 1rem;
  cursor: pointer;
  border-radius: var(--radius);
  transition: background var(--transition-fast);
  margin-top: .5rem;
}
.auth-submit:hover:not(:disabled) { background: #C8501F; }
.auth-submit:disabled { opacity: .45; cursor: not-allowed; }

.auth-footer-link {
  font-size: .82rem;
  color: var(--color-seashell-muted);
  margin-bottom: 2rem;
}
.auth-footer-link a {
  color: var(--color-spice-market);
  font-weight: 600;
  border-bottom: 1px solid rgba(186,68,29,.3);
  transition: border-color var(--transition-fast);
}
.auth-footer-link a:hover { border-bottom-color: var(--color-spice-market); }

.auth-demo {
  padding: 1rem 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  font-size: .75rem;
  color: var(--color-seashell-muted);
  line-height: 1.9;
}
.auth-demo__title {
  font-weight: 700;
  font-size: .68rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  margin-bottom: .375rem;
}

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .auth-page    { grid-template-columns: 1fr; }
  .auth-panel   { display: none; }
  .auth-form-panel { padding: 5rem 1.5rem 3rem; }
}
</style>
