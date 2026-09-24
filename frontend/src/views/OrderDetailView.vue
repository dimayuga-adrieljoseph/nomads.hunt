<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

const route   = useRoute()
const router  = useRouter()
const order   = ref<Order | null>(null)
const loading = ref(true)

onMounted(async () => {
  try { order.value = await orderService.get(Number(route.params.id)) }
  catch { router.push('/my-orders') }
  finally { loading.value = false }
})
</script>

<template>
  <div class="page-content">
    <div class="container" style="max-width: 600px">

      <RouterLink to="/my-orders" class="back-link">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
        My Orders
      </RouterLink>

      <div v-if="loading" class="spinner" />

      <div v-else-if="order" class="order-detail">
        <div class="order-detail__header">
          <h1 class="order-detail__num">Order #{{ order.order_number }}</h1>
          <span :class="['badge', order.payment_status === 'paid' ? 'badge--paid' : `badge--${order.payment_status}`]">
            {{ order.payment_status.toUpperCase() }}
          </span>
        </div>

        <!-- Product -->
        <div v-if="order.product" class="order-detail__product">
          <div class="order-detail__product-img-wrap">
            <img v-if="order.product.image_url" :src="order.product.image_url" :alt="order.product.name" class="order-detail__product-img" />
          </div>
          <div class="order-detail__product-info">
            <span class="order-detail__product-name">{{ order.product.name }}</span>
            <span :class="['badge', `badge--${order.claim_type}`]" style="margin-top:.375rem">{{ order.claim_type.toUpperCase() }}</span>
          </div>
        </div>

        <div class="order-detail__divider" />

        <div class="order-detail__rows">
          <div class="order-detail__row">
            <span class="order-detail__row-label">Amount</span>
            <strong class="order-detail__row-amount">{{ formatPeso(order.amount) }}</strong>
          </div>
          <div class="order-detail__row">
            <span class="order-detail__row-label">Status</span>
            <span class="order-detail__row-value">{{ order.status }}</span>
          </div>
          <div v-if="order.paid_at" class="order-detail__row">
            <span class="order-detail__row-label">Paid at</span>
            <span class="order-detail__row-value">{{ formatDate(order.paid_at) }}</span>
          </div>
          <div class="order-detail__row">
            <span class="order-detail__row-label">Created</span>
            <span class="order-detail__row-value">{{ formatDate(order.created_at) }}</span>
          </div>
        </div>

        <div class="order-detail__divider" />

        <div class="order-detail__actions">
          <RouterLink
            v-if="order.payment_status === 'pending'"
            :to="`/orders/${order.id}/pay`"
            class="btn btn--primary btn--full"
          >Pay Now →</RouterLink>
          <RouterLink
            v-if="order.payment_status === 'paid'"
            :to="`/receipt/${order.id}`"
            class="btn btn--ghost btn--full"
          >View Receipt</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.back-link {
  display: inline-flex; align-items: center; gap: .4rem;
  font-size: .75rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
  color: var(--color-seashell-muted); margin-bottom: 2.5rem;
  transition: color var(--transition-fast);
}
.back-link:hover { color: var(--color-spice-market); }

.order-detail {
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  padding: 2rem;
  display: flex; flex-direction: column; gap: 1.5rem;
}
.order-detail__header { display: flex; justify-content: space-between; align-items: center; }
.order-detail__num { font-size: 1.25rem; font-weight: 700; color: var(--color-seashell); }

.order-detail__product { display: flex; gap: 1rem; align-items: center; }
.order-detail__product-img-wrap { width: 72px; height: 72px; flex-shrink: 0; overflow: hidden; background: var(--color-balsamico-lighter); }
.order-detail__product-img { width: 100%; height: 100%; object-fit: cover; }
.order-detail__product-name { font-weight: 600; font-size: .95rem; color: var(--color-seashell); display: block; }

.order-detail__divider { border: none; border-top: 1px solid var(--color-balsamico-border); }

.order-detail__rows { display: flex; flex-direction: column; gap: .875rem; }
.order-detail__row { display: flex; justify-content: space-between; align-items: center; }
.order-detail__row-label { font-size: .78rem; color: var(--color-seashell-muted); }
.order-detail__row-value { font-size: .875rem; color: var(--color-seashell-strong); }
.order-detail__row-amount { font-size: 1.25rem; font-weight: 700; color: var(--color-seashell); }

.order-detail__actions { display: flex; flex-direction: column; gap: .75rem; }
</style>
