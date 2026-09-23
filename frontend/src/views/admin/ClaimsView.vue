<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { claimService } from '@/services/claim.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

interface AdminClaim {
  id: number
  product_id: number
  type: 'mine' | 'steal' | 'grab'
  position: number
  status: string
  amount: number
  expires_at: string | null
  created_at: string
  user: { id: number; name: string; email: string } | null
  product: { id: number; name: string; status: string } | null
}

const claims      = ref<AdminClaim[]>([])
const loading     = ref(true)
const typeFilter  = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

async function load(page = 1) {
  loading.value = true
  try {
    const res = await claimService.adminList({
      type:   typeFilter.value  || undefined,
      status: statusFilter.value || undefined,
      page,
    })
    claims.value      = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
    total.value       = res.meta.total
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <h1 class="admin-page-title">Claims</h1>
        <p class="admin-page-sub">{{ total }} total</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="toolbar card">
      <select v-model="typeFilter" class="form-select toolbar__select" @change="load(1)">
        <option value="">All Types</option>
        <option value="mine">Mine</option>
        <option value="steal">Steal</option>
        <option value="grab">Grab</option>
      </select>
      <select v-model="statusFilter" class="form-select toolbar__select" @change="load(1)">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="waiting">Waiting</option>
        <option value="expired">Expired</option>
        <option value="completed">Completed</option>
        <option value="overridden">Overridden</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button class="btn btn--ghost btn--sm" @click="load(currentPage)">↻ Refresh</button>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="card table-wrap">
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
              <RouterLink
                :to="`/admin/products/${c.product_id}/claims`"
                class="table-link"
              >
                {{ c.product?.name ?? `#${c.product_id}` }}
              </RouterLink>
            </td>
            <td>
              <RouterLink
                :to="`/admin/customers/${c.user?.id}`"
                class="table-link"
              >
                {{ c.user?.name ?? '—' }}
              </RouterLink>
            </td>
            <td>
              <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
            </td>
            <td style="color:var(--color-muted)">#{{ c.position }}</td>
            <td>
              <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
            </td>
            <td>{{ formatPeso(c.amount) }}</td>
            <td>
              <ClaimTimer
                v-if="c.status === 'active' && c.expires_at"
                :expires-at="c.expires_at"
                style="font-size:.85rem"
                @expired="load(currentPage)"
              />
              <span v-else style="color:var(--color-muted);font-size:.8rem">—</span>
            </td>
            <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">
              {{ formatDate(c.created_at) }}
            </td>
            <td>
              <RouterLink
                :to="`/admin/products/${c.product_id}/claims`"
                class="btn btn--ghost btn--sm"
              >
                View Queue
              </RouterLink>
            </td>
          </tr>
          <tr v-if="claims.length === 0">
            <td colspan="9" style="text-align:center;color:var(--color-muted);padding:2rem">
              No claims found.
            </td>
          </tr>
        </tbody>
      </table>
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
.toolbar { display:flex;gap:.75rem;align-items:center;padding:.875rem 1rem;margin-bottom:1.25rem;flex-wrap:wrap; }
.toolbar__select { width:auto;min-width:140px; }
.table-link { color:var(--color-text);font-weight:500;font-size:.875rem; }
.table-link:hover { text-decoration:underline; }
</style>
