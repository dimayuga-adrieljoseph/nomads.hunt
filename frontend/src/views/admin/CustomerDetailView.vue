<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { adminService } from '@/services/admin.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface Order {
  id: number; order_number: string; amount: number; claim_type: string
  payment_status: string; status: string; paid_at: string | null; created_at: string
  product: { id: number; name: string; image_url: string | null; status: string } | null
}
interface Claim {
  id: number; type: string; status: string; amount: number
  position: number; expires_at: string | null; created_at: string
  phase: 'claim' | 'payment' | null
  claim_expires_at: string | null
  payment_starts_at: string | null
  payment_expires_at: string | null
  can_pay: boolean
  product: { id: number; name: string; status: string } | null
}
interface CustomerDetail {
  id: number; name: string; email: string; created_at: string
  total_spent: number; orders: Order[]; claims: Claim[]
}

const route    = useRoute()
const router   = useRouter()
const customer = ref<CustomerDetail | null>(null)
const loading  = ref(true)
const tab      = ref<'orders' | 'claims'>('orders')

onMounted(async () => {
  try { customer.value = await adminService.customer(Number(route.params.id)) }
  catch { router.push('/admin/customers') }
  finally { loading.value = false }
})
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin / Customers</p>
        <RouterLink to="/admin/customers" class="back-link">← Customers</RouterLink>
        <h1 class="admin-page-title">{{ customer?.name ?? '…' }}</h1>
      </div>
    </div>

    <div v-if="loading" class="spinner" />

    <template v-else-if="customer">
      <!-- Profile card -->
      <div class="profile-card">
        <div class="profile-card__avatar" aria-hidden="true">
          {{ customer.name.charAt(0).toUpperCase() }}
        </div>
        <div class="profile-card__info">
          <div class="profile-card__name">{{ customer.name }}</div>
          <div class="profile-card__email">{{ customer.email }}</div>
          <div class="profile-card__joined">Joined {{ formatDate(customer.created_at) }}</div>
        </div>
        <div class="profile-card__stats">
          <div class="profile-stat">
            <div class="profile-stat__value">{{ customer.orders.length }}</div>
            <div class="profile-stat__label">Orders</div>
          </div>
          <div class="profile-stat">
            <div class="profile-stat__value">{{ customer.claims.length }}</div>
            <div class="profile-stat__label">Claims</div>
          </div>
          <div class="profile-stat">
            <div class="profile-stat__value profile-stat__value--spice">{{ formatPeso(customer.total_spent) }}</div>
            <div class="profile-stat__label">Total Spent</div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs" role="tablist">
        <button
          role="tab"
          :aria-selected="tab === 'orders'"
          :class="['tab', { 'tab--active': tab === 'orders' }]"
          @click="tab = 'orders'"
        >Orders ({{ customer.orders.length }})</button>
        <button
          role="tab"
          :aria-selected="tab === 'claims'"
          :class="['tab', { 'tab--active': tab === 'claims' }]"
          @click="tab = 'claims'"
        >Claims ({{ customer.claims.length }})</button>
      </div>

      <!-- Orders tab -->
      <div v-if="tab === 'orders'" class="table-wrap admin-table-card" role="tabpanel">
        <table>
          <thead><tr><th>Order #</th><th>Product</th><th>Type</th><th>Amount</th><th>Payment</th><th>Date</th></tr></thead>
          <tbody>
            <tr v-for="o in customer.orders" :key="o.id">
              <td class="td-mono">#{{ o.order_number }}</td>
              <td>
                <div class="product-cell">
                  <div class="product-cell__img-wrap">
                    <img v-if="o.product?.image_url" :src="o.product.image_url" :alt="o.product?.name ?? ''" class="product-cell__img" />
                    <div v-else class="product-cell__img-placeholder" />
                  </div>
                  <RouterLink v-if="o.product" :to="`/admin/products/${o.product.id}/claims`" class="table-link">
                    {{ o.product.name }}
                  </RouterLink>
                  <span v-else class="td-muted">—</span>
                </div>
              </td>
              <td><span :class="['badge', `badge--${o.claim_type}`]">{{ o.claim_type.toUpperCase() }}</span></td>
              <td class="td-price">{{ formatPeso(o.amount) }}</td>
              <td>
                <span :class="['badge', o.payment_status === 'paid' ? 'badge--paid' : `badge--${o.payment_status}`]">
                  {{ o.payment_status.toUpperCase() }}
                </span>
              </td>
              <td class="td-date">{{ formatDate(o.created_at) }}</td>
            </tr>
            <tr v-if="customer.orders.length === 0"><td colspan="6" class="empty-cell">No orders yet.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Claims tab -->
      <div v-if="tab === 'claims'" class="table-wrap admin-table-card" role="tabpanel">
        <table>
          <thead><tr><th>Product</th><th>Type</th><th>#</th><th>Status</th><th>Amount</th><th>Claim Expires</th><th>Payment Expires</th><th>Date</th></tr></thead>
          <tbody>
            <tr v-for="c in customer.claims" :key="c.id">
              <td>
                <RouterLink v-if="c.product" :to="`/admin/products/${c.product.id}/claims`" class="table-link">
                  {{ c.product.name }}
                </RouterLink>
                <span v-else class="td-muted">—</span>
              </td>
              <td><span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span></td>
              <td class="td-muted">#{{ c.position }}</td>
              <td>
                <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
                <span v-if="c.status === 'active' && c.phase" class="badge badge--phase">
                  {{ c.phase === 'payment' ? 'payment' : 'claim' }}
                </span>
              </td>
              <td class="td-price">{{ formatPeso(c.amount) }}</td>
              <td class="td-date">{{ formatDate(c.claim_expires_at) }}</td>
              <td class="td-date">{{ formatDate(c.payment_expires_at) }}</td>
              <td class="td-date">{{ formatDate(c.created_at) }}</td>
            </tr>
            <tr v-if="customer.claims.length === 0"><td colspan="8" class="empty-cell">No claims yet.</td></tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-page-header { margin-bottom: 1.75rem; }
