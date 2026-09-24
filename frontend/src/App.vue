<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const mobileOpen = ref(false)
const scrolled   = ref(false)

function onScroll() { scrolled.value = window.scrollY > 40 }
onMounted(()  => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

async function handleLogout() {
  await auth.logout()
  mobileOpen.value = false
  router.push('/login')
}

function closeMobile() { mobileOpen.value = false }
</script>

<template>
  <!-- ── Navigation ─────────────────────────────────────────────────────── -->
  <nav :class="['nav', { 'nav--scrolled': scrolled }]" role="navigation" aria-label="Main navigation">
    <div class="nav__inner container">

      <!-- Brand -->
      <RouterLink to="/" class="nav__brand" aria-label="NOMADS.HUNT — Home" @click="closeMobile">
        <span class="nav__brand-text">NOMADS<span class="nav__brand-dot">.</span>HUNT</span>
      </RouterLink>

      <!-- Desktop center links -->
      <div class="nav__links" role="list">
        <RouterLink to="/" class="nav__link" role="listitem">Nomads</RouterLink>
        <RouterLink to="/catalog" class="nav__link" role="listitem">Collections</RouterLink>
        <RouterLink to="/" class="nav__link" role="listitem">Hunts</RouterLink>
      </div>

      <!-- Desktop right actions -->
      <div class="nav__actions">
        <!-- Customer icons -->
        <template v-if="auth.isLoggedIn && auth.isCustomer">
          <RouterLink to="/my-claims" class="nav__icon-btn" aria-label="My Claims" title="My Claims">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </RouterLink>
          <RouterLink to="/my-orders" class="nav__icon-btn" aria-label="My Orders" title="My Orders">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
              <line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
          </RouterLink>
          <RouterLink to="/profile" class="nav__icon-btn nav__icon-btn--user" aria-label="Profile" title="Profile">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </RouterLink>
          <button class="nav__signout" @click="handleLogout">Sign Out</button>
        </template>

        <!-- Admin -->
        <template v-else-if="auth.isLoggedIn && auth.isAdmin">
          <RouterLink to="/admin" class="nav__link nav__link--admin">Admin Panel</RouterLink>
          <button class="nav__signout" @click="handleLogout">Sign Out</button>
        </template>

        <!-- Guest -->
        <template v-else>
          <RouterLink to="/login" class="nav__signin btn btn--ghost btn--sm">Sign In</RouterLink>
          <RouterLink to="/register" class="btn btn--primary btn--sm">Register</RouterLink>
        </template>
      </div>

      <!-- Mobile hamburger -->
      <button
        class="nav__hamburger"
        :class="{ 'nav__hamburger--open': mobileOpen }"
        :aria-expanded="mobileOpen"
        aria-controls="mobile-menu"
        aria-label="Toggle navigation menu"
        @click="mobileOpen = !mobileOpen"
      >
        <span /><span /><span />
      </button>
    </div>
  </nav>

  <!-- ── Mobile Menu ─────────────────────────────────────────────────────── -->
  <div
    id="mobile-menu"
    :class="['mobile-nav', { 'mobile-nav--open': mobileOpen }]"
    role="dialog"
    aria-modal="true"
    aria-label="Mobile navigation"
  >
    <div class="mobile-nav__inner">
      <nav class="mobile-nav__links">
        <RouterLink to="/" class="mobile-nav__link" @click="closeMobile">Nomads</RouterLink>
        <RouterLink to="/catalog" class="mobile-nav__link" @click="closeMobile">Collections</RouterLink>
        <RouterLink to="/" class="mobile-nav__link" @click="closeMobile">Hunts</RouterLink>
        <hr class="mobile-nav__divider" />

        <template v-if="auth.isLoggedIn && auth.isCustomer">
          <RouterLink to="/my-claims"  class="mobile-nav__link" @click="closeMobile">My Claims</RouterLink>
          <RouterLink to="/my-orders"  class="mobile-nav__link" @click="closeMobile">My Orders</RouterLink>
          <RouterLink to="/profile"    class="mobile-nav__link" @click="closeMobile">Profile</RouterLink>
          <button class="mobile-nav__signout" @click="handleLogout">Sign Out</button>
        </template>

        <template v-else-if="auth.isLoggedIn && auth.isAdmin">
          <RouterLink to="/admin" class="mobile-nav__link mobile-nav__link--admin" @click="closeMobile">Admin Panel</RouterLink>
          <button class="mobile-nav__signout" @click="handleLogout">Sign Out</button>
        </template>

        <template v-else>
          <RouterLink to="/login"    class="mobile-nav__link" @click="closeMobile">Sign In</RouterLink>
          <RouterLink to="/register" class="mobile-nav__cta btn btn--primary btn--full" @click="closeMobile">Register</RouterLink>
        </template>
      </nav>
    </div>
  </div>

  <!-- Backdrop -->
  <div
    v-if="mobileOpen"
    class="mobile-nav__backdrop"
    aria-hidden="true"
    @click="closeMobile"
  />

  <!-- ── Page Content ────────────────────────────────────────────────────── -->
  <main>
    <RouterView />
  </main>
</template>

<style scoped>
/* ── Navigation bar ────────────────────────────────────────────────────────── */
.nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 200;
  height: var(--nav-height);
  background: var(--color-balsamico);
  border-bottom: 1px solid var(--color-balsamico-border);
  transition: border-color var(--transition-normal), background var(--transition-normal);
}
.nav--scrolled {
  background: rgba(26,15,5,.97);
  border-bottom-color: rgba(186,68,29,.25);
}

