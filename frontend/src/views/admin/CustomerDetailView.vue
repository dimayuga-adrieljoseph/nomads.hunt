<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminService } from '@/services/admin.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface Order {
  id: number
  order_number: string
  amount: number
  claim_type: string
  payment_status: string
  status: string
  paid_at: string | null
  created_at: string
  product: { id: number; name: string; image_url: string | null; status: string } | null
}

interface Claim {
  id: number
  type: string
  status: string
  amount: number
  position: number
  expires_at: string | null
  created_at: string
  product: { id: number; name: string; status: string } | null
}

interface CustomerDetail {
  id: number
  name: string
  email: string
  created_at: string
  total_spent: number
  orders: Order[]
  claims: Claim[]
}

const route    = useRoute()
const router   = useRouter()
const customer = ref<CustomerDetail | null>(null)
const loading  = ref(true)
const tab      = ref<'orders' | 'claims'>('orders')

onMounted(async () => {
  try {
    customer.value = await adminService.customer(Number(route.params.id))
  } catch {
    router.push('/admin/customers')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <RouterLink to="/admin/customers" class="back-link">← Customers</RouterLink>
        <h1 class="admin-page-title">{{ customer?.name ?? '…' }}</h1>
      </div>
    </div>

    <div v-if="loading" class="spinner" />

    <template v-else-if="customer">
      <!-- Profile card -->
      <div class="profile-card card">
        <div class="profile-card__avatar">{{ customer.name.charAt(0).toUpperCase() }}</div>
        <div class="profile-card__info">
          <div class="profile-card__name">{{ customer.name }}</div>
          <div class="profile-card__email">{{ customer.email }}</div>
          <div class="profile-card__meta">
            Joined {{ formatDate(customer.created_at) }}
          </div>
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
            <div class="profile-stat__value profile-stat__value--green">{{ formatPeso(customer.total_spent) }}</div>
            <div class="profile-stat__label">Total Spent</div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button :class="['tab', { 'tab--active': tab === 'orders' }]" @click="tab = 'orders'">
          Orders ({{ customer.orders.length }})
        </button>
        <button :class="['tab', { 'tab--active': tab === 'claims' }]" @click="tab = 'claims'">
          Claims ({{ customer.claims.length }})
        </button>
      </div>

      <!-- Orders tab -->
      <div v-if="tab === 'orders'" class="card table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order #</th>
              <th>Product</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in customer.orders" :key="o.id">
              <td style="font-weight:600">#{{ o.order_number }}</td>
              <td>
                <div class="product-cell">
                  <img v-if="o.product?.image_url" :src="o.product.image_url" class="product-cell__img" />
                  <div v-else class="product-cell__img-placeholder" />
                  <RouterLink :to="`/admin/products/${o.product?.id}/claims`" class="table-link">
                    {{ o.product?.name ?? '—' }}
                  </RouterLink>
                </div>
              </td>
              <td><span :class="['badge', `badge--${o.claim_type}`]">{{ o.claim_type.toUpperCase() }}</span></td>
              <td style="font-weight:600">{{ formatPeso(o.amount) }}</td>
              <td>
                <span :class="['badge', o.payment_status === 'paid' ? 'badge--paid' : `badge--${o.payment_status}`]">
                  {{ o.payment_status.toUpperCase() }}
                </span>
              </td>
              <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">{{ formatDate(o.created_at) }}</td>
            </tr>
            <tr v-if="customer.orders.length === 0">
              <td colspan="6" style="text-align:center;color:var(--color-muted);padding:2rem">No orders yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Claims tab -->
      <div v-if="tab === 'claims'" class="card table-wrap">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Type</th>
              <th>#</th>
              <th>Status</th>
              <th>Amount</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in customer.claims" :key="c.id">
              <td>
                <RouterLink :to="`/admin/products/${c.product?.id}/claims`" class="table-link">
                  {{ c.product?.name ?? '—' }}
                </RouterLink>
              </td>
              <td><span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span></td>
              <td style="color:var(--color-muted)">#{{ c.position }}</td>
              <td><span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span></td>
              <td>{{ formatPeso(c.amount) }}</td>
              <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">{{ formatDate(c.created_at) }}</td>
            </tr>
            <tr v-if="customer.claims.length === 0">
              <td colspan="6" style="text-align:center;color:var(--color-muted);padding:2rem">No claims yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-page-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem; }
.admin-page-title  { font-size:1.5rem;font-weight:700;margin-top:.25rem; }
.back-link { font-size:.8rem;color:var(--color-muted);display:inline-block;margin-bottom:.375rem; }
.back-link:hover { color:var(--color-text); }

.profile-card {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.profile-card__avatar {
  width: 60px; height: 60px;
  border-radius: 50%;
  background: var(--color-surface-2);
  border: 1px solid var(--color-border);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; font-weight: 700;
  flex-shrink: 0;
}
.profile-card__info   { flex: 1; min-width: 180px; }
.profile-card__name   { font-size: 1.1rem; font-weight: 700; }
.profile-card__email  { font-size: .875rem; color: var(--color-muted); margin-top: .2rem; }
.profile-card__meta   { font-size: .75rem; color: var(--color-muted); margin-top: .375rem; }

.profile-card__stats  { display: flex; gap: 2rem; flex-wrap: wrap; }
.profile-stat         { text-align: center; }
.profile-stat__value  { font-size: 1.5rem; font-weight: 700; }
.profile-stat__value--green { color: var(--color-grab); }
.profile-stat__label  { font-size: .7rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: .06em; margin-top: .2rem; }

.tabs { display: flex; gap: 0; margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); }
.tab {
  padding: .625rem 1.25rem;
  font-size: .875rem;
  font-weight: 500;
  color: var(--color-muted);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: color .15s, border-color .15s;
}
.tab:hover    { color: var(--color-text); }
.tab--active  { color: var(--color-text); border-bottom-color: var(--color-mine); }

.product-cell { display:flex;align-items:center;gap:.5rem; }
.product-cell__img { width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0; }
.product-cell__img-placeholder { width:32px;height:32px;background:var(--color-surface-2);border-radius:4px;flex-shrink:0; }
.table-link { color:var(--color-text);font-weight:500;font-size:.875rem; }
.table-link:hover { text-decoration:underline; }
</style>
