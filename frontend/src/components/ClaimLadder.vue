<script setup lang="ts">
/**
 * ClaimLadder — Mine / Steal / Grab interaction widget.
 * All business logic validation is in Laravel; this only triggers API calls.
 */
import { ref, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { claimService } from '@/services/claim.service'
import type { Product } from '@/services/product.service'
import { formatPeso } from '@/utils/formatters'
import ClaimTimer from './ClaimTimer.vue'

const props = defineProps<{
  product: Product
  myActiveClaim?: import('@/services/claim.service').ClaimRecord | null
}>()
const emit = defineEmits<{ (e: 'refresh'): void }>()

const auth   = useAuthStore()
const router = useRouter()

const loading = ref<'mine' | 'steal' | 'grab' | null>(null)
const message = ref('')
const msgType = ref<'success' | 'error'>('success')

// ── Derived state from backend product.status ─────────────────────────────
const isSold         = computed(() => props.product.status === 'sold')
const isAvailable    = computed(() => props.product.status === 'available')
const isMinePending  = computed(() => props.product.status === 'mine_pending')
const isStealPending = computed(() => props.product.status === 'steal_pending')
const isGrabPending  = computed(() => props.product.status === 'grab_pending')
const isUnavailable  = computed(() => isSold.value || isGrabPending.value)

const canMine  = computed(() => (isAvailable.value || isMinePending.value) && auth.isCustomer)
const canSteal = computed(() => (isMinePending.value || isStealPending.value) && auth.isCustomer)
const canGrab  = computed(() => isAvailable.value && auth.isCustomer)

// ── The customer's own claim ───────────────────────────────────────────────
// A customer may only be sent to payment once their claim stage has finished:
// the backend exposes that as phase = 'payment' and can_pay = true.
const claimPhase       = computed(() => props.myActiveClaim?.phase ?? null)
const isMyActiveClaim  = computed(() => props.myActiveClaim?.status === 'active')
const isMyClaimStage   = computed(() => isMyActiveClaim.value && claimPhase.value === 'claim')
const isMyPaymentStage = computed(() => isMyActiveClaim.value && claimPhase.value === 'payment')

const canPayMyClaim = computed(() =>
  isMyPaymentStage.value
  && props.myActiveClaim?.can_pay === true
  && props.myActiveClaim?.order?.payment_status === 'pending',
)

const mineDisabledReason = computed(() => {
  if (isSold.value)         return 'This piece has been sold'
  if (isStealPending.value) return 'Mine locked — Steal is active'
  if (isGrabPending.value)  return 'Grab payment in progress'
  return ''
})

const grabDisabledReason = computed(() => {
  if (isSold.value)         return 'This piece has been sold'
  if (isMinePending.value)  return 'A Mine claim is active'
  if (isStealPending.value) return 'A Steal claim is active'
  if (isGrabPending.value)  return 'Grab payment in progress'
  return ''
})

// ── Auth guard ────────────────────────────────────────────────────────────
function requireAuth(): boolean {
  if (!auth.isLoggedIn) {
    router.push({ name: 'login', query: { redirect: `/products/${props.product.id}` } })
    return false
  }
  if (!auth.isCustomer) {
    showMsg('Admin accounts cannot place claims.', 'error')
    return false
  }
  return true
}

// ── Actions ───────────────────────────────────────────────────────────────
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
  } catch (e: unknown) { showErr(e) }
  finally { loading.value = null }
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
  } catch (e: unknown) { showErr(e) }
  finally { loading.value = null }
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
  } catch (e: unknown) { showErr(e) }
  finally { loading.value = null }
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
  <div class="ladder" role="region" aria-label="Claim Ladder">

    <!-- ── Feedback ───────────────────────────────────────────────────── -->
    <div
      v-if="message"
      role="alert"
      :class="['alert', msgType === 'success' ? 'alert--success' : 'alert--error']"
    >{{ message }}</div>

    <!-- ── SOLD STATE ─────────────────────────────────────────────────── -->
    <div v-if="isSold" class="ladder__sold" aria-label="Product sold">
      <div class="ladder__sold-stamp display">SOLD</div>
      <p class="ladder__sold-sub">This piece has found its home.</p>

      <!-- Similar piece request -->
      <div class="ladder__request">
        <div class="ladder__request-header">
          <span class="ladder__request-label">Want something similar?</span>
        </div>
        <RouterLink to="/" class="ladder__request-btn">
          Request Similar Piece →
        </RouterLink>
        <!-- Only show if backend provides a minimum price -->
        <p v-if="product.mine_price" class="ladder__request-hint">
          Starting from {{ formatPeso(product.mine_price) }}
        </p>
      </div>
    </div>

    <template v-if="!isSold">

      <!-- ── My active claim banner ─────────────────────────────────── -->
      <div v-if="myActiveClaim && !isSold" class="ladder__my-claim" :class="`ladder__my-claim--${myActiveClaim.type}`">
        <div class="ladder__my-claim-top">
          <span class="ladder__my-claim-type">{{ myActiveClaim.type.toUpperCase() }}</span>
          <span v-if="isMyClaimStage" class="ladder__my-claim-status">
            Your claim is <strong>ACTIVE</strong>
          </span>
          <span v-else-if="isMyPaymentStage" class="ladder__my-claim-status">
            Claim period complete — <strong>PAYMENT AVAILABLE</strong>
          </span>
          <span v-else class="ladder__my-claim-status">Queue position #{{ myActiveClaim.position }}</span>
        </div>

        <!-- Stage 1: CLAIM period — holding the piece, others may still Steal -->
        <template v-if="isMyClaimStage">
          <div v-if="myActiveClaim.claim_expires_at" class="ladder__my-claim-timer" aria-label="Claim expires in">
            <ClaimTimer :expires-at="myActiveClaim.claim_expires_at" @expired="emit('refresh')" />
            <span class="ladder__my-claim-timer-label">claim expires in</span>
          </div>
          <p class="ladder__my-claim-note">
            Hold the piece through your claim period — payment opens when it ends. A Steal can override it before then.
          </p>
        </template>

        <!-- Stage 2: PAYMENT window — only now may the claimant pay -->
        <template v-else-if="isMyPaymentStage">
          <div v-if="myActiveClaim.payment_expires_at" class="ladder__my-claim-timer" aria-label="Payment expires in">
            <ClaimTimer :expires-at="myActiveClaim.payment_expires_at" @expired="emit('refresh')" />
            <span class="ladder__my-claim-timer-label">payment expires in</span>
          </div>
          <p class="ladder__my-claim-note">
            Claim survived. Pay {{ formatPeso(myActiveClaim.amount) }} now or the queue moves to the next claimant.
          </p>
          <RouterLink
            v-if="canPayMyClaim"
            :to="`/orders/${myActiveClaim.order!.id}/pay`"
            class="btn btn--primary btn--full"
          >Pay {{ formatPeso(myActiveClaim.amount) }} →</RouterLink>
          <p v-else class="ladder__my-claim-note">This payment window has closed — the next claimant is being activated.</p>
        </template>

        <!-- Queued -->
        <p v-else-if="myActiveClaim.status === 'waiting'" class="ladder__my-claim-note">
          Waiting for the current claimant's claim + payment periods to finish. You'll get your own claim period next.
        </p>
      </div>

      <!-- ── Product-level countdown (someone else's active claim) ────── -->
      <div
        v-if="product.active_claim?.expires_at && !isMyActiveClaim"
        class="ladder__countdown"
        aria-label="Current claim expires in"
      >
        <span class="ladder__countdown-label">
          {{ product.active_claim.phase === 'payment' ? 'Current claimant paying — expires in' : 'Current claim expires in' }}
        </span>
        <ClaimTimer :expires-at="product.active_claim.expires_at" @expired="emit('refresh')" />
      </div>

      <!-- ── MINE ────────────────────────────────────────────────────── -->
      <div
        class="ladder__rung ladder__rung--mine"
        :class="{ 'ladder__rung--disabled': !canMine && !mineDisabledReason === false }"
        role="group"
        aria-label="Mine claim option"
      >
        <div class="ladder__rung-left">
          <div class="ladder__rung-indicator ladder__rung-indicator--mine" aria-hidden="true">
            <span>01</span>
          </div>
          <div class="ladder__rung-info">
            <span class="ladder__rung-type ladder__rung-type--mine">MINE</span>
            <span class="ladder__rung-desc">Hold the piece, then pay once your claim period ends — queue position if taken</span>
            <span
              v-if="product.mine_queue_count && product.mine_queue_count > 0"
              class="ladder__rung-queue"
              aria-label="`${product.mine_queue_count} in queue`"
            >{{ product.mine_queue_count }} in queue</span>
          </div>
        </div>
        <div class="ladder__rung-right">
          <span class="ladder__rung-price">{{ formatPeso(product.mine_price) }}</span>
          <template v-if="mineDisabledReason">
            <span class="ladder__rung-reason" role="status">{{ mineDisabledReason }}</span>
          </template>
          <template v-else-if="auth.isLoggedIn">
            <button
              class="ladder__rung-btn ladder__rung-btn--mine"
              :disabled="!canMine || loading !== null"
              :aria-label="`Mine this piece for ${formatPeso(product.mine_price)}`"
              @click="doMine"
            >{{ loading === 'mine' ? '…' : 'MINE' }}</button>
          </template>
          <RouterLink v-else to="/login" class="ladder__rung-btn ladder__rung-btn--mine">MINE</RouterLink>
        </div>
      </div>

      <!-- Visual connector between rungs -->
      <div class="ladder__connector" aria-hidden="true">
        <span class="ladder__connector-line" />
        <span class="ladder__connector-arrow">↓</span>
      </div>

      <!-- ── STEAL ───────────────────────────────────────────────────── -->
      <div
        class="ladder__rung ladder__rung--steal"
        :class="{ 'ladder__rung--disabled': !canSteal }"
        role="group"
        aria-label="Steal claim option"
      >
        <div class="ladder__rung-left">
          <div class="ladder__rung-indicator ladder__rung-indicator--steal" aria-hidden="true">
            <span>02</span>
          </div>
          <div class="ladder__rung-info">
            <span class="ladder__rung-type ladder__rung-type--steal">STEAL</span>
            <span class="ladder__rung-desc">Override the current claim, then pay once your own claim period ends</span>
            <span
              v-if="product.steal_queue_count && product.steal_queue_count > 0"
              class="ladder__rung-queue"
              aria-label="`${product.steal_queue_count} in queue`"
            >{{ product.steal_queue_count }} in queue</span>
          </div>
        </div>
        <div class="ladder__rung-right">
          <span class="ladder__rung-price">{{ formatPeso(product.steal_price) }}</span>
          <template v-if="!canSteal && !isStealPending && !isMinePending">
            <span class="ladder__rung-reason" role="status">
              {{ isSold ? 'Sold' : isAvailable ? 'Steal requires an active Mine' : 'Unavailable' }}
            </span>
          </template>
          <template v-else-if="auth.isLoggedIn">
            <button
              class="ladder__rung-btn ladder__rung-btn--steal"
              :disabled="!canSteal || loading !== null"
              :aria-label="`Steal this piece for ${formatPeso(product.steal_price)}`"
              @click="doSteal"
            >{{ loading === 'steal' ? '…' : 'STEAL' }}</button>
          </template>
          <RouterLink v-else to="/login" class="ladder__rung-btn ladder__rung-btn--steal">STEAL</RouterLink>
        </div>
      </div>

      <!-- Connector -->
      <div class="ladder__connector" aria-hidden="true">
        <span class="ladder__connector-line" />
        <span class="ladder__connector-arrow">↓</span>
      </div>

      <!-- ── GRAB ────────────────────────────────────────────────────── -->
      <div
        class="ladder__rung ladder__rung--grab"
        :class="{ 'ladder__rung--disabled': !canGrab }"
        role="group"
        aria-label="Grab claim option"
      >
        <div class="ladder__rung-left">
          <div class="ladder__rung-indicator ladder__rung-indicator--grab" aria-hidden="true">
            <span>03</span>
          </div>
          <div class="ladder__rung-info">
            <span class="ladder__rung-type ladder__rung-type--grab">GRAB</span>
            <span class="ladder__rung-desc">Buy instantly — no queue, goes straight to payment</span>
          </div>
        </div>
        <div class="ladder__rung-right">
          <span class="ladder__rung-price ladder__rung-price--grab">{{ formatPeso(product.grab_price) }}</span>
          <template v-if="grabDisabledReason">
            <span class="ladder__rung-reason" role="status">{{ grabDisabledReason }}</span>
          </template>
          <template v-else-if="auth.isLoggedIn">
            <button
              class="ladder__rung-btn ladder__rung-btn--grab"
              :disabled="!canGrab || loading !== null"
              :aria-label="`Grab this piece now for ${formatPeso(product.grab_price)}`"
              @click="doGrab"
            >{{ loading === 'grab' ? '…' : 'GRAB' }}</button>
          </template>
          <RouterLink v-else to="/login" class="ladder__rung-btn ladder__rung-btn--grab">GRAB</RouterLink>
        </div>
      </div>

    </template>
  </div>
</template>

<style scoped>
/* ── Ladder wrapper ─────────────────────────────────────────────────────────── */
.ladder {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* ── Feedback ──────────────────────────────────────────────────────────────── */
/* Uses global .alert classes — no scoped override needed */

/* ── SOLD state ────────────────────────────────────────────────────────────── */
.ladder__sold {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.5rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
}
.ladder__sold-stamp {
  font-size: 2rem;
  letter-spacing: .25em;
  color: var(--color-seashell-muted);
  border: 1px solid rgba(254,243,238,.12);
  padding: .3rem 1.25rem;
}
.ladder__sold-sub {
  font-size: .82rem;
  color: var(--color-seashell-muted);
  line-height: 1.6;
}

/* Similar piece request */
.ladder__request {
  width: 100%;
  border-top: 1px solid var(--color-balsamico-border);
  padding-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: .625rem;
}
.ladder__request-header {}
.ladder__request-label {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
}
.ladder__request-btn {
  display: inline-block;
  background: var(--color-spice-market);
  color: var(--color-seashell);
  font-family: var(--font-body);
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: .75rem 1.5rem;
  border-radius: var(--radius);
  transition: background var(--transition-fast);
  text-decoration: none;
}
.ladder__request-btn:hover { background: #C8501F; }
.ladder__request-hint {
  font-size: .72rem;
  color: var(--color-seashell-muted);
}

/* ── My claim banner ───────────────────────────────────────────────────────── */
.ladder__my-claim {
  padding: 1rem 1.25rem;
  border-radius: var(--radius);
  border-left: 3px solid;
  background: var(--color-balsamico-light);
  display: flex;
  flex-direction: column;
  gap: .625rem;
  margin-bottom: .5rem;
}
.ladder__my-claim--mine  { border-left-color: var(--color-spice-market); }
.ladder__my-claim--steal { border-left-color: var(--color-steal); }
.ladder__my-claim--grab  { border-left-color: var(--color-grab); }

.ladder__my-claim-top  { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.ladder__my-claim-type {
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: var(--color-spice-market);
  background: var(--color-spice-dim);
  padding: .2rem .6rem;
  border: 1px solid var(--color-spice-border);
}
.ladder__my-claim--steal .ladder__my-claim-type {
  color: var(--color-steal);
  background: rgba(212,105,26,.12);
  border-color: rgba(212,105,26,.3);
}
.ladder__my-claim--grab .ladder__my-claim-type {
  color: var(--color-grab);
  background: rgba(232,160,80,.1);
  border-color: rgba(232,160,80,.3);
}
.ladder__my-claim-status { font-size: .85rem; color: var(--color-seashell-muted); }
.ladder__my-claim-note { font-size: .78rem; color: var(--color-seashell-muted); line-height: 1.5; }
.ladder__my-claim-timer {
  display: flex;
  align-items: baseline;
  gap: .375rem;
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-spice-market);
}
.ladder__my-claim-timer-label { font-size: .72rem; color: var(--color-seashell-muted); font-weight: 400; }

/* ── Countdown (other user's claim) ───────────────────────────────────────── */
.ladder__countdown {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .625rem 1rem;
  background: rgba(254,243,238,.04);
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  font-size: .78rem;
  margin-bottom: .5rem;
}
.ladder__countdown-label { color: var(--color-seashell-muted); letter-spacing: .04em; }

/* ── Rungs ─────────────────────────────────────────────────────────────────── */
.ladder__rung {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.25rem;
  border: 1px solid var(--color-balsamico-border);
  background: var(--color-balsamico-light);
  transition: border-color var(--transition-fast), background var(--transition-fast);
}

.ladder__rung--mine:not(.ladder__rung--disabled):hover  {
  border-color: var(--color-spice-border);
  background: rgba(186,68,29,.06);
}
.ladder__rung--steal:not(.ladder__rung--disabled):hover {
  border-color: rgba(212,105,26,.35);
  background: rgba(212,105,26,.06);
}
.ladder__rung--grab:not(.ladder__rung--disabled):hover  {
  border-color: rgba(232,160,80,.3);
  background: rgba(232,160,80,.05);
}

.ladder__rung--disabled {
  opacity: .45;
}

.ladder__rung-left {
  display: flex;
  align-items: flex-start;
  gap: .875rem;
  flex: 1;
  min-width: 0;
}

/* Step indicators */
.ladder__rung-indicator {
  width: 30px; height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: .6rem;
  font-weight: 800;
  letter-spacing: .05em;
  border: 1px solid;
  margin-top: 2px;
}
.ladder__rung-indicator--mine  { color: var(--color-spice-market); border-color: var(--color-spice-border); background: var(--color-spice-dim); }
.ladder__rung-indicator--steal { color: var(--color-steal); border-color: rgba(212,105,26,.3); background: rgba(212,105,26,.1); }
.ladder__rung-indicator--grab  { color: var(--color-grab); border-color: rgba(232,160,80,.3); background: rgba(232,160,80,.08); }

.ladder__rung-info {
  display: flex;
  flex-direction: column;
  gap: .2rem;
  min-width: 0;
}
.ladder__rung-type {
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
}
.ladder__rung-type--mine  { color: var(--color-spice-market); }
.ladder__rung-type--steal { color: var(--color-steal); }
.ladder__rung-type--grab  { color: var(--color-grab); }

.ladder__rung-desc {
  font-size: .75rem;
  color: var(--color-seashell-muted);
  line-height: 1.4;
}
.ladder__rung-queue {
  font-size: .68rem;
  color: rgba(254,243,238,.3);
  letter-spacing: .06em;
}

/* Right side */
.ladder__rung-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: .5rem;
  flex-shrink: 0;
}

.ladder__rung-price {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-seashell);
  white-space: nowrap;
}
.ladder__rung-price--grab { color: var(--color-grab); }

.ladder__rung-reason {
  font-size: .7rem;
  color: rgba(254,243,238,.3);
  text-align: right;
  max-width: 130px;
  line-height: 1.3;
}

/* Claim buttons */
.ladder__rung-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-body);
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
  padding: .55rem 1.25rem;
  border-radius: var(--radius);
  border: none;
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
  text-decoration: none;
  min-width: 70px;
}
.ladder__rung-btn:disabled { opacity: .35; cursor: not-allowed; }
.ladder__rung-btn--mine {
  background: var(--color-spice-market);
  color: var(--color-seashell);
}
.ladder__rung-btn--mine:not(:disabled):hover  { background: #C8501F; }
.ladder__rung-btn--steal {
  background: var(--color-steal);
  color: var(--color-seashell);
}
.ladder__rung-btn--steal:not(:disabled):hover { background: #C0601A; }
.ladder__rung-btn--grab {
  background: var(--color-grab);
  color: var(--color-balsamico);
  font-weight: 900;
}
.ladder__rung-btn--grab:not(:disabled):hover  { background: #D49040; }

/* Connector arrows between rungs */
.ladder__connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0;
  gap: 0;
  pointer-events: none;
}
.ladder__connector-line {
  display: block;
  width: 1px;
  height: 12px;
  background: var(--color-balsamico-border);
}
.ladder__connector-arrow {
  font-size: .65rem;
  color: rgba(254,243,238,.18);
  line-height: 1;
}
</style>
