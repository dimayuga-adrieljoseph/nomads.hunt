<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
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
  } finally { loading.value = false }
}

onMounted(() => load())
</script>

<template>
  <div class="page-content">
    <div class="container">

      <div class="page-header">
        <div>
          <p class="page-header__eyebrow">/ Account</p>
          <h1 class="page-header__title display">My Orders</h1>
        </div>
        <RouterLink to="/catalog" class="btn btn--ghost btn--sm">Browse Rack</RouterLink>
      </div>

      <div v-if="loading" class="spinner" />

      <template v-else>
        <div v-if="orders.length === 0" class="page-empty">
          <p>No orders yet.</p>
          <RouterLink to="/catalog" class="btn btn--primary btn--sm">Browse Rack →</RouterLink>
        </div>

        <div v-else class="orders-list">
          <div v-for="o in orders" :key="o.id" class="order-item">
            <RouterLink :to="`/products/${o.product?.id ?? ''}`" class="order-item__img-wrap" :aria-label="o.product?.name ?? 'Product'">
              <img v-if="o.product?.image_url" :src="o.product.image_url" :alt="o.product?.name ?? 'Product'" class="order-item__img" />
              <div v-else class="order-item__img-placeholder" />
            </RouterLink>

            <div class="order-item__body">
              <span class="order-item__num">Order #{{ o.order_number }}</span>
              <RouterLink :to="`/orders/${o.id}`" class="order-item__name">
                {{ o.product?.name ?? `Order #${o.id}` }}
              </RouterLink>
              <div class="order-item__meta">
                <span :class="['badge', `badge--${o.claim_type}`]">{{ o.claim_type.toUpperCase() }}</span>
                <span :class="['badge', o.payment_status === 'paid' ? 'badge--paid' : `badge--${o.payment_status}`]">
                  {{ o.payment_status.toUpperCase() }}
                </span>
              </div>
              <span class="order-item__date">{{ formatDate(o.created_at) }}</span>
            </div>

            <div class="order-item__right">
              <span class="order-item__amount">{{ formatPeso(o.amount) }}</span>
              <div class="order-item__actions">
                <RouterLink v-if="o.payment_status === 'pending'" :to="`/orders/${o.id}/pay`" class="btn btn--primary btn--sm">Pay →</RouterLink>
                <RouterLink v-else-if="o.payment_status === 'paid'" :to="`/receipt/${o.id}`" class="btn btn--ghost btn--sm">Receipt</RouterLink>
                <RouterLink v-else :to="`/orders/${o.id}`" class="btn btn--ghost btn--sm">Details</RouterLink>
              </div>
            </div>
          </div>
        </div>

        <div v-if="lastPage > 1" class="pagination">
          <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
          <span class="pagination__info">{{ currentPage }} / {{ lastPage }}</span>
          <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 3rem; padding-bottom: 2rem;
  border-bottom: 1px solid var(--color-balsamico-border); gap: 1rem; flex-wrap: wrap;
}
.page-header__eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .5rem; }
.page-header__title   { font-size: clamp(2rem, 5vw, 3.5rem); color: var(--color-seashell); }

.orders-list { display: flex; flex-direction: column; gap: 1px; }

.order-item {
  display: flex; align-items: center; gap: 1.25rem;
  padding: 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  transition: border-color var(--transition-fast);
}
.order-item:hover { border-color: var(--color-spice-border); }

.order-item__img-wrap {
  width: 72px; height: 72px; flex-shrink: 0;
  overflow: hidden; background: var(--color-balsamico-lighter);
  border: 1px solid var(--color-balsamico-border); display: block;
}
.order-item__img { width: 100%; height: 100%; object-fit: cover; }
.order-item__img-placeholder { width: 100%; height: 100%; }

.order-item__body { flex: 1; display: flex; flex-direction: column; gap: .375rem; min-width: 0; }
.order-item__num  { font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; color: rgba(254,243,238,.3); }
.order-item__name {
  font-weight: 600; font-size: .95rem; color: var(--color-seashell);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  text-decoration: none; transition: color var(--transition-fast);
}
.order-item__name:hover { color: var(--color-spice-market); }
.order-item__meta { display: flex; gap: .5rem; flex-wrap: wrap; }
.order-item__date { font-size: .72rem; color: rgba(254,243,238,.3); }

.order-item__right { flex-shrink: 0; text-align: right; display: flex; flex-direction: column; gap: .5rem; align-items: flex-end; }
.order-item__amount { font-size: 1.1rem; font-weight: 700; color: var(--color-seashell); }

.pagination__info { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .08em; text-transform: uppercase; }
.page-empty { text-align: center; padding: 6rem 1rem; color: var(--color-seashell-muted); display: flex; flex-direction: column; align-items: center; gap: 1.25rem; }
</style>
