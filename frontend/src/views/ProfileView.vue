<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="page-content">
    <div class="container" style="max-width: 600px">

      <div class="page-header">
        <div>
          <p class="page-header__eyebrow">/ Account</p>
          <h1 class="page-header__title display">Profile</h1>
        </div>
      </div>

      <!-- Profile card -->
      <div class="profile-card">
        <div class="profile-card__avatar" aria-hidden="true">
          {{ auth.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
        </div>
        <div class="profile-card__info">
          <h2 class="profile-card__name">{{ auth.user?.name }}</h2>
          <p class="profile-card__email">{{ auth.user?.email }}</p>
          <span :class="['badge', 'badge--available']" style="margin-top: .5rem;">{{ auth.user?.role }}</span>
        </div>
      </div>

      <!-- Quick links -->
      <div class="profile-links">
        <RouterLink to="/liked-products" class="profile-link">
          <div class="profile-link__left">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <span>Liked Products</span>
          </div>
          <span class="profile-link__arrow" aria-hidden="true">→</span>
        </RouterLink>
        <RouterLink to="/my-orders" class="profile-link">
          <div class="profile-link__left">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <span>My Orders</span>
          </div>
          <span class="profile-link__arrow" aria-hidden="true">→</span>
        </RouterLink>
      </div>

      <button class="logout-btn" @click="handleLogout">
        Sign Out
      </button>
    </div>
  </div>
</template>

<style scoped>
.page-header { margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid var(--color-balsamico-border); }
.page-header__eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .5rem; }
.page-header__title   { font-size: clamp(2rem, 5vw, 3rem); color: var(--color-seashell); }

.profile-card {
  display: flex; align-items: center; gap: 1.5rem;
  padding: 2rem; background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border); margin-bottom: 1.5rem;
}
.profile-card__avatar {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: var(--color-spice-dim);
  border: 1px solid var(--color-spice-border);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display);
  font-size: 1.4rem;
  color: var(--color-spice-market);
  flex-shrink: 0;
}
.profile-card__name  { font-size: 1.25rem; font-weight: 700; color: var(--color-seashell); margin-bottom: .25rem; }
.profile-card__email { font-size: .85rem; color: var(--color-seashell-muted); }

.profile-links { display: flex; flex-direction: column; gap: 1px; margin-bottom: 2rem; }
.profile-link {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.1rem 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  font-weight: 500; font-size: .9rem;
  color: var(--color-seashell-muted);
  text-decoration: none;
  transition: all var(--transition-fast);
}
.profile-link:hover { border-color: var(--color-spice-border); color: var(--color-seashell); }
.profile-link__left { display: flex; align-items: center; gap: .75rem; }
.profile-link__arrow { color: var(--color-spice-market); font-size: .9rem; }

.logout-btn {
  background: transparent;
  border: 1px solid var(--color-spice-border);
  color: var(--color-spice-market);
  font-family: var(--font-body);
  font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
  padding: .875rem 2rem; cursor: pointer; border-radius: var(--radius);
  transition: all var(--transition-fast);
}
.logout-btn:hover { background: var(--color-spice-market); color: var(--color-seashell); }
</style>
