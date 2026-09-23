<script setup lang="ts">
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <nav class="nav">
    <div class="container nav__inner">
      <!-- Brand -->
      <RouterLink to="/" class="nav__brand">
        <span class="nav__brand-text">NOMADS<span class="nav__brand-dot">.</span>HUNT</span>
      </RouterLink>

      <!-- Links -->
      <div class="nav__links">
        <RouterLink to="/" class="nav__link">Catalog</RouterLink>

        <template v-if="auth.isLoggedIn && auth.isCustomer">
          <RouterLink to="/my-claims" class="nav__link">My Claims</RouterLink>
          <RouterLink to="/my-orders" class="nav__link">My Orders</RouterLink>
        </template>

        <template v-if="auth.isLoggedIn && auth.isAdmin">
          <RouterLink to="/admin" class="nav__link nav__link--admin">Admin</RouterLink>
        </template>
      </div>

      <!-- Auth -->
      <div class="nav__auth">
        <template v-if="!auth.isLoggedIn">
          <RouterLink to="/login" class="btn btn--ghost btn--sm">Log In</RouterLink>
          <RouterLink to="/register" class="btn btn--primary btn--sm">Register</RouterLink>
        </template>

        <template v-else>
          <RouterLink
            v-if="auth.isCustomer"
            to="/profile"
            class="nav__user"
          >
            {{ auth.user?.name }}
          </RouterLink>
          <span v-else class="nav__user">{{ auth.user?.name }}</span>
          <button class="btn btn--ghost btn--sm" @click="handleLogout">Log Out</button>
        </template>
      </div>
    </div>
  </nav>

  <main>
    <RouterView />
  </main>
</template>

<style scoped>
.nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  height: var(--nav-height);
  background: rgba(14, 14, 14, .92);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--color-border);
}

.nav__inner {
  display: flex;
  align-items: center;
  height: 100%;
  gap: 1.5rem;
}

.nav__brand {
  flex-shrink: 0;
}
.nav__brand-text {
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: .08em;
  color: var(--color-text);
}
.nav__brand-dot { color: var(--color-mine); }

.nav__links {
  display: flex;
  gap: 1.25rem;
  flex: 1;
}

.nav__link {
  font-size: .875rem;
  font-weight: 500;
  color: var(--color-muted);
  transition: color .15s;
}
.nav__link:hover,
.nav__link.router-link-active { color: var(--color-text); }

.nav__link--admin {
  color: var(--color-mine);
}

.nav__auth {
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-shrink: 0;
}

.nav__user {
  font-size: .85rem;
  color: var(--color-muted);
}
</style>
