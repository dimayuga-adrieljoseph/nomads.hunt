<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const route  = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

const navItems = [
  { to: '/admin',           label: 'Dashboard', exact: true,
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>` },
  { to: '/admin/products',  label: 'Products',
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>` },
  { to: '/admin/announcements', label: 'Announcements',
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11v-1a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v1"/><path d="M5 10V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v5"/><path d="M4 11v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M8 15h8"/></svg>` },
  { to: '/admin/claims',    label: 'Claims',
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>` },
  { to: '/admin/orders',    label: 'Orders',
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>` },
  { to: '/admin/customers', label: 'Customers',
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>` },
  { to: '/admin/activity',  label: 'Activity',
    icon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>` },
]

function isActive(item: { to: string; exact?: boolean }) {
  if (item.exact) return route.path === item.to
  return route.path.startsWith(item.to)
}
</script>

<template>
  <div class="admin-shell">
    <!-- Mobile sidebar toggle -->
    <button
      class="admin-sidebar-toggle"
      :aria-expanded="sidebarOpen"
      aria-label="Toggle sidebar"
      @click="sidebarOpen = !sidebarOpen"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <!-- Sidebar -->
    <aside :class="['admin-sidebar', { 'admin-sidebar--open': sidebarOpen }]" role="navigation" aria-label="Admin navigation">
      <!-- Brand -->
      <div class="admin-sidebar__brand">
        <RouterLink to="/" class="admin-sidebar__logo display" aria-label="NOMADS.HUNT public site">
          NOMADS<span>.</span>HUNT
        </RouterLink>
        <span class="admin-sidebar__role">Admin Panel</span>
      </div>

      <!-- Nav -->
      <nav class="admin-sidebar__nav">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="['admin-sidebar__link', { 'admin-sidebar__link--active': isActive(item) }]"
          :aria-current="isActive(item) ? 'page' : undefined"
          @click="sidebarOpen = false"
        >
          <!-- eslint-disable-next-line vue/no-v-html -->
          <span class="admin-sidebar__icon" aria-hidden="true" v-html="item.icon" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <!-- Footer -->
      <div class="admin-sidebar__footer">
        <div class="admin-sidebar__user">
          <div class="admin-sidebar__user-name">{{ auth.user?.name }}</div>
          <div class="admin-sidebar__user-email">{{ auth.user?.email }}</div>
        </div>
        <button class="admin-sidebar__logout" @click="handleLogout">Sign Out</button>
      </div>
    </aside>

    <!-- Backdrop (mobile) -->
    <div v-if="sidebarOpen" class="admin-sidebar__backdrop" aria-hidden="true" @click="sidebarOpen = false" />

    <!-- Main -->
    <main class="admin-main" role="main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.admin-shell { display: flex; min-height: 100vh; background: var(--color-balsamico); }

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.admin-sidebar {
  width: 230px; flex-shrink: 0;
  background: var(--color-balsamico-light);
  border-right: 1px solid var(--color-balsamico-border);
  display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; bottom: 0;
  z-index: 150;
  transition: transform var(--transition-normal);
}

.admin-sidebar__brand {
  padding: 1.75rem 1.5rem 1.25rem;
  border-bottom: 1px solid var(--color-balsamico-border);
}
.admin-sidebar__logo {
  font-size: .95rem; letter-spacing: .1em;
  color: var(--color-seashell); display: block; margin-bottom: .375rem;
  text-decoration: none; transition: color var(--transition-fast);
}
.admin-sidebar__logo:hover { color: var(--color-spice-market); }
.admin-sidebar__logo span { color: var(--color-spice-market); }
.admin-sidebar__role {
  font-size: .62rem; font-weight: 700; letter-spacing: .18em;
  text-transform: uppercase; color: var(--color-seashell-muted);
}

.admin-sidebar__nav {
  flex: 1; padding: 1rem 0; overflow-y: auto;
  display: flex; flex-direction: column; gap: 2px;
}

.admin-sidebar__link {
  display: flex; align-items: center; gap: .875rem;
  padding: .7rem 1.5rem;
  font-size: .82rem; font-weight: 500;
  color: var(--color-seashell-muted);
  text-decoration: none;
  border-left: 2px solid transparent;
  transition: all var(--transition-fast);
}
.admin-sidebar__link:hover {
  color: var(--color-seashell);
  background: var(--color-seashell-dim);
  border-left-color: rgba(254,243,238,.15);
}
.admin-sidebar__link--active {
  color: var(--color-seashell);
  background: var(--color-spice-dim);
  border-left-color: var(--color-spice-market);
}
.admin-sidebar__icon { display: flex; align-items: center; flex-shrink: 0; opacity: .7; }
.admin-sidebar__link--active .admin-sidebar__icon,
.admin-sidebar__link:hover .admin-sidebar__icon { opacity: 1; }

.admin-sidebar__footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--color-balsamico-border);
  display: flex; flex-direction: column; gap: .75rem;
}
.admin-sidebar__user-name  { font-size: .85rem; font-weight: 600; color: var(--color-seashell); }
.admin-sidebar__user-email { font-size: .72rem; color: var(--color-seashell-muted); }
.admin-sidebar__logout {
  background: transparent; border: 1px solid var(--color-spice-border);
  color: var(--color-spice-market); font-family: var(--font-body);
  font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  padding: .5rem 1rem; cursor: pointer; border-radius: var(--radius);
  transition: all var(--transition-fast); align-self: flex-start;
}
.admin-sidebar__logout:hover { background: var(--color-spice-market); color: var(--color-seashell); }

/* ── Main ────────────────────────────────────────────────────────────────── */
.admin-main { flex: 1; margin-left: 230px; padding: calc(var(--nav-height) + 2.5rem) 2.5rem 2.5rem; min-height: 100vh; overflow-y: auto; }

/* ── Mobile toggle ───────────────────────────────────────────────────────── */
.admin-sidebar-toggle {
  display: none;
  position: fixed; top: 1rem; left: 1rem; z-index: 160;
  background: var(--color-balsamico-light); border: 1px solid var(--color-balsamico-border);
  color: var(--color-seashell); padding: .5rem; border-radius: var(--radius); cursor: pointer;
}

.admin-sidebar__backdrop {
  display: none;
  position: fixed; inset: 0; z-index: 145;
  background: rgba(26,15,5,.65);
}

@media (max-width: 900px) {
  .admin-sidebar { transform: translateX(-100%); }
  .admin-sidebar--open { transform: translateX(0); }
  .admin-main { margin-left: 0; }
  .admin-sidebar-toggle { display: flex; }
  .admin-sidebar__backdrop { display: block; }
}
</style>