.nav__inner {
  display: flex;
  align-items: center;
  height: 100%;
  gap: 2rem;
}

/* ── Brand ─────────────────────────────────────────────────────────────────── */
.nav__brand { flex-shrink: 0; }
.nav__brand-text {
  font-family: var(--font-display);
  font-size: 1.35rem;
  letter-spacing: .06em;
  color: var(--color-seashell);
  line-height: 1;
}
.nav__brand-dot { color: var(--color-spice-market); }

/* ── Center links ───────────────────────────────────────────────────────────── */
.nav__links {
  display: flex;
  gap: 2.5rem;
  flex: 1;
  justify-content: center;
}

.nav__link {
  font-family: var(--font-body);
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  transition: color var(--transition-fast);
  position: relative;
}
.nav__link::after {
  content: '';
  position: absolute;
  bottom: -4px; left: 0; right: 0;
  height: 1px;
  background: var(--color-spice-market);
  transform: scaleX(0);
  transition: transform var(--transition-fast);
}
.nav__link:hover,
.nav__link.router-link-active {
  color: var(--color-seashell);
}
.nav__link:hover::after,
.nav__link.router-link-active::after {
  transform: scaleX(1);
}
.nav__link--admin { color: var(--color-spice-market); }

/* ── Right actions ──────────────────────────────────────────────────────────── */
.nav__actions {
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-shrink: 0;
}

/* Icon buttons */
.nav__icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 50%;
  color: var(--color-seashell-muted);
  transition: color var(--transition-fast), background var(--transition-fast);
  flex-shrink: 0;
}
.nav__icon-btn:hover { color: var(--color-seashell); background: var(--color-seashell-dim); }
.nav__icon-btn--user { color: var(--color-seashell-muted); }

.nav__signout {
  background: transparent;
  border: none;
  font-family: var(--font-body);
  font-size: .78rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  cursor: pointer;
  padding: .375rem .5rem;
  transition: color var(--transition-fast);
}
.nav__signout:hover { color: var(--color-spice-market); }

.nav__signin {
  font-size: .78rem;
  letter-spacing: .08em;
}

/* ── Hamburger ──────────────────────────────────────────────────────────────── */
.nav__hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  padding: .5rem;
  margin-left: auto;
  cursor: pointer;
}
.nav__hamburger span {
  display: block;
  width: 24px; height: 2px;
  background: var(--color-seashell);
  border-radius: 0;
  transition: all var(--transition-fast);
  transform-origin: center;
}
.nav__hamburger--open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.nav__hamburger--open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.nav__hamburger--open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Mobile nav ─────────────────────────────────────────────────────────────── */
.mobile-nav {
  position: fixed;
  top: var(--nav-height); left: 0; right: 0; bottom: 0;
  z-index: 190;
  background: var(--color-balsamico);
  transform: translateX(-100%);
  transition: transform var(--transition-normal);
  overflow-y: auto;
}
.mobile-nav--open { transform: translateX(0); }

.mobile-nav__inner { padding: 2rem; }

.mobile-nav__links {
  display: flex;
  flex-direction: column;
  gap: 0;
}
.mobile-nav__link {
  display: block;
  padding: 1rem 0;
  font-size: 1.1rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  border-bottom: 1px solid var(--color-balsamico-border);
  transition: color var(--transition-fast);
}
.mobile-nav__link:hover,
.mobile-nav__link.router-link-active { color: var(--color-seashell); }
.mobile-nav__link--admin { color: var(--color-spice-market); }

.mobile-nav__divider {
  border: none;
  border-top: 1px solid var(--color-balsamico-border);
  margin: .5rem 0;
}

.mobile-nav__signout {
  margin-top: 1.5rem;
  background: transparent;
  border: 1px solid var(--color-spice-border);
  color: var(--color-spice-market);
  font-family: var(--font-body);
  font-size: .85rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: .75rem 1.5rem;
  cursor: pointer;
  transition: all var(--transition-fast);
  align-self: flex-start;
  border-radius: var(--radius);
}
.mobile-nav__signout:hover {
  background: var(--color-spice-market);
  color: var(--color-seashell);
}

.mobile-nav__cta { margin-top: 1rem; }

.mobile-nav__backdrop {
  position: fixed;
  inset: 0;
  z-index: 185;
  background: rgba(26,15,5,.6);
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .nav__links  { display: none; }
  .nav__actions { display: none; }
  .nav__hamburger { display: flex; }
}
</style>
