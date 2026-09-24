<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { claimService } from '@/services/claim.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

interface AdminClaim {
  id: number; product_id: number; type: 'mine' | 'steal' | 'grab'
  position: number; status: string; amount: number; expires_at: string | null; created_at: string
  phase: 'claim' | 'payment' | null
  claim_expires_at: string | null
  payment_starts_at: string | null
  payment_expires_at: string | null
  can_pay: boolean
  user: { id: number; name: string; email: string } | null
  product: { id: number; name: string; status: string } | null
}

const claims       = ref<AdminClaim[]>([])
const loading      = ref(true)
const loadError    = ref('')
const typeFilter   = ref('')
const statusFilter = ref('')
const currentPage  = ref(1)
const lastPage     = ref(1)
const total        = ref(0)

async function load(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const res = await claimService.adminList({
      type:   typeFilter.value   || undefined,
      status: statusFilter.value || undefined,
      page,
    })
    claims.value      = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
    total.value       = res.meta.total
  } catch {
    loadError.value = 'Failed to load claims. Please try again.'
  } finally { loading.value = false }
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin</p>
        <h1 class="admin-page-title">Claims <span class="admin-count">{{ total }}</span></h1>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load(currentPage)">↻ Refresh</button>
    </div>

    <!-- Filters -->
    <div class="admin-toolbar">
      <select v-model="typeFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Types</option>
        <option value="mine">Mine</option>
        <option value="steal">Steal</option>
        <option value="grab">Grab</option>
      </select>
      <select v-model="statusFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="waiting">Waiting</option>
        <option value="expired">Expired</option>
        <option value="completed">Completed</option>
        <option value="overridden">Overridden</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else-if="loadError" class="empty-cell" role="alert" style="padding: 3rem; text-align: center;">
      {{ loadError }}
    </div>

    <div v-else class="table-wrap admin-table-card">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Customer</th>
            <th>Type</th>
            <th>#</th>
            <th>Status</th>
            <th>Amount</th>
            <th>Expires</th>
            <th>Created</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in claims" :key="c.id">
            <td>
              <RouterLink :to="`/admin/products/${c.product_id}/claims`" class="table-link">
                {{ c.product?.name ?? `#${c.product_id}` }}
              </RouterLink>
            </td>
            <td>
              <RouterLink v-if="c.user" :to="`/admin/customers/${c.user.id}`" class="table-link">
                {{ c.user.name }}
              </RouterLink>
              <span v-else class="td-muted">—</span>
            </td>
            <td><span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span></td>
            <td class="td-muted">#{{ c.position }}</td>
            <td>
              <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
              <!-- Two-stage lifecycle: which stage is this claim in? -->
              <span v-if="c.status === 'active' && c.phase" class="badge badge--phase">
                {{ c.phase === 'payment' ? 'payment window' : 'claim period' }}
              </span>
            </td>
            <td class="td-price">{{ formatPeso(c.amount) }}</td>
            <td>
              <ClaimTimer
                v-if="c.status === 'active' && c.expires_at"
                :expires-at="c.expires_at"
                style="font-size:.82rem"
                @expired="load(currentPage)"
              />
              <span v-else class="td-muted">—</span>
            </td>
            <td class="td-date">{{ formatDate(c.created_at) }}</td>
            <td>
              <RouterLink :to="`/admin/products/${c.product_id}/claims`" class="btn btn--ghost btn--sm">
                Queue
              </RouterLink>
            </td>
          </tr>
          <tr v-if="claims.length === 0">
            <td colspan="9" class="empty-cell">No claims found.</td>
          </tr>
        </tbody>
      </table>
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
.admin-toolbar { display: flex; gap: .75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.admin-toolbar__select { width: auto; min-width: 150px; }
.admin-table-card { background: var(--color-balsamico-light); border: 1px solid var(--color-balsamico-border); border-radius: var(--radius); }
.table-link { color: var(--color-seashell-strong); font-weight: 500; font-size: .875rem; transition: color var(--transition-fast); }
.table-link:hover { color: var(--color-spice-market); }
.td-muted { color: var(--color-seashell-muted); font-size: .82rem; }
.td-date  { color: rgba(254,243,238,.3); font-size: .72rem; white-space: nowrap; }
.td-price { font-weight: 600; white-space: nowrap; font-size: .875rem; }
.empty-cell { text-align: center; color: var(--color-seashell-muted); padding: 3rem; }
.pagination__info { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .08em; text-transform: uppercase; }
</style>
