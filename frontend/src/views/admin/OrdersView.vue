<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { orderService } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface AdminOrder {
  id: number; order_number: string; amount: number; claim_type: string
  payment_status: string; status: string; paid_at: string | null
  expires_at: string | null; created_at: string
  user: { id: number; name: string; email: string } | null
  product: { id: number; name: string; status: string } | null
  claim: { id: number; type: string; status: string } | null
}

const orders        = ref<AdminOrder[]>([])
const loading       = ref(true)
const paymentFilter = ref('')
const typeFilter    = ref('')
const currentPage   = ref(1)
const lastPage      = ref(1)
const total         = ref(0)

async function load(page = 1) {
  loading.value = true
  try {
    const res = await orderService.adminList({
      payment_status: paymentFilter.value || undefined,
      claim_type:     typeFilter.value    || undefined,
      page,
    })
    orders.value = res.data; currentPage.value = res.meta.current_page
    lastPage.value = res.meta.last_page; total.value = res.meta.total
  } finally { loading.value = false }
}

function paymentBadge(s: string) {
  const m: Record<string,string> = { paid: 'badge--paid', pending: 'badge--pending', expired: 'badge--expired', cancelled: 'badge--cancelled' }
  return m[s] ?? 'badge--default'
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin</p>
        <h1 class="admin-page-title">Orders <span class="admin-count">{{ total }}</span></h1>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load(currentPage)">↻ Refresh</button>
    </div>

    <div class="admin-toolbar">
      <select v-model="paymentFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Payments</option>
        <option value="pending">Pending</option>
        <option value="paid">Paid</option>
        <option value="expired">Expired</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select v-model="typeFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Types</option>
        <option value="mine">Mine</option>
        <option value="steal">Steal</option>
        <option value="grab">Grab</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="table-wrap admin-table-card">
      <table>
        <thead>
          <tr>
            <th>Order #</th><th>Product</th><th>Customer</th>
            <th>Type</th><th>Amount</th><th>Payment</th>
            <th>Paid At</th><th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id">
            <td class="td-mono">#{{ o.order_number }}</td>
            <td>
              <RouterLink v-if="o.product" :to="`/admin/products/${o.product.id}/claims`" class="table-link">
                {{ o.product.name }}
              </RouterLink>
              <span v-else class="td-muted">—</span>
            </td>
            <td>
              <RouterLink v-if="o.user" :to="`/admin/customers/${o.user.id}`" class="table-link">
                {{ o.user.name }}
              </RouterLink>
              <span v-else class="td-muted">—</span>
            </td>
            <td><span :class="['badge', `badge--${o.claim_type}`]">{{ o.claim_type.toUpperCase() }}</span></td>
            <td class="td-price">{{ formatPeso(o.amount) }}</td>
            <td><span :class="['badge', paymentBadge(o.payment_status)]">{{ o.payment_status.toUpperCase() }}</span></td>
            <td class="td-date">{{ o.paid_at ? formatDate(o.paid_at) : '—' }}</td>
            <td class="td-date">{{ formatDate(o.created_at) }}</td>
          </tr>
          <tr v-if="orders.length === 0"><td colspan="8" class="empty-cell">No orders found.</td></tr>
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
.td-price { font-weight: 600; font-size: .875rem; }
.td-mono  { font-weight: 600; font-size: .82rem; color: var(--color-seashell-strong); }
.empty-cell { text-align: center; color: var(--color-seashell-muted); padding: 3rem; }
.pagination__info { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .08em; text-transform: uppercase; }
</style>
