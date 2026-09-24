<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { claimService, type ClaimRecord } from '@/services/claim.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

const claims  = ref<{ active: ClaimRecord[]; waiting: ClaimRecord[]; completed: ClaimRecord[]; expired: ClaimRecord[] }>({ active: [], waiting: [], completed: [], expired: [] })
const loading = ref(true)
const loadError = ref('')

/**
 * Only the CURRENT active claimant, whose claim stage has finished, may be sent
 * to payment. The server is authoritative via `can_pay` — these flags only keep
 * the UI honest.
 */
function canPayClaim(c: ClaimRecord): boolean {
  return c.status === 'active'
    && c.phase === 'payment'
    && c.can_pay === true
    && c.order?.payment_status === 'pending'
}

/**
 * Human label for how a claim ended, so history distinguishes the two-stage
 * outcomes (claim expired → payment, vs payment window expired, vs overridden).
 */
function outcomeLabel(c: ClaimRecord): string {
  if (c.status === 'overridden') return 'OVERRIDDEN'
  if (c.status === 'cancelled')  return 'CANCELLED'
  if (c.status === 'completed')  return 'COMPLETED'
  if (c.status === 'expired') {
    return c.payment_starts_at ? 'PAYMENT EXPIRED' : 'CLAIM EXPIRED'
  }
  return c.status.toUpperCase()
}

async function load() {
  loading.value = true
  loadError.value = ''
  try { claims.value = await claimService.myClaims() }
  catch { loadError.value = 'Failed to load your claims. Please try again.' }
  finally { loading.value = false }
}

onMounted(load)
</script>

<template>
  <div class="page-content">
    <div class="container">

      <div class="page-header">
        <div>
          <p class="page-header__eyebrow">/ Account</p>
          <h1 class="page-header__title display">My Claims</h1>
        </div>
        <RouterLink to="/catalog" class="btn btn--ghost btn--sm">Browse Rack</RouterLink>
      </div>

      <div v-if="loading" class="spinner" />

      <div v-else-if="loadError" class="page-empty" role="alert">
        <p>{{ loadError }}</p>
        <button class="btn btn--ghost btn--sm" @click="load">Try Again</button>
      </div>

      <template v-else>

        <!-- ── Active ─────────────────────────────────────────────── -->
        <section v-if="claims.active.length" class="claims-section" aria-labelledby="section-active">
          <h2 id="section-active" class="claims-section__label claims-section__label--active">Active</h2>
          <div class="claims-list">
            <div v-for="c in claims.active" :key="c.id" class="claim-item claim-item--active">
              <RouterLink :to="`/products/${c.product_id}`" class="claim-item__img-wrap" aria-label="View product">
                <img v-if="c.product?.image_url" :src="c.product.image_url" :alt="c.product?.name ?? 'Product'" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </RouterLink>
              <div class="claim-item__body">
                <RouterLink :to="`/products/${c.product_id}`" class="claim-item__name">
                  {{ c.product?.name ?? `Product #${c.product_id}` }}
                </RouterLink>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span v-if="c.phase === 'payment'" class="badge badge--grab">PAYMENT OPEN</span>
                  <span v-else class="badge badge--active">CLAIM ACTIVE</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <!-- Stage 1: claim period -->
                <div v-if="c.phase === 'claim' && c.claim_expires_at" class="claim-item__timer" aria-label="Claim expires in">
                  <ClaimTimer :expires-at="c.claim_expires_at" @expired="load" />
                  <span class="claim-item__timer-label">claim expires in</span>
                </div>
                <p v-if="c.phase === 'claim'" class="claim-item__note">
                  Hold the piece — payment opens when your claim period ends.
                </p>
                <!-- Stage 2: payment window -->
                <div v-if="c.phase === 'payment' && c.payment_expires_at" class="claim-item__timer" aria-label="Payment expires in">
                  <ClaimTimer :expires-at="c.payment_expires_at" @expired="load" />
                  <span class="claim-item__timer-label">payment expires in</span>
                </div>
                <p v-if="c.phase === 'payment' && !canPayClaim(c)" class="claim-item__note">
                  Payment window closed — the next claimant is being activated.
                </p>
              </div>
              <div class="claim-item__actions">
                <RouterLink v-if="canPayClaim(c)" :to="`/orders/${c.order!.id}/pay`" class="btn btn--primary btn--sm">Pay Now →</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Waiting ────────────────────────────────────────────── -->
        <section v-if="claims.waiting.length" class="claims-section" aria-labelledby="section-waiting">
          <h2 id="section-waiting" class="claims-section__label claims-section__label--waiting">Waiting</h2>
          <div class="claims-list">
            <div v-for="c in claims.waiting" :key="c.id" class="claim-item">
              <RouterLink :to="`/products/${c.product_id}`" class="claim-item__img-wrap" aria-label="View product">
                <img v-if="c.product?.image_url" :src="c.product.image_url" :alt="c.product?.name ?? 'Product'" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </RouterLink>
              <div class="claim-item__body">
                <RouterLink :to="`/products/${c.product_id}`" class="claim-item__name">
                  {{ c.product?.name ?? `Product #${c.product_id}` }}
                </RouterLink>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span class="badge badge--waiting">Queue #{{ c.position }}</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <p class="claim-item__note">Waiting for the current claimant's claim + payment periods to finish.</p>
              </div>
              <div class="claim-item__actions">
                <RouterLink :to="`/products/${c.product_id}`" class="btn btn--ghost btn--sm">View</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Completed ──────────────────────────────────────────── -->
        <section v-if="claims.completed.length" class="claims-section" aria-labelledby="section-completed">
          <h2 id="section-completed" class="claims-section__label claims-section__label--completed">Completed</h2>
          <div class="claims-list">
            <div v-for="c in claims.completed" :key="c.id" class="claim-item claim-item--dim">
              <RouterLink :to="`/products/${c.product_id}`" class="claim-item__img-wrap" aria-label="View product">
                <img v-if="c.product?.image_url" :src="c.product.image_url" :alt="c.product?.name ?? 'Product'" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </RouterLink>
              <div class="claim-item__body">
                <RouterLink :to="`/products/${c.product_id}`" class="claim-item__name">{{ c.product?.name ?? `Product #${c.product_id}` }}</RouterLink>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span class="badge badge--completed">COMPLETED</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <span class="claim-item__date">{{ formatDate(c.created_at) }}</span>
              </div>
              <div class="claim-item__actions">
                <RouterLink v-if="c.order" :to="`/receipt/${c.order.id}`" class="btn btn--ghost btn--sm">Receipt</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Expired / overridden ───────────────────────────────── -->
        <section v-if="claims.expired.length" class="claims-section" aria-labelledby="section-expired">
          <h2 id="section-expired" class="claims-section__label claims-section__label--expired">Expired / Overridden</h2>
          <div class="claims-list">
            <div v-for="c in claims.expired" :key="c.id" class="claim-item claim-item--dim">
              <RouterLink :to="`/products/${c.product_id}`" class="claim-item__img-wrap" aria-label="View product">
                <img v-if="c.product?.image_url" :src="c.product.image_url" :alt="c.product?.name ?? 'Product'" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </RouterLink>
              <div class="claim-item__body">
                <RouterLink :to="`/products/${c.product_id}`" class="claim-item__name">{{ c.product?.name ?? `Product #${c.product_id}` }}</RouterLink>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span :class="['badge', `badge--${c.status}`]">{{ outcomeLabel(c) }}</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <span class="claim-item__date">{{ formatDate(c.created_at) }}</span>
              </div>
              <div class="claim-item__actions">
                <RouterLink :to="`/products/${c.product_id}`" class="btn btn--ghost btn--sm">View</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- Empty state -->
        <div
          v-if="!claims.active.length && !claims.waiting.length && !claims.completed.length && !claims.expired.length"
          class="page-empty"
        >
          <p>You haven't placed any claims yet.</p>
          <RouterLink to="/catalog" class="btn btn--primary btn--sm">Browse Rack →</RouterLink>
        </div>

      </template>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 3rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid var(--color-balsamico-border);
  gap: 1rem;
  flex-wrap: wrap;
}
.page-header__eyebrow {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: var(--color-spice-market);
  margin-bottom: .5rem;
}
.page-header__title {
  font-size: clamp(2rem, 5vw, 3.5rem);
  color: var(--color-seashell);
}

