<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { orderService } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface AdminOrder {
  id: number
  order_number: string
  amount: number
  claim_type: string
  payment_status: string
  status: string
  paid_at: string | null
  expires_at: string | null
  created_at: string
  user: { id: number; name: string; email: string } | null
  product: { id: number; name: string; status: string } | null
  claim: { id: number; type: string; status: string } | null
}

const orders          = ref<AdminOrder[]>([])
const loading         = ref(true)
const paymentFilter   = ref('')
const typeFilter      = ref('')
const currentPage     = ref(1)
const lastPage        = ref(1)
const total           = ref(0)

async function load(page = 1) {
  loading.value = true
  try {
    const res = await orderService.adminList({
      payment_status: paymentFilter.value || undefined,
      claim_type:     typeFilter.value    || undefined,
      page,
    })
    orders.value      = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
    total.value       = res.meta.total
  } finally {
    loading.value = false
  }
}

function paymentBadge(s: string) {
  const m: Record<string, string> = { paid: 'badge--paid', pending: 'badge--pending', expired: 'badge--expired', cancelled: 'badge--cancelled' }
  return m[s] ?? 'badge--default'
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <h1 class="admin-page-title">Orders</h1>
        <p class="admin-page-sub">{{ total }} total</p>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load(currentPage)">↻ Refresh</button>
    </div>

    <!-- Filters -->
    <div class="toolbar card">
      <select v-model="paymentFilter" class="form-select toolbar__select" @change="load(1)">
        <option value="">All Payments</option>
        <option value="pending">Pending</option>
        <option value="paid">Paid</option>
        <option value="expired">Expired</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select v-model="typeFilter" class="form-select toolbar__select" @change="load(1)">
        <option value="">All Types</option>
        <option value="mine">Mine</option>
        <option value="steal">Steal</option>
        <option value="grab">Grab</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="card table-wrap">
      <table>
        <thead>
          <tr>
            <th>Order #</th>
            <th>Product</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Payment</th>
            <th>Paid At</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id">
            <td style="font-weight:600;font-size:.875rem">#{{ o.order_number }}</td>
            <td>
              <RouterLink
                :to="`/admin/products/${o.product?.id}/claims`"
                class="table-link"
              >
                {{ o.product?.name ?? `#${o.id}` }}
              </RouterLink>
            </td>
            <td>
              <RouterLink
                :to="`/admin/customers/${o.user?.id}`"
                class="table-link"
              >
                {{ o.user?.name ?? '—' }}
              </RouterLink>
            </td>
            <td>
              <span :class="['badge', `badge--${o.claim_type}`]">{{ o.claim_type.toUpperCase() }}</span>
            </td>
            <td style="font-weight:600">{{ formatPeso(o.amount) }}</td>
            <td>
              <span :class="['badge', paymentBadge(o.payment_status)]">
                {{ o.payment_status.toUpperCase() }}
              </span>
            </td>
            <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">
              {{ o.paid_at ? formatDate(o.paid_at) : '—' }}
            </td>
            <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">
              {{ formatDate(o.created_at) }}
            </td>
          </tr>
          <tr v-if="orders.length === 0">
            <td colspan="8" style="text-align:center;color:var(--color-muted);padding:2rem">
              No orders found.
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
