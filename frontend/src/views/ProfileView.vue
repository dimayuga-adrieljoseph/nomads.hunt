<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="page-content">
    <div class="container" style="max-width:480px">
      <h1 style="margin-bottom:2rem">Profile</h1>

      <div class="profile card">
        <div class="profile__avatar">
          {{ auth.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
        </div>
        <div class="profile__name">{{ auth.user?.name }}</div>
        <div class="profile__email">{{ auth.user?.email }}</div>
        <div class="profile__role"><span class="badge badge--available">{{ auth.user?.role }}</span></div>
      </div>

      <div class="profile-links">
        <RouterLink to="/my-claims" class="profile-link card">
          <span>My Claims</span>
          <span class="profile-link__arrow">→</span>
        </RouterLink>
        <RouterLink to="/my-orders" class="profile-link card">
          <span>My Orders</span>
          <span class="profile-link__arrow">→</span>
        </RouterLink>
      </div>

      <button class="btn btn--danger btn--full" style="margin-top:2rem" @click="handleLogout">
        Log Out
      </button>
    </div>
  </div>
</template>

<style scoped>
.profile {
  padding: 2rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .75rem;
  margin-bottom: 1.5rem;
}
.profile__avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
}
.profile__name  { font-size: 1.25rem; font-weight: 700; }
.profile__email { font-size: .875rem; color: var(--color-muted); }

.profile-links { display: flex; flex-direction: column; gap: .75rem; }
.profile-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  font-weight: 500;
  transition: border-color .15s;
}
.profile-link:hover { border-color: #444; }
.profile-link__arrow { color: var(--color-muted); }
</style>
