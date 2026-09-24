<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { adminService } from '@/services/admin.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface Stats {
  available: number; active_claims: number; pending_payments: number; sold: number
  total_customers: number; total_products: number; total_orders: number; total_revenue: number
  /** Two-stage lifecycle counters */
  claim_period: number; payment_windows: number; waiting_claims: number
  payment_expired: number; overridden: number
}
interface ActivityEntry {
  id: number; action: string; description: string
  user: { id: number; name: string } | null
  product: { id: number; name: string } | null
  created_at: string
}

const stats    = ref<Stats | null>(null)
const activity = ref<ActivityEntry[]>([])
const loading  = ref(true)

async function load() {
  loading.value = true
  try {
    const data    = await adminService.dashboard()
    stats.value   = data.stats
    activity.value = data.recent_activity
  } finally { loading.value = false }
}

function actionColor(action: string): string {
  if (action.includes('SOLD') || action.includes('PAYMENT')) return 'var(--color-grab)'
  if (action.includes('STEAL'))   return 'var(--color-steal)'
  if (action.includes('MINE'))    return 'var(--color-mine)'
  if (action.includes('EXPIRED') || action.includes('OVERRIDDEN')) return 'var(--color-spice-market)'
  if (action.includes('GRAB'))    return 'var(--color-grab)'
  return 'var(--color-seashell-muted)'
}

onMounted(load)
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin</p>
        <h1 class="admin-page-title">Dashboard</h1>
      </div>
      <button class="btn btn--ghost btn--sm" :disabled="loading" @click="load">↻ Refresh</button>
    </div>

    <div v-if="loading" class="spinner" />

    <template v-else-if="stats">
      <!-- Stat grid -->
      <div class="stat-grid">
        <div class="stat-card">
          <span class="stat-card__label">Available</span>
          <span class="stat-card__value stat-card__value--spice">{{ stats.available }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Active Claims</span>
          <span class="stat-card__value stat-card__value--spice">{{ stats.active_claims }}</span>
        </div>
        <!-- Two-stage lifecycle breakdown -->
        <div class="stat-card">
          <span class="stat-card__label">In Claim Period</span>
          <span class="stat-card__value stat-card__value--spice">{{ stats.claim_period }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">In Payment Window</span>
          <span class="stat-card__value stat-card__value--gold">{{ stats.payment_windows }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Queued Claimants</span>
          <span class="stat-card__value">{{ stats.waiting_claims }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Payment Expired</span>
          <span class="stat-card__value">{{ stats.payment_expired }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Overridden</span>
          <span class="stat-card__value">{{ stats.overridden }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Pending Payments</span>
          <span class="stat-card__value">{{ stats.pending_payments }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Sold</span>
          <span class="stat-card__value">{{ stats.sold }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Customers</span>
          <span class="stat-card__value">{{ stats.total_customers }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Total Products</span>
          <span class="stat-card__value">{{ stats.total_products }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Completed Orders</span>
          <span class="stat-card__value stat-card__value--gold">{{ stats.total_orders }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-card__label">Total Revenue</span>
          <span class="stat-card__value stat-card__value--gold">{{ formatPeso(stats.total_revenue) }}</span>
        </div>
      </div>

      <!-- Quick links -->
      <div class="quick-links">
        <RouterLink to="/admin/claims" class="quick-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
          View Active Claims
          <span class="quick-link__arrow">→</span>
        </RouterLink>
        <RouterLink to="/admin/orders" class="quick-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
          </svg>
          Pending Payments
          <span class="quick-link__arrow">→</span>
        </RouterLink>
        <RouterLink to="/admin/products" class="quick-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/>
          </svg>
          Manage Products
          <span class="quick-link__arrow">→</span>
        </RouterLink>
      </div>

      <!-- Recent activity -->
      <div class="section-block">
        <h2 class="section-block__title">Recent Activity</h2>
        <div class="activity-list">
          <div v-if="activity.length === 0" class="empty-row">No activity yet.</div>
          <div v-for="entry in activity" :key="entry.id" class="activity-item">
            <span class="activity-item__dot" :style="{ background: actionColor(entry.action) }" aria-hidden="true" />
            <div class="activity-item__body">
              <span class="activity-item__desc">{{ entry.description }}</span>
              <span class="activity-item__meta">
                <span class="activity-item__action">{{ entry.action }}</span>
                <span class="activity-item__time">{{ formatDate(entry.created_at) }}</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 2rem; }
.admin-eyebrow { font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .3rem; }
.admin-page-title { font-size: 1.75rem; font-weight: 700; color: var(--color-seashell); }

/* Stat grid */
.stat-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
  gap: 1px; margin-bottom: 1.5rem;
  border: 1px solid var(--color-balsamico-border);
}
.stat-card {
  padding: 1.5rem 1.25rem;
  background: var(--color-balsamico-light);
  display: flex; flex-direction: column; gap: .5rem;
  transition: background var(--transition-fast);
}
.stat-card:hover { background: var(--color-balsamico-lighter); }
.stat-card__label {
  font-size: .65rem; font-weight: 700; letter-spacing: .12em;
  text-transform: uppercase; color: var(--color-seashell-muted);
}
.stat-card__value { font-size: 2.2rem; font-weight: 700; line-height: 1; color: var(--color-seashell); }
.stat-card__value--spice { color: var(--color-spice-market); }
.stat-card__value--gold  { color: var(--color-grab); }

/* Quick links */
.quick-links { display: grid; grid-template-columns: repeat(3,1fr); gap: 1px; margin-bottom: 2.5rem; }
.quick-link {
  display: flex; align-items: center; gap: .75rem;
  padding: 1rem 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  font-size: .82rem; font-weight: 500; color: var(--color-seashell-muted);
  text-decoration: none; transition: all var(--transition-fast);
}
.quick-link:hover { border-color: var(--color-spice-border); color: var(--color-seashell); background: var(--color-spice-dim); }
.quick-link__arrow { margin-left: auto; color: var(--color-spice-market); }

/* Section */
.section-block { }
.section-block__title {
  font-size: .68rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  color: var(--color-seashell-muted); margin-bottom: 1rem;
  padding-bottom: .625rem; border-bottom: 1px solid var(--color-balsamico-border);
}
.activity-list {
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
}
.activity-item {
  display: flex; align-items: flex-start; gap: .875rem;
  padding: .875rem 1.25rem; border-bottom: 1px solid var(--color-balsamico-border);
}
.activity-item:last-child { border-bottom: none; }
.activity-item__dot {
  width: 8px; height: 8px; border-radius: 50%;
  flex-shrink: 0; margin-top: .35rem;
}
.activity-item__body { flex: 1; display: flex; flex-direction: column; gap: .25rem; }
.activity-item__desc { font-size: .875rem; color: var(--color-seashell-strong); }
.activity-item__meta { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
.activity-item__action { font-size: .62rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--color-seashell-muted); }
.activity-item__time   { font-size: .72rem; color: rgba(254,243,238,.3); margin-left: auto; }
.empty-row { padding: 2rem; text-align: center; color: var(--color-seashell-muted); font-size: .875rem; }

@media (max-width: 768px) {
  .quick-links { grid-template-columns: 1fr; }
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
