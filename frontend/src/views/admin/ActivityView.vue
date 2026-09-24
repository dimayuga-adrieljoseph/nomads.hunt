<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { adminService } from '@/services/admin.service'
import { formatDate } from '@/utils/formatters'

interface LogEntry {
  id: number; action: string; description: string; claim_id: number | null
  user: { id: number; name: string } | null
  product: { id: number; name: string } | null
  created_at: string
}

const logs         = ref<LogEntry[]>([])
const loading      = ref(true)
const actionFilter = ref('')
const currentPage  = ref(1)
const lastPage     = ref(1)
const total        = ref(0)

const ACTIONS = [
  'PRODUCT_CREATED', 'PRODUCT_UPDATED',
  'MINE_CLAIMED', 'STEAL_CLAIMED', 'GRAB_CLAIMED',
  'CLAIM_ACTIVATED', 'CLAIM_EXPIRED', 'CLAIM_OVERRIDDEN',
  'PAYMENT_CONFIRMED', 'PRODUCT_SOLD',
]

async function load(page = 1) {
  loading.value = true
  try {
    const res = await adminService.activityLogs({ action: actionFilter.value || undefined, page })
    logs.value = res.data; currentPage.value = res.meta.current_page
    lastPage.value = res.meta.last_page; total.value = res.meta.total
  } finally { loading.value = false }
}

function dotColor(action: string): string {
  if (action.includes('SOLD') || action.includes('PAYMENT')) return 'var(--color-grab)'
  if (action.includes('STEAL'))    return 'var(--color-steal)'
  if (action.includes('MINE'))     return 'var(--color-mine)'
  if (action.includes('GRAB'))     return 'var(--color-grab)'
  if (action.includes('EXPIRED') || action.includes('OVERRIDDEN')) return 'var(--color-spice-market)'
  if (action.includes('ACTIVATED')) return 'var(--color-grab)'
  return 'var(--color-seashell-muted)'
}

function badgeClass(action: string): string {
  if (action.includes('SOLD') || action.includes('PAYMENT')) return 'badge--paid'
  if (action.includes('STEAL'))    return 'badge--steal'
  if (action.includes('MINE'))     return 'badge--mine'
  if (action.includes('GRAB'))     return 'badge--grab'
  if (action.includes('EXPIRED') || action.includes('OVERRIDDEN')) return 'badge--expired'
  if (action.includes('ACTIVATED')) return 'badge--active'
  return 'badge--waiting'
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin</p>
        <h1 class="admin-page-title">Activity Log <span class="admin-count">{{ total }}</span></h1>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load(1)">↻ Refresh</button>
    </div>

    <div class="admin-toolbar">
      <select v-model="actionFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Actions</option>
        <option v-for="a in ACTIONS" :key="a" :value="a">{{ a }}</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="activity-card">
      <div v-if="logs.length === 0" class="empty-cell">No activity recorded yet.</div>

      <div v-for="entry in logs" :key="entry.id" class="log-item">
        <span
          class="log-item__dot"
          :style="{ background: dotColor(entry.action) }"
          aria-hidden="true"
        />
        <div class="log-item__body">
          <span class="log-item__desc">{{ entry.description }}</span>
          <div class="log-item__meta">
            <span :class="['badge', badgeClass(entry.action)]" style="font-size:.6rem">
              {{ entry.action }}
            </span>
            <RouterLink
              v-if="entry.user"
              :to="`/admin/customers/${entry.user.id}`"
              class="log-item__link"
            >{{ entry.user.name }}</RouterLink>
            <RouterLink
              v-if="entry.product"
              :to="`/admin/products/${entry.product.id}/claims`"
              class="log-item__link"
            >{{ entry.product.name }}</RouterLink>
          </div>
        </div>
        <span class="log-item__time">{{ formatDate(entry.created_at) }}</span>
      </div>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
      <span class="pagination__info">{{ currentPage }} / {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
    </div>
  </div>
</template>

<style scoped>
.admin-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.75rem; gap: 1rem; }
.admin-eyebrow { font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .3rem; }
.admin-page-title { font-size: 1.5rem; font-weight: 700; color: var(--color-seashell); display: flex; align-items: baseline; gap: .75rem; }
.admin-count { font-size: .85rem; color: var(--color-seashell-muted); font-weight: 400; }
.admin-toolbar { display: flex; gap: .75rem; margin-bottom: 1.25rem; }
.admin-toolbar__select { width: auto; min-width: 220px; }

.activity-card {
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
}

.log-item {
  display: flex; align-items: flex-start; gap: .875rem;
  padding: .875rem 1.25rem;
  border-bottom: 1px solid var(--color-balsamico-border);
  transition: background var(--transition-fast);
}
.log-item:last-child { border-bottom: none; }
.log-item:hover { background: rgba(254,243,238,.02); }

.log-item__dot {
  width: 8px; height: 8px; border-radius: 50%;
  flex-shrink: 0; margin-top: .45rem;
}

.log-item__body { flex: 1; display: flex; flex-direction: column; gap: .3rem; }
.log-item__desc { font-size: .875rem; color: var(--color-seashell-strong); line-height: 1.4; }
.log-item__meta { display: flex; align-items: center; gap: .625rem; flex-wrap: wrap; }

.log-item__link {
  font-size: .72rem; font-weight: 500;
  color: var(--color-seashell-muted);
  transition: color var(--transition-fast);
}
.log-item__link:hover { color: var(--color-spice-market); }

.log-item__time {
  flex-shrink: 0; font-size: .72rem;
  color: rgba(254,243,238,.25);
  white-space: nowrap; padding-top: .15rem;
}

.empty-cell { padding: 3rem; text-align: center; color: var(--color-seashell-muted); }
.pagination__info { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .08em; text-transform: uppercase; }
</style>