.claims-section { margin-bottom: 3rem; }

.claims-section__label {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .18em;
  text-transform: uppercase;
  padding-bottom: .625rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--color-balsamico-border);
}
.claims-section__label--active    { color: var(--color-spice-market); border-bottom-color: var(--color-spice-border); }
.claims-section__label--waiting   { color: var(--color-seashell-muted); }
.claims-section__label--completed { color: var(--color-grab); }
.claims-section__label--expired   { color: rgba(254,243,238,.25); }

.claims-list { display: flex; flex-direction: column; gap: 1px; }

.claim-item {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  transition: border-color var(--transition-fast);
}
.claim-item:hover { border-color: var(--color-spice-border); }
.claim-item--active { border-left: 3px solid var(--color-spice-market); }
.claim-item--dim { opacity: .6; }

.claim-item__img-wrap {
  width: 68px; height: 68px;
  flex-shrink: 0;
  overflow: hidden;
  background: var(--color-balsamico-lighter);
  border: 1px solid var(--color-balsamico-border);
  display: block;
}
.claim-item__img { width: 100%; height: 100%; object-fit: cover; }
.claim-item__img-placeholder { width: 100%; height: 100%; }

.claim-item__body { flex: 1; display: flex; flex-direction: column; gap: .375rem; min-width: 0; }
.claim-item__name {
  font-weight: 600;
  font-size: .95rem;
  color: var(--color-seashell);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-decoration: none;
  transition: color var(--transition-fast);
}
.claim-item__name:hover { color: var(--color-spice-market); }

.claim-item__meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.claim-item__amount { font-weight: 700; font-size: .9rem; margin-left: auto; color: var(--color-seashell); }

.claim-item__timer {
  display: flex;
  align-items: baseline;
  gap: .375rem;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--color-spice-market);
}
.claim-item__timer-label { font-size: .72rem; color: var(--color-seashell-muted); font-weight: 400; }
.claim-item__note { font-size: .78rem; color: var(--color-seashell-muted); }
.claim-item__date { font-size: .75rem; color: rgba(254,243,238,.3); }

.claim-item__actions { flex-shrink: 0; }

.page-empty {
  text-align: center;
  padding: 6rem 1rem;
  color: var(--color-seashell-muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
}
</style>
