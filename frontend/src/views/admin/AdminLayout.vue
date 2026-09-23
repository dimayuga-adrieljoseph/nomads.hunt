<script setup lang="ts">
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const route  = useRoute()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

const navItems = [
  { to: '/admin',           label: 'Dashboard',  icon: '◈', exact: true },
  { to: '/admin/products',  label: 'Products',   icon: '▣' },
  { to: '/admin/claims',    label: 'Claims',     icon: '⊞' },
  { to: '/admin/orders',    label: 'Orders',     icon: '◎' },
  { to: '/admin/customers', label: 'Customers',  icon: '◉' },
  { to: '/admin/activity',  label: 'Activity',   icon: '◍' },
]

function isActive(item: { to: string; exact?: boolean }) {
  if (item.exact) return route.path === item.to
  return route.path.startsWith(item.to)
}
</script>

<template>
  <div class="admin-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar__brand">
        <RouterLink to="/" class="sidebar__logo">
          NOMADS<span>.</span>HUNT
        </RouterLink>
        <div class="sidebar__role">Admin Panel</div>
      </div>

      <nav class="sidebar__nav">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="['sidebar__link', { 'sidebar__link--active': isActive(item) }]"
        >
          <span class="sidebar__icon">{{ item.icon }}</span>
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-name">{{ auth.user?.name }}</div>
          <div class="sidebar__user-email">{{ auth.user?.email }}</div>
        </div>
        <button class="sidebar__logout btn btn--ghost btn--sm" @click="handleLogout">
          Log Out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <main class="admin-main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.admin-shell {
  display: flex;
  min-height: 100vh;
}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.sidebar {
  width: 220px;
  flex-shrink: 0;
  background: var(--color-surface);
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 50;
}

.sidebar__brand {
  padding: 1.5rem 1.25rem 1rem;
  border-bottom: 1px solid var(--color-border);
}
.sidebar__logo {
  font-size: .9rem;
  font-weight: 800;
  letter-spacing: .08em;
  color: var(--color-text);
  display: block;
}
.sidebar__logo span { color: var(--color-mine); }
.sidebar__role {
  font-size: .7rem;
  color: var(--color-muted);
  margin-top: .25rem;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.sidebar__nav {
  flex: 1;
  padding: .875rem 0;
  display: flex;
  flex-direction: column;
  gap: .125rem;
  overflow-y: auto;
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .625rem 1.25rem;
  font-size: .875rem;
  font-weight: 500;
  color: var(--color-muted);
  border-radius: 0;
  transition: color .15s, background .15s;
}
.sidebar__link:hover {
  color: var(--color-text);
  background: var(--color-surface-2);
}
.sidebar__link--active {
  color: var(--color-text);
  background: var(--color-surface-2);
  border-left: 2px solid var(--color-mine);
}
.sidebar__icon { font-size: .85rem; width: 1rem; text-align: center; }

.sidebar__footer {
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: .625rem;
}
.sidebar__user-name  { font-size: .85rem; font-weight: 600; }
.sidebar__user-email { font-size: .75rem; color: var(--color-muted); }
.sidebar__logout     { align-self: flex-start; }

/* ── Main content ────────────────────────────────────────────────────────── */
.admin-main {
  flex: 1;
  margin-left: 220px;
  padding: 2rem;
  min-height: 100vh;
  overflow-y: auto;
}

@media (max-width: 768px) {
  .sidebar   { display: none; }
  .admin-main { margin-left: 0; }
}
</style>
