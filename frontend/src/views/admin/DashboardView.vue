<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminService } from '@/services/admin.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface Stats {
  available: number
  active_claims: number
  pending_payments: number
  sold: number
  total_customers: number
  total_products: number
  total_orders: number
  total_revenue: number
}

interface ActivityEntry {
  id: number
  action: string
  description: string
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
    const data   = await adminService.dashboard()
    stats.value  = data.stats
    activity.value = data.recent_activity
  } finally {
    loading.value = false
  }
}

function actionColor(action: string): string {
  if (action.includes('SOLD') || action.includes('PAYMENT')) return 'var(--color-grab)'
  if (action.includes('STEAL'))    return 'var(--color-steal)'
  if (action.includes('MINE'))     return 'var(--color-mine)'
  if (action.includes('EXPIRED') || action.includes('OVERRIDDEN')) return '#f87171'
  if (action.includes('GRAB'))     return 'var(--color-grab)'
  return 'var(--color-muted)'
}

onMounted(load)
</script>

<template>
  <div>
    <div class="admin-page-header">
      <h1 class="admin-page-title">Dashboard</h1>
      <button class="btn btn--ghost btn--sm" :disabled="loading" @click="load">↻ Refresh</button>
    </div>

    <div v-if="loading" class="spinner" />

    <template v-else-if="stats">
      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card card">
          <div class="stat-card__label">Available</div>
          <div class="stat-card__value stat-card__value--green">{{ stats.available }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Active Claims</div>
          <div class="stat-card__value stat-card__value--amber">{{ stats.active_claims }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Pending Payments</div>
          <div class="stat-card__value stat-card__value--amber">{{ stats.pending_payments }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Sold</div>
          <div class="stat-card__value">{{ stats.sold }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Customers</div>
          <div class="stat-card__value">{{ stats.total_customers }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Total Products</div>
          <div class="stat-card__value">{{ stats.total_products }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Completed Orders</div>
          <div class="stat-card__value stat-card__value--green">{{ stats.total_orders }}</div>
        </div>
        <div class="stat-card card">
          <div class="stat-card__label">Total Revenue</div>
          <div class="stat-card__value stat-card__value--green">{{ formatPeso(stats.total_revenue) }}</div>
        </div>
      </div>

      <!-- Quick links -->
      <div class="quick-links">
        <RouterLink to="/admin/claims" class="quick-link card">
          <span class="quick-link__icon">⊞</span>
          <span>View Active Claims</span>
        </RouterLink>
        <RouterLink to="/admin/orders?payment_status=pending" class="quick-link card">
          <span class="quick-link__icon">◎</span>
          <span>Pending Payments</span>
        </RouterLink>
        <RouterLink to="/admin/products" class="quick-link card">
          <span class="quick-link__icon">▣</span>
          <span>Manage Products</span>
        </RouterLink>
      </div>

      <!-- Recent Activity -->
      <div class="activity-section">
        <h2 class="section-title">Recent Activity</h2>
        <div class="card">
          <div v-if="activity.length === 0" class="empty-state-sm">No activity yet.</div>
          <div
            v-for="entry in activity"
            :key="entry.id"
            class="activity-item"
          >
            <span
              class="activity-item__dot"
              :style="{ background: actionColor(entry.action) }"
            />
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
.admin-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.75rem;
}
.admin-page-title { font-size: 1.5rem; font-weight: 700; }

/* Stat grid */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.stat-card {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: .375rem;
}
.stat-card__label { font-size: .7rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--color-muted); }
.stat-card__value { font-size: 2rem; font-weight: 700; line-height: 1; }
.stat-card__value--green { color: var(--color-grab); }
.stat-card__value--amber { color: var(--color-mine); }

/* Quick links */
.quick-links {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}
.quick-link {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .875rem 1rem;
  font-size: .875rem;
  font-weight: 500;
  color: var(--color-muted);
  transition: border-color .15s, color .15s;
}
.quick-link:hover { border-color: #444; color: var(--color-text); }
.quick-link__icon { font-size: 1rem; }

/* Activity */
.section-title {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: .875rem;
}
.activity-item {
  display: flex;
  align-items: flex-start;
  gap: .875rem;
  padding: .75rem 1rem;
  border-bottom: 1px solid var(--color-border);
}
.activity-item:last-child { border-bottom: none; }
.activity-item__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: .35rem;
}
.activity-item__body { flex: 1; display: flex; flex-direction: column; gap: .2rem; }
.activity-item__desc { font-size: .875rem; }
.activity-item__meta { display: flex; gap: 1rem; align-items: center; }
.activity-item__action { font-size: .65rem; font-weight: 700; letter-spacing: .08em; color: var(--color-muted); }
.activity-item__time   { font-size: .75rem; color: var(--color-muted); margin-left: auto; }

.empty-state-sm { padding: 1.5rem; text-align: center; color: var(--color-muted); font-size: .875rem; }
</style>