.admin-eyebrow { font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .25rem; }
.back-link { font-size: .75rem; color: var(--color-seashell-muted); display: inline-block; margin-bottom: .375rem; transition: color var(--transition-fast); }
.back-link:hover { color: var(--color-spice-market); }
.admin-page-title { font-size: 1.5rem; font-weight: 700; color: var(--color-seashell); }

.profile-card {
  display: flex; align-items: center; gap: 2rem;
  padding: 1.75rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  margin-bottom: 1.5rem; flex-wrap: wrap;
}
.profile-card__avatar {
  width: 60px; height: 60px; border-radius: 50%;
  background: var(--color-spice-dim); border: 1px solid var(--color-spice-border);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-size: 1.3rem; color: var(--color-spice-market); flex-shrink: 0;
}
.profile-card__info   { flex: 1; min-width: 180px; }
.profile-card__name   { font-size: 1.1rem; font-weight: 700; color: var(--color-seashell); margin-bottom: .2rem; }
.profile-card__email  { font-size: .85rem; color: var(--color-seashell-muted); }
.profile-card__joined { font-size: .72rem; color: rgba(254,243,238,.3); margin-top: .375rem; }
.profile-card__stats  { display: flex; gap: 2.5rem; flex-wrap: wrap; }
.profile-stat         { text-align: center; }
.profile-stat__value  { font-size: 1.5rem; font-weight: 700; line-height: 1; color: var(--color-seashell); }
.profile-stat__value--spice { color: var(--color-spice-market); }
.profile-stat__label  { font-size: .62rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--color-seashell-muted); margin-top: .375rem; }

.tabs {
  display: flex; gap: 0; margin-bottom: 1rem;
  border-bottom: 1px solid var(--color-balsamico-border);
}
.tab {
  padding: .7rem 1.5rem;
  font-family: var(--font-body); font-size: .8rem; font-weight: 600;
  letter-spacing: .08em; text-transform: uppercase;
  color: var(--color-seashell-muted);
  background: none; border: none; border-bottom: 2px solid transparent;
  margin-bottom: -1px; cursor: pointer; transition: all var(--transition-fast);
}
.tab:hover { color: var(--color-seashell); }
.tab--active { color: var(--color-seashell); border-bottom-color: var(--color-spice-market); }

.admin-table-card { background: var(--color-balsamico-light); border: 1px solid var(--color-balsamico-border); border-radius: var(--radius); }
.table-link { color: var(--color-seashell-strong); font-weight: 500; font-size: .875rem; transition: color var(--transition-fast); }
.table-link:hover { color: var(--color-spice-market); }
.product-cell { display: flex; align-items: center; gap: .625rem; }
.product-cell__img-wrap { width: 32px; height: 32px; flex-shrink: 0; overflow: hidden; background: var(--color-balsamico-lighter); }
.product-cell__img { width: 100%; height: 100%; object-fit: cover; }
.product-cell__img-placeholder { width: 100%; height: 100%; }
.td-muted  { color: var(--color-seashell-muted); font-size: .82rem; }
.td-date   { color: rgba(254,243,238,.3); font-size: .72rem; white-space: nowrap; }
.td-price  { font-weight: 600; font-size: .875rem; }
.td-mono   { font-weight: 600; font-size: .82rem; color: var(--color-seashell-strong); }
.empty-cell { text-align: center; color: var(--color-seashell-muted); padding: 2.5rem; }
</style>
