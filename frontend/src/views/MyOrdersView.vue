<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

const orders      = ref<Order[]>([])
const loading     = ref(true)
const currentPage = ref(1)
const lastPage    = ref(1)

async function load(page = 1) {
  loading.value = true
  try {
    const res = await orderService.myOrders(page)
    orders.value      = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="page-content">
    <div class="container">
      <h1 style="margin-bottom:2rem">My Orders</h1>

      <div v-if="loading" class="spinner" />

      <template v-else>
        <div v-if="orders.length === 0" class="empty-state">
          <p>No orders yet.</p>
          <RouterLink to="/" class="btn btn--primary btn--sm">Browse Catalog</RouterLink>
        </div>

        <div v-else class="orders-list">
          <div v-for="o in orders" :key="o.id" class="order-item card">
            <div class="order-item__img-wrap">
              <img v-if="o.product?.image_url" :src="o.product.image_url" class="order-item__img" />
              <div v-else class="order-item__img-placeholder" />
            </div>

            <div class="order-item__body">
              <div class="order-item__num">Order #{{ o.order_number }}</div>
              <div class="order-item__name">{{ o.product?.name ?? `Order #${o.id}` }}</div>
              <div class="order-item__meta">
                <span :class="['badge', `badge--${o.claim_type}`]">{{ o.claim_type.toUpperCase() }}</span>
                <span :class="['badge', o.payment_status === 'paid' ? 'badge--paid' : `badge--${o.payment_status}`]">
                  {{ o.payment_status.toUpperCase() }}
                </span>
              </div>
              <div class="order-item__date">{{ formatDate(o.created_at) }}</div>
            </div>

            <div class="order-item__right">
              <div class="order-item__amount">{{ formatPeso(o.amount) }}</div>
              <div class="order-item__actions">
                <RouterLink
                  v-if="o.payment_status === 'pending'"
                  :to="`/orders/${o.id}/pay`"
                  class="btn btn--primary btn--sm"
                >Pay</RouterLink>
                <RouterLink
                  v-else-if="o.payment_status === 'paid'"
                  :to="`/receipt/${o.id}`"
                  class="btn btn--ghost btn--sm"
                >Receipt</RouterLink>
                <RouterLink
                  v-else
                  :to="`/orders/${o.id}`"
                  class="btn btn--ghost btn--sm"
                >Details</RouterLink>
              </div>
            </div>
          </div>
        </div>

        <div v-if="lastPage > 1" class="pagination">
          <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
          <span style="font-size:.85rem;color:var(--color-muted)">{{ currentPage }} / {{ lastPage }}</span>
          <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.orders-list { display: flex; flex-direction: column; gap: .75rem; }

.order-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; }

.order-item__img-wrap { width: 72px; height: 72px; flex-shrink: 0; border-radius: var(--radius); overflow: hidden; background: var(--color-surface-2); }
.order-item__img { width: 100%; height: 100%; object-fit: cover; }
.order-item__img-placeholder { width: 100%; height: 100%; }

.order-item__body { flex: 1; display: flex; flex-direction: column; gap: .375rem; min-width: 0; }
.order-item__num  { font-size: .75rem; color: var(--color-muted); }
.order-item__name { font-weight: 600; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.order-item__meta { display: flex; gap: .5rem; flex-wrap: wrap; }
.order-item__date { font-size: .75rem; color: var(--color-muted); }

.order-item__right { flex-shrink: 0; text-align: right; display: flex; flex-direction: column; gap: .5rem; align-items: flex-end; }
.order-item__amount { font-size: 1.1rem; font-weight: 700; }

.empty-state { text-align: center; padding: 4rem 1rem; color: var(--color-muted); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
</style>
