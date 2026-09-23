<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

const route  = useRoute()
const order  = ref<Order | null>(null)
const loading = ref(true)
const error   = ref('')

onMounted(async () => {
  try {
    order.value = await orderService.get(Number(route.params.id))
  } catch {
    error.value = 'Order not found.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-content">
    <div class="container">
      <div v-if="loading" class="spinner" />
      <div v-else-if="error" class="alert alert--error">{{ error }}</div>

      <div v-else-if="order" class="receipt-wrap">
        <div class="receipt card">
          <!-- Brand -->
          <div class="receipt__brand">NOMADS<span>.</span>HUNT</div>

          <!-- Checkmark -->
          <div class="receipt__check">✓</div>

          <h1 class="receipt__title">Payment Confirmed</h1>

          <div class="receipt__order-num">RECEIPT — Order #{{ order.order_number }}</div>

          <hr class="divider" />

          <!-- Product -->
          <div v-if="order.product" class="receipt__product">
            <img
              v-if="order.product.image_url"
              :src="order.product.image_url"
              :alt="order.product.name"
              class="receipt__product-img"
            />
            <div class="receipt__product-info">
              <div class="receipt__product-name">{{ order.product.name }}</div>
              <div class="receipt__sold-badge">
                <span class="badge badge--sold">SOLD</span>
              </div>
            </div>
          </div>

          <hr class="divider" />

          <!-- Details -->
          <div class="receipt__rows">
            <div class="receipt__row">
              <span class="receipt__row-label">Claim Method</span>
              <span :class="['badge', `badge--${order.claim_type}`]">{{ order.claim_type.toUpperCase() }}</span>
            </div>
            <div class="receipt__row">
              <span class="receipt__row-label">Amount</span>
              <span class="receipt__row-value receipt__amount">{{ formatPeso(order.amount) }}</span>
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

          <hr class="divider" />

          <div class="receipt__actions">
            <RouterLink to="/" class="btn btn--ghost btn--full">Continue Shopping</RouterLink>
            <RouterLink to="/my-orders" class="btn btn--primary btn--full">View My Orders</RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.receipt-wrap { max-width: 420px; margin: 0 auto; }

.receipt { padding: 2.5rem; text-align: center; }

.receipt__brand {
  font-size: .9rem;
  font-weight: 800;
  letter-spacing: .1em;
  color: var(--color-muted);
  margin-bottom: 1.5rem;
}
.receipt__brand span { color: var(--color-mine); }

.receipt__check {
  width: 56px;
  height: 56px;
  background: rgba(34,197,94,.15);
  border: 2px solid var(--color-grab);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--color-grab);
  margin: 0 auto 1rem;
}

.receipt__title { font-size: 1.5rem; margin-bottom: .25rem; }

.receipt__order-num {
  font-size: .75rem;
  color: var(--color-muted);
  letter-spacing: .06em;
  margin-bottom: .5rem;
}

.receipt__product {
  display: flex;
  gap: 1rem;
  align-items: center;
  text-align: left;
  padding: .75rem 0;
}
.receipt__product-img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  flex-shrink: 0;
}
.receipt__product-info { display: flex; flex-direction: column; gap: .375rem; }
.receipt__product-name { font-weight: 600; font-size: .95rem; }

.receipt__rows { display: flex; flex-direction: column; gap: .75rem; text-align: left; }
.receipt__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.receipt__row-label { font-size: .85rem; color: var(--color-muted); }
.receipt__row-value { font-size: .9rem; font-weight: 500; }
.receipt__amount    { font-size: 1.25rem; font-weight: 700; }

.receipt__actions { display: flex; flex-direction: column; gap: .75rem; }
</style>
