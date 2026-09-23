<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminService } from '@/services/admin.service'
import { formatDate } from '@/utils/formatters'

interface LogEntry {
  id: number
  action: string
  description: string
  claim_id: number | null
  user: { id: number; name: string } | null
  product: { id: number; name: string } | null
  created_at: string
}

const logs        = ref<LogEntry[]>([])
const loading     = ref(true)
const actionFilter = ref('')
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

const ACTIONS = [
  'PRODUCT_CREATED', 'PRODUCT_UPDATED',
  'MINE_CLAIMED', 'STEAL_CLAIMED', 'GRAB_CLAIMED',
  'CLAIM_ACTIVATED', 'CLAIM_EXPIRED', 'CLAIM_OVERRIDDEN',
  'PAYMENT_CONFIRMED', 'PRODUCT_SOLD',
]

async function load(page = 1) {
  loading.value = true
  try {
    const res = await adminService.activityLogs({
      action: actionFilter.value || undefined,
      page,
    })
    logs.value        = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
    total.value       = res.meta.total
  } finally {
    loading.value = false
  }
}

function dotColor(action: string): string {
  if (action.includes('SOLD') || action.includes('PAYMENT')) return 'var(--color-grab)'
  if (action.includes('STEAL'))    return 'var(--color-steal)'
  if (action.includes('MINE'))     return 'var(--color-mine)'
  if (action.includes('GRAB'))     return 'var(--color-grab)'
  if (action.includes('EXPIRED') || action.includes('OVERRIDDEN')) return '#f87171'
  if (action.includes('ACTIVATED')) return '#34d399'
  return 'var(--color-muted)'
}

function badgeClass(action: string): string {
  if (action.includes('SOLD') || action.includes('PAYMENT')) return 'badge--paid'
  if (action.includes('STEAL'))    return 'badge--steal'
  if (action.includes('MINE'))     return 'badge--mine'
  if (action.includes('GRAB'))     return 'badge--grab'
  if (action.includes('EXPIRED') || action.includes('OVERRIDDEN')) return 'badge--expired'
  if (action.includes('ACTIVATED')) return 'badge--active'
  return 'badge--default'
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <h1 class="admin-page-title">Activity Log</h1>
        <p class="admin-page-sub">{{ total }} events</p>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load(1)">↻ Refresh</button>
    </div>

    <!-- Filter -->
    <div class="toolbar card">
      <select v-model="actionFilter" class="form-select toolbar__select" @change="load(1)">
        <option value="">All Actions</option>
        <option v-for="a in ACTIONS" :key="a" :value="a">{{ a }}</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="card">
      <div v-if="logs.length === 0" class="empty-state-sm">No activity recorded yet.</div>

      <div v-for="entry in logs" :key="entry.id" class="log-item">
        <!-- Color dot -->
        <span class="log-item__dot" :style="{ background: dotColor(entry.action) }" />

        <!-- Body -->
        <div class="log-item__body">
          <div class="log-item__desc">{{ entry.description }}</div>
          <div class="log-item__meta">
            <span :class="['badge', badgeClass(entry.action)]" style="font-size:.65rem">
              {{ entry.action }}
            </span>
            <RouterLink
              v-if="entry.user"
              :to="`/admin/customers/${entry.user.id}`"
              class="log-item__link"
            >
              {{ entry.user.name }}
            </RouterLink>
            <RouterLink
              v-if="entry.product"
              :to="`/admin/products/${entry.product.id}/claims`"
              class="log-item__link"
            >
              {{ entry.product.name }}
            </RouterLink>
          </div>
        </div>

        <!-- Timestamp -->
        <div class="log-item__time">{{ formatDate(entry.created_at) }}</div>
      </div>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
      <span style="font-size:.85rem;color:var(--color-muted)">{{ currentPage }} / {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
    </div>
  </div>
</template>

<style scoped>
.admin-page-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem; }
.admin-page-title  { font-size:1.5rem;font-weight:700; }
.admin-page-sub    { font-size:.8rem;color:var(--color-muted);margin-top:.2rem; }
.toolbar { display:flex;gap:.75rem;padding:.875rem 1rem;margin-bottom:1.25rem; }
.toolbar__select { width:auto;min-width:200px; }

.log-item {
  display: flex;
  align-items: flex-start;
  gap: .875rem;
  padding: .875rem 1.25rem;
  border-bottom: 1px solid var(--color-border);
}
.log-item:last-child { border-bottom: none; }

.log-item__dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: .45rem;
}

.log-item__body { flex: 1; display: flex; flex-direction: column; gap: .3rem; }
.log-item__desc { font-size: .875rem; }
.log-item__meta {
  display: flex;
  align-items: center;
  gap: .625rem;
  flex-wrap: wrap;
}
.log-item__link {
  font-size: .75rem;
  color: var(--color-muted);
  font-weight: 500;
}
.log-item__link:hover { color: var(--color-text); text-decoration: underline; }

.log-item__time {
  flex-shrink: 0;
  font-size: .75rem;
  color: var(--color-muted);
  white-space: nowrap;
  padding-top: .125rem;
}

.empty-state-sm { padding: 2rem; text-align: center; color: var(--color-muted); font-size: .875rem; }
</style>
