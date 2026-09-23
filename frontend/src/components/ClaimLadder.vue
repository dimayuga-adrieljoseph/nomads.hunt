<script setup lang="ts">
/**
 * ClaimLadder — the main interaction component on ProductDetail.
 * Renders Mine / Steal / Grab buttons based on backend product state.
 * All business logic validation happens in Laravel; this only triggers the calls.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { claimService } from '@/services/claim.service'
import type { Product } from '@/services/product.service'
import { formatPeso } from '@/utils/formatters'
import ClaimTimer from './ClaimTimer.vue'

const props = defineProps<{ product: Product; myActiveClaim?: import('@/services/claim.service').ClaimRecord | null }>()
const emit  = defineEmits<{ (e: 'refresh'): void }>()

const auth   = useAuthStore()
const router = useRouter()

const loading = ref<'mine' | 'steal' | 'grab' | null>(null)
const message = ref('')
const msgType = ref<'success' | 'error'>('success')

// ── Derived state from backend product.status ─────────────────────────────

const isSold        = computed(() => props.product.status === 'sold')
const isAvailable   = computed(() => props.product.status === 'available')
const isMinePending = computed(() => props.product.status === 'mine_pending')
const isStealPending= computed(() => props.product.status === 'steal_pending')
const isGrabPending = computed(() => props.product.status === 'grab_pending')

const canMine  = computed(() => (isAvailable.value || isMinePending.value) && auth.isCustomer)
const canSteal = computed(() => (isMinePending.value || isStealPending.value) && auth.isCustomer)
const canGrab  = computed(() => isAvailable.value && auth.isCustomer)

const mineDisabledReason = computed(() => {
  if (isSold.value)        return 'Product is sold'
  if (isStealPending.value) return 'Mine locked — Steal is active'
  if (isGrabPending.value)  return 'Grab payment in progress'
  return ''
})

const grabDisabledReason = computed(() => {
  if (isSold.value)         return 'Product is sold'
  if (isMinePending.value)  return 'A Mine claim is active'
  if (isStealPending.value) return 'A Steal claim is active'
  if (isGrabPending.value)  return 'Grab payment in progress'
  return ''
})

// ── Actions ───────────────────────────────────────────────────────────────

function requireAuth() {
  if (!auth.isLoggedIn) { router.push({ name: 'login', query: { redirect: `/products/${props.product.id}` } }); return false }
  if (!auth.isCustomer) { showMsg('Admin accounts cannot place claims.', 'error'); return false }
  return true
}

async function doMine() {
  if (!requireAuth() || !canMine.value) return
  loading.value = 'mine'
  try {
    const res = await claimService.mine(props.product.id)
    showMsg(res.message, 'success')
    emit('refresh')
    if (res.claim.status === 'active' && res.claim.order) {
      router.push(`/orders/${res.claim.order.id}/pay`)
    }
  } catch (e: unknown) {
    showErr(e)
  } finally {
    loading.value = null
  }
}

async function doSteal() {
  if (!requireAuth() || !canSteal.value) return
  loading.value = 'steal'
  try {
    const res = await claimService.steal(props.product.id)
    showMsg(res.message, 'success')
    emit('refresh')
    if (res.claim.status === 'active' && res.claim.order) {
      router.push(`/orders/${res.claim.order.id}/pay`)
    }
  } catch (e: unknown) {
    showErr(e)
  } finally {
    loading.value = null
  }
}

async function doGrab() {
  if (!requireAuth() || !canGrab.value) return
  loading.value = 'grab'
  try {
    const res = await claimService.grab(props.product.id)
    showMsg(res.message, 'success')
    emit('refresh')
    if (res.claim.order) {
      router.push(`/orders/${res.claim.order.id}/pay`)
    }
  } catch (e: unknown) {
    showErr(e)
  } finally {
    loading.value = null
  }
}

function showMsg(msg: string, type: 'success' | 'error') {
  message.value = msg
  msgType.value = type
  setTimeout(() => { message.value = '' }, 6000)
}

function showErr(e: unknown) {
  const err = e as { response?: { data?: { message?: string } } }
  showMsg(err.response?.data?.message ?? 'Something went wrong.', 'error')
}
</script>

<template>
  <div class="ladder">
    <h2 class="ladder__title">Claim Ladder</h2>

    <!-- Feedback message -->
    <div v-if="message" :class="['alert', msgType === 'success' ? 'alert--success' : 'alert--error']">
      {{ message }}
    </div>

    <!-- SOLD state -->
    <div v-if="isSold" class="ladder__sold">
      <span class="badge badge--sold" style="font-size:1rem;padding:.5rem 1.5rem">SOLD</span>
      <p>This item has been sold.</p>
    </div>

    <!-- Active claim context for current user -->
    <div v-if="myActiveClaim && !isSold" class="ladder__my-claim">
      <div class="my-claim-badge">
        <span :class="['badge', `badge--${myActiveClaim.type}`]">{{ myActiveClaim.type.toUpperCase() }}</span>
        <span v-if="myActiveClaim.status === 'active'" class="my-claim-status">Your claim is ACTIVE</span>
        <span v-else class="my-claim-status">Queue #{{ myActiveClaim.position }}</span>
      </div>
      <div v-if="myActiveClaim.status === 'active' && myActiveClaim.expires_at" class="my-claim-timer">
        <ClaimTimer :expires-at="myActiveClaim.expires_at" @expired="emit('refresh')" />
        <span class="my-claim-timer-label">remaining</span>
      </div>
      <div v-if="myActiveClaim.status === 'active' && myActiveClaim.order" class="my-claim-pay">
        <RouterLink :to="`/orders/${myActiveClaim.order.id}/pay`" class="btn btn--primary btn--full">
          Go to Payment →
        </RouterLink>
      </div>
    </div>

    <template v-if="!isSold">
      <!-- MINE -->
      <div class="ladder__action" :class="{ 'ladder__action--disabled': !canMine }">
        <div class="ladder__action-info">
          <div class="ladder__action-type ladder__action-type--mine">MINE</div>
          <div class="ladder__action-price">{{ formatPeso(product.mine_price) }}</div>
          <div v-if="product.mine_queue_count && product.mine_queue_count > 0" class="ladder__action-queue">
            {{ product.mine_queue_count }} in queue
          </div>
        </div>
        <div class="ladder__action-right">
          <p v-if="mineDisabledReason" class="ladder__disabled-reason">{{ mineDisabledReason }}</p>
          <button
            v-else-if="auth.isLoggedIn"
            class="btn btn--mine"
            :disabled="!canMine || loading !== null"
            @click="doMine"
          >
            {{ loading === 'mine' ? '…' : 'MINE' }}
          </button>
          <RouterLink v-else to="/login" class="btn btn--mine">MINE</RouterLink>
        </div>
      </div>

      <!-- STEAL -->
      <div class="ladder__action" :class="{ 'ladder__action--disabled': !canSteal }">
        <div class="ladder__action-info">
          <div class="ladder__action-type ladder__action-type--steal">STEAL</div>
          <div class="ladder__action-price">{{ formatPeso(product.steal_price) }}</div>
          <div v-if="product.steal_queue_count && product.steal_queue_count > 0" class="ladder__action-queue">
            {{ product.steal_queue_count }} in queue
          </div>
        </div>
        <div class="ladder__action-right">
          <p v-if="!canSteal && !isStealPending && !isMinePending" class="ladder__disabled-reason">
            {{ isSold ? 'Sold' : isAvailable ? 'Steal requires an active Mine' : 'Unavailable' }}
          </p>
          <button
            v-else-if="auth.isLoggedIn"
            class="btn btn--steal"
            :disabled="!canSteal || loading !== null"
            @click="doSteal"
          >
            {{ loading === 'steal' ? '…' : 'STEAL' }}
          </button>
          <RouterLink v-else to="/login" class="btn btn--steal">STEAL</RouterLink>
        </div>
      </div>

      <!-- GRAB -->
      <div class="ladder__action" :class="{ 'ladder__action--disabled': !canGrab }">
        <div class="ladder__action-info">
          <div class="ladder__action-type ladder__action-type--grab">GRAB</div>
          <div class="ladder__action-price">{{ formatPeso(product.grab_price) }}</div>
          <div class="ladder__action-sub">Buy now — no queue</div>
        </div>
        <div class="ladder__action-right">
          <p v-if="grabDisabledReason" class="ladder__disabled-reason">{{ grabDisabledReason }}</p>
          <button
            v-else-if="auth.isLoggedIn"
            class="btn btn--grab"
            :disabled="!canGrab || loading !== null"
            @click="doGrab"
          >
            {{ loading === 'grab' ? '…' : 'GRAB' }}
          </button>
          <RouterLink v-else to="/login" class="btn btn--grab">GRAB</RouterLink>
        </div>
      </div>

      <!-- Active claim timer (product-level) -->
      <div v-if="product.active_claim?.expires_at && !myActiveClaim" class="ladder__active-timer">
        <span class="ladder__active-timer-label">Current claim expires in</span>
        <ClaimTimer :expires-at="product.active_claim.expires_at" @expired="emit('refresh')" />
      </div>
    </template>
  </div>
</template>

<style scoped>
.ladder {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.ladder__title {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-muted);
  padding-bottom: .5rem;
  border-bottom: 1px solid var(--color-border);
}

.ladder__sold {
  text-align: center;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .75rem;
  color: var(--color-muted);
}

.ladder__my-claim {
  background: var(--color-surface-2);
  border-radius: var(--radius);
  padding: .875rem 1rem;
  display: flex;
  flex-direction: column;
  gap: .625rem;
}
.my-claim-badge    { display: flex; align-items: center; gap: .625rem; }
.my-claim-status   { font-size: .875rem; font-weight: 600; }
.my-claim-timer    { display: flex; align-items: baseline; gap: .375rem; font-size: 1.5rem; }
.my-claim-timer-label { font-size: .75rem; color: var(--color-muted); }

.ladder__action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  transition: border-color .15s;
}
.ladder__action:not(.ladder__action--disabled):hover { border-color: #444; }
.ladder__action--disabled { opacity: .55; }

.ladder__action-info    { display: flex; flex-direction: column; gap: .25rem; }
.ladder__action-type    { font-size: .7rem; font-weight: 800; letter-spacing: .1em; }
.ladder__action-type--mine  { color: var(--color-mine); }
.ladder__action-type--steal { color: var(--color-steal); }
.ladder__action-type--grab  { color: var(--color-grab); }
.ladder__action-price   { font-size: 1.125rem; font-weight: 700; }
.ladder__action-queue   { font-size: .75rem; color: var(--color-muted); }
.ladder__action-sub     { font-size: .75rem; color: var(--color-muted); }

.ladder__action-right   { flex-shrink: 0; }
.ladder__disabled-reason { font-size: .75rem; color: var(--color-muted); text-align: right; max-width: 120px; }

.ladder__active-timer {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .8rem;
  color: var(--color-muted);
  padding: .5rem .75rem;
  background: var(--color-surface-2);
  border-radius: var(--radius);
}
.ladder__active-timer-label { flex: 1; }
</style>
