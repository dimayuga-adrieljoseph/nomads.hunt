<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

const route  = useRoute()
const router = useRouter()

const order   = ref<Order | null>(null)
const loading = ref(true)
const paying  = ref(false)
const error   = ref('')

async function load() {
  loading.value = true
  try {
    order.value = await orderService.get(Number(route.params.id))
    // If already paid, redirect to receipt
    if (order.value.payment_status === 'paid') {
      router.replace(`/receipt/${order.value.id}`)
    }
  } catch {
    error.value = 'Order not found.'
  } finally {
    loading.value = false
  }
}

async function simulatePayment() {
  if (!order.value || paying.value) return
  paying.value = true
  error.value  = ''
  try {
    const res = await orderService.pay(order.value.id)
    router.push(`/receipt/${res.data.id}`)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Payment failed. Please try again.'
  } finally {
    paying.value = false
  }
}

function onTimerExpired() {
  load() // Reload to show expired state
}

onMounted(load)
</script>

<template>
  <div class="page-content">
    <div class="container">
      <div v-if="loading" class="spinner" />

      <div v-else-if="error && !order" class="alert alert--error">{{ error }}</div>

      <div v-else-if="order" class="payment-wrap">
        <div class="payment-card card">
          <!-- Header -->
          <div class="payment-header">
            <div class="payment-brand">NOMADS<span>.</span>HUNT</div>
            <div class="payment-order-num">Order #{{ order.order_number }}</div>
          </div>

          <!-- Product -->
          <div v-if="order.product" class="payment-product">
            <img
              v-if="order.product.image_url"
              :src="order.product.image_url"
              :alt="order.product.name"
              class="payment-product__img"
            />
            <div class="payment-product__info">
              <h2 class="payment-product__name">{{ order.product.name }}</h2>
              <span :class="['badge', `badge--${order.claim_type}`]">
                {{ order.claim_type.toUpperCase() }}
              </span>
            </div>
          </div>

          <hr class="divider" />

          <!-- Amount -->
          <div class="payment-row">
            <span class="payment-label">Amount Due</span>
            <span class="payment-amount">{{ formatPeso(order.amount) }}</span>
          </div>

          <!-- Timer -->
          <div v-if="order.payment_status === 'pending' && order.expires_at" class="payment-row payment-row--timer">
            <span class="payment-label">Payment deadline</span>
            <ClaimTimer
              :expires-at="order.expires_at"
              style="font-size:1.5rem"
              @expired="onTimerExpired"
            />
          </div>

          <!-- Expired / cancelled states -->
          <div v-if="order.payment_status === 'expired'" class="alert alert--error">
            This payment window has expired. Your claim has been released.
          </div>
          <div v-if="order.payment_status === 'cancelled'" class="alert alert--error">
            This order has been cancelled.
          </div>

          <hr class="divider" />

          <!-- Error feedback -->
          <div v-if="error" class="alert alert--error">{{ error }}</div>

          <!-- CTA -->
          <button
            v-if="order.payment_status === 'pending'"
            class="btn btn--primary btn--full btn--lg"
            :disabled="paying"
            @click="simulatePayment"
          >
            {{ paying ? 'Processing…' : 'SIMULATE PAYMENT' }}
          </button>

          <div v-else-if="order.payment_status !== 'paid'" class="payment-unavailable">
            Payment is no longer available for this order.
          </div>

          <div class="payment-meta">
            <span>Placed {{ formatDate(order.created_at) }}</span>
          </div>
        </div>

        <RouterLink to="/my-claims" class="back-link">← Back to My Claims</RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payment-wrap {
  max-width: 480px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.payment-card { padding: 2rem; }

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.payment-brand {
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: .08em;
}
.payment-brand span { color: var(--color-mine); }
.payment-order-num { font-size: .85rem; color: var(--color-muted); }

.payment-product {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-bottom: .5rem;
}
.payment-product__img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: var(--radius);
  flex-shrink: 0;
  background: var(--color-surface-2);
}
.payment-product__info { display: flex; flex-direction: column; gap: .5rem; }
.payment-product__name { font-size: 1.1rem; font-weight: 600; }

.payment-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .75rem 0;
}
.payment-row--timer { border-top: 1px solid var(--color-border); }
.payment-label  { color: var(--color-muted); font-size: .9rem; }
.payment-amount { font-size: 1.75rem; font-weight: 700; }

.payment-unavailable {
  text-align: center;
  padding: 1rem;
  color: var(--color-muted);
  font-size: .9rem;
}

.payment-meta {
  margin-top: 1rem;
  text-align: center;
  font-size: .75rem;
  color: var(--color-muted);
}

.back-link {
  font-size: .875rem;
  color: var(--color-muted);
  text-align: center;
}
.back-link:hover { color: var(--color-text); }
</style>
