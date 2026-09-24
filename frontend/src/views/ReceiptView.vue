<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

const route   = useRoute()
const order   = ref<Order | null>(null)
const loading = ref(true)
const error   = ref('')

onMounted(async () => {
  try { order.value = await orderService.get(Number(route.params.id)) }
  catch { error.value = 'Order not found.' }
  finally { loading.value = false }
})
</script>

<template>
  <div class="page-content">
    <div class="container" style="max-width: 480px">
      <div v-if="loading" class="spinner" />
      <div v-else-if="error" class="alert alert--error" role="alert">{{ error }}</div>

      <div v-else-if="order" class="receipt">
        <!-- Brand -->
        <RouterLink to="/" class="receipt__brand display" aria-label="NOMADS.HUNT">
          NOMADS<span>.</span>HUNT
        </RouterLink>

        <!-- Confirmed mark -->
        <div class="receipt__check" aria-hidden="true">✓</div>
        <h1 class="receipt__title display">Payment Confirmed</h1>
        <p class="receipt__order-num">RECEIPT — Order #{{ order.order_number }}</p>

        <div class="receipt__divider" />

        <!-- Product -->
        <div v-if="order.product" class="receipt__product">
          <div class="receipt__product-img-wrap">
            <img
              v-if="order.product.image_url"
              :src="order.product.image_url"
              :alt="order.product.name"
              class="receipt__product-img"
            />
          </div>
          <div class="receipt__product-info">
            <span class="receipt__product-name">{{ order.product.name }}</span>
            <span class="badge badge--sold" style="margin-top:.25rem">SOLD</span>
          </div>
        </div>

        <div class="receipt__divider" />

        <!-- Details table -->
        <div class="receipt__rows">
          <div class="receipt__row">
            <span class="receipt__row-label">Claim Method</span>
            <span :class="['badge', `badge--${order.claim_type}`]">{{ order.claim_type.toUpperCase() }}</span>
          </div>
          <div class="receipt__row">
            <span class="receipt__row-label">Amount</span>
            <span class="receipt__row-amount">{{ formatPeso(order.amount) }}</span>
          </div>
          <div class="receipt__row">
            <span class="receipt__row-label">Payment</span>
            <span class="badge badge--paid">PAID</span>
          </div>
          <div class="receipt__row">
            <span class="receipt__row-label">Paid at</span>
            <span class="receipt__row-value">{{ formatDate(order.paid_at) }}</span>
          </div>
        </div>

        <div class="receipt__divider" />

        <div class="receipt__actions">
          <RouterLink to="/catalog" class="btn btn--ghost btn--full">Continue Hunting</RouterLink>
          <RouterLink to="/my-orders" class="btn btn--primary btn--full">View My Orders</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.receipt {
  display: flex; flex-direction: column; gap: 1.25rem; align-items: center;
  padding: 3rem 2rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  text-align: center;
}

.receipt__brand {
  font-size: .9rem; letter-spacing: .1em;
  color: var(--color-seashell-muted);
  text-decoration: none; display: block;
}
.receipt__brand span { color: var(--color-spice-market); }

.receipt__check {
  width: 52px; height: 52px;
  border-radius: 50%;
  background: rgba(232,160,80,.1);
  border: 1px solid rgba(232,160,80,.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; color: var(--color-grab);
}

.receipt__title { font-size: 1.6rem; color: var(--color-seashell); }
.receipt__order-num { font-size: .72rem; letter-spacing: .1em; text-transform: uppercase; color: rgba(254,243,238,.3); }

.receipt__divider { width: 100%; border: none; border-top: 1px solid var(--color-balsamico-border); }

.receipt__product {
  display: flex; gap: 1rem; align-items: center; text-align: left; width: 100%;
}
.receipt__product-img-wrap { width: 64px; height: 64px; flex-shrink: 0; overflow: hidden; background: var(--color-balsamico-lighter); }
.receipt__product-img { width: 100%; height: 100%; object-fit: cover; }
.receipt__product-info { display: flex; flex-direction: column; }
.receipt__product-name { font-weight: 600; font-size: .95rem; color: var(--color-seashell); }

.receipt__rows { width: 100%; display: flex; flex-direction: column; gap: .75rem; text-align: left; }
.receipt__row { display: flex; justify-content: space-between; align-items: center; }
.receipt__row-label { font-size: .78rem; color: var(--color-seashell-muted); }
.receipt__row-value { font-size: .875rem; color: var(--color-seashell-strong); }
.receipt__row-amount { font-size: 1.25rem; font-weight: 700; color: var(--color-seashell); }

.receipt__actions { width: 100%; display: flex; flex-direction: column; gap: .75rem; }
</style>
