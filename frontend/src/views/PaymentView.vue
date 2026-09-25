<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

const route  = useRoute()
const router = useRouter()

const order   = ref<Order | null>(null)
const loading = ref(true)
const paying  = ref(false)
const error   = ref('')
const deadlinePassed = ref(false)

/**
 * The backend is the authority on whether this order may be paid. These flags
 * only keep the UI honest — a disabled button never replaces the server check.
 */
const claimStatus = computed(() => order.value?.claim?.status ?? null)
const claimPhase  = computed(() => order.value?.claim?.phase ?? null)

const canPay = computed(() =>
  !!order.value
  && order.value.payment_status === 'pending'
  && claimStatus.value === 'active'
  && claimPhase.value === 'payment'
  && !deadlinePassed.value,
)

const blockedReason = computed(() => {
  if (!order.value || order.value.payment_status === 'paid') return ''
  if (claimStatus.value === 'overridden')   return 'Your claim was overridden by a Steal claim, so it can no longer be paid.'
  if (claimStatus.value === 'expired')      return 'Your payment window expired. The next claimant has been activated.'
  if (claimStatus.value === 'cancelled')    return 'This claim was cancelled — the piece went to another claimant.'
  if (claimStatus.value === 'completed')    return 'This claim has already been completed.'
  if (claimStatus.value === 'waiting')      return 'You are still in the queue — payment opens once your claim becomes active.'
  if (claimPhase.value === 'claim')         return 'Your claim period is still running. Payment opens when it ends.'
  if (deadlinePassed.value)                 return 'This payment window has expired.'
  return ''
})

async function load() {
  loading.value = true
  try {
    order.value = await orderService.get(Number(route.params.id))
    deadlinePassed.value = !!order.value.expires_at
      && new Date(order.value.expires_at).getTime() <= Date.now()
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
  if (!order.value || paying.value || !canPay.value) return
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
  // Close the window locally straight away, then let the backend confirm it
  deadlinePassed.value = true
  load()
}
onMounted(load)
</script>

<template>
  <div class="page-content">
    <div class="container" style="max-width: 520px">

      <div v-if="loading" class="spinner" />
      <div v-else-if="error && !order" class="alert alert--error" role="alert">{{ error }}</div>

      <div v-else-if="order" class="payment-wrap">

        <!-- Header -->
        <div class="payment-brand">
          <RouterLink to="/" class="payment-brand__logo display" aria-label="NOMADS.HUNT">
            NOMADS<span>.</span>HUNT
          </RouterLink>
          <span class="payment-brand__order">Order #{{ order.order_number }}</span>
        </div>

        <!-- Product info -->
        <div v-if="order.product" class="payment-product">
          <div class="payment-product__img-wrap">
            <img
              v-if="order.product.image_url"
              :src="order.product.image_url"
              :alt="order.product.name"
              class="payment-product__img"
            />
            <div v-else class="payment-product__img-placeholder" />
          </div>
          <div class="payment-product__info">
            <h2 class="payment-product__name">{{ order.product.name }}</h2>
            <span :class="['badge', `badge--${order.claim_type}`]">{{ order.claim_type.toUpperCase() }}</span>
          </div>
        </div>

        <div class="payment-divider" />

        <!-- Amount -->
        <div class="payment-row">
          <span class="payment-row__label">Amount Due</span>
          <span class="payment-row__value payment-row__value--big">{{ formatPeso(order.amount) }}</span>
        </div>

        <!-- Timer -->
        <div v-if="order.payment_status === 'pending' && order.expires_at" class="payment-row payment-row--timer">
          <span class="payment-row__label">Payment deadline</span>
          <ClaimTimer :expires-at="order.expires_at" style="font-size:1.5rem;font-weight:700;color:var(--color-spice-market)" @expired="onTimerExpired" />
        </div>

        <!-- Status alerts -->
        <div v-if="order.payment_status === 'expired'" class="alert alert--error" role="alert">
          This payment window has expired. Your claim has been released.
        </div>
        <div v-if="order.payment_status === 'cancelled'" class="alert alert--error" role="alert">
          This order has been cancelled.
        </div>

        <div class="payment-divider" />

        <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>
        <div v-if="blockedReason" class="alert alert--error" role="alert">{{ blockedReason }}</div>

        <!-- CTA -->
        <button
          v-if="order.payment_status === 'pending'"
          class="payment-cta"
          :disabled="paying || !canPay"
          :aria-busy="paying"
          @click="simulatePayment"
        >
          {{ paying ? 'Processing…' : 'Simulate Payment →' }}
        </button>

        <div v-else-if="order.payment_status !== 'paid'" class="payment-unavailable">
          Payment is no longer available for this order.
        </div>

        <p class="payment-meta">Placed {{ formatDate(order.created_at) }}</p>

        <RouterLink to="/liked-products" class="payment-back">← Back to Liked Products</RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payment-wrap { display: flex; flex-direction: column; gap: 1.25rem; }

.payment-brand { display: flex; justify-content: space-between; align-items: center; margin-bottom: .5rem; }
.payment-brand__logo {
  font-size: 1rem; letter-spacing: .08em;
  color: var(--color-seashell); text-decoration: none;
}
.payment-brand__logo span { color: var(--color-spice-market); }
.payment-brand__order { font-size: .75rem; color: var(--color-seashell-muted); letter-spacing: .06em; }

.payment-product {
  display: flex; gap: 1.25rem; align-items: center;
  padding: 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
}
.payment-product__img-wrap { width: 80px; height: 80px; flex-shrink: 0; overflow: hidden; background: var(--color-balsamico-lighter); }
.payment-product__img      { width: 100%; height: 100%; object-fit: cover; }
.payment-product__img-placeholder { width: 100%; height: 100%; }
.payment-product__info { display: flex; flex-direction: column; gap: .5rem; }
.payment-product__name { font-size: 1.05rem; font-weight: 600; color: var(--color-seashell); }

.payment-divider { border: none; border-top: 1px solid var(--color-balsamico-border); }

.payment-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: .75rem 0;
}
.payment-row--timer { border-top: 1px solid var(--color-balsamico-border); }
.payment-row__label { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .04em; }
.payment-row__value--big { font-size: 1.75rem; font-weight: 700; color: var(--color-seashell); }

.payment-cta {
  width: 100%;
  background: var(--color-spice-market);
  color: var(--color-seashell);
  border: none;
  font-family: var(--font-body);
  font-size: .85rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  padding: 1.1rem; cursor: pointer; border-radius: var(--radius);
  transition: background var(--transition-fast);
}
.payment-cta:hover:not(:disabled) { background: #C8501F; }
.payment-cta:disabled { opacity: .4; cursor: not-allowed; }

.payment-unavailable { text-align: center; font-size: .85rem; color: var(--color-seashell-muted); padding: 1rem; }
.payment-meta { text-align: center; font-size: .72rem; color: rgba(254,243,238,.25); }
.payment-back { font-size: .78rem; color: var(--color-seashell-muted); text-align: center; display: block; transition: color var(--transition-fast); }
.payment-back:hover { color: var(--color-spice-market); }
</style>
