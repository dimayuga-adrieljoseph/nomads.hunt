<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { claimService } from '@/services/claim.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

interface ClaimEntry {
  id: number; user_id: number; type: 'mine' | 'steal' | 'grab'; position: number
  status: string; amount: number; expires_at: string | null; created_at: string
  phase: 'claim' | 'payment' | null
  claim_expires_at: string | null
  payment_starts_at: string | null
  payment_expires_at: string | null
  can_pay: boolean
  user: { id: number; name: string; email: string } | null
  order: { id: number; order_number: string; payment_status: string; status: string; expires_at: string | null } | null
}

interface ProductSummary { id: number; name: string; status: string }

const route       = useRoute()
const product     = ref<ProductSummary | null>(null)
const claims      = ref<ClaimEntry[]>([])
const activeClaim = ref<ClaimEntry | null>(null)
const loading     = ref(true)
const forceExpiring = ref(false)
const message     = ref('')
const msgType     = ref<'success' | 'error'>('success')
const productId   = Number(route.params.id)

async function load() {
  loading.value = true
  try {
    const data        = await claimService.adminProductClaims(productId)
    product.value     = data.product
    claims.value      = data.claims
    activeClaim.value = data.active_claim
  } finally { loading.value = false }
}

async function forceExpire() {
  if (!activeClaim.value || forceExpiring.value) return
  if (!confirm(`Force-expire ${activeClaim.value.user?.name ?? 'this'}'s ${activeClaim.value.type.toUpperCase()} claim?`)) return
  forceExpiring.value = true
  try {
    await claimService.adminForceExpire(activeClaim.value.id)
    showMsg('Claim force-expired. Queue has advanced.', 'success')
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    showMsg(err.response?.data?.message ?? 'Force expire failed.', 'error')
  } finally { forceExpiring.value = false }
}

function showMsg(text: string, type: 'success' | 'error') {
  message.value = text; msgType.value = type
  setTimeout(() => { message.value = '' }, 5000)
}

function mineQueue()  { return claims.value.filter(c => c.type === 'mine'  && ['active','waiting'].includes(c.status)).sort((a,b) => a.position - b.position) }
function stealQueue() { return claims.value.filter(c => c.type === 'steal' && ['active','waiting'].includes(c.status)).sort((a,b) => a.position - b.position) }
function history()    { return claims.value.filter(c => !['active','waiting'].includes(c.status)).sort((a,b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()) }
function statusClass(status: string) {
  const m: Record<string,string> = { active:'badge--active', waiting:'badge--waiting', completed:'badge--completed', expired:'badge--expired', overridden:'badge--overridden', cancelled:'badge--cancelled' }
  return m[status] ?? 'badge--default'
}

/** How a finished claim ended — distinguishes the two-stage failure modes. */
function outcomeLabel(c: ClaimEntry): string {
  if (c.status === 'expired') return c.payment_starts_at ? 'PAYMENT EXPIRED' : 'CLAIM EXPIRED'
  return c.status.toUpperCase()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin / Claims</p>
        <RouterLink to="/admin/claims" class="back-link">← All Claims</RouterLink>
        <h1 class="admin-page-title">{{ product?.name ?? 'Claim Detail' }}</h1>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load">↻ Refresh</button>
    </div>

    <div v-if="loading" class="spinner" />

    <template v-else>
      <div v-if="message" :class="['alert', msgType === 'success' ? 'alert--success' : 'alert--error']" role="alert">
        {{ message }}
      </div>

      <!-- Status card -->
      <div class="status-card">
        <div class="status-card__section">
          <span class="status-card__label">Product Status</span>
          <span :class="['badge', `badge--${product?.status?.replace('_pending','')}`]">
            {{ (product?.status ?? '—').replace('_', ' ').toUpperCase() }}
          </span>
        </div>

        <template v-if="activeClaim">
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">Lifecycle Stage</span>
            <span v-if="activeClaim.phase === 'payment'" class="badge badge--grab">PAYMENT WINDOW</span>
            <span v-else-if="activeClaim.phase === 'claim'" class="badge badge--active">CLAIM PERIOD</span>
            <span v-else class="status-card__muted">—</span>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">Active Claimant</span>
            <RouterLink :to="`/admin/customers/${activeClaim.user?.id}`" class="table-link status-card__value">
              {{ activeClaim.user?.name ?? '—' }}
            </RouterLink>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">Type / Amount</span>
            <span class="status-card__value">
              <span :class="['badge', `badge--${activeClaim.type}`]">{{ activeClaim.type.toUpperCase() }}</span>
              &nbsp; {{ formatPeso(activeClaim.amount) }}
            </span>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">Payment</span>
            <span :class="['badge', activeClaim.order ? `badge--${activeClaim.order.payment_status}` : 'badge--cancelled']">
              {{ activeClaim.order?.payment_status?.toUpperCase() ?? 'CLOSED' }}
            </span>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">
              {{ activeClaim.phase === 'payment' ? 'Payment Expires In' : 'Claim Expires In' }}
            </span>
            <ClaimTimer v-if="activeClaim.expires_at" :expires-at="activeClaim.expires_at" style="font-size:1.4rem;font-weight:700;color:var(--color-spice-market)" @expired="load" />
            <span v-else class="status-card__muted">—</span>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">Claim Started / Expires</span>
            <span class="status-card__value status-card__value--stamps">
              {{ formatDate(activeClaim.created_at) }}<br />
              → {{ formatDate(activeClaim.claim_expires_at) }}
            </span>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section">
            <span class="status-card__label">Payment Started / Expires</span>
            <span class="status-card__value status-card__value--stamps">
              {{ activeClaim.payment_starts_at ? formatDate(activeClaim.payment_starts_at) : '—' }}<br />
              → {{ activeClaim.payment_expires_at ? formatDate(activeClaim.payment_expires_at) : '—' }}
            </span>
          </div>
          <div class="status-card__divider" aria-hidden="true" />
          <div class="status-card__section status-card__section--action">
            <button class="btn btn--danger" :disabled="forceExpiring" @click="forceExpire">
              {{ forceExpiring ? 'Expiring…' : '⚡ Force Expire' }}
            </button>
            <p class="force-hint">
              Claim period → opens the payment window · Payment window → moves to the next claimant
            </p>
          </div>
        </template>

        <div v-else-if="product?.status !== 'sold'" class="status-card__section">
          <span class="status-card__muted">No active claim.</span>
        </div>
      </div>

      <!-- Queues -->
      <div v-if="mineQueue().length || stealQueue().length" class="queues-grid">
        <div v-if="mineQueue().length" class="queue-card">
          <h2 class="queue-card__title queue-card__title--mine">Mine Queue</h2>
          <div class="queue-list">
            <div v-for="(c, i) in mineQueue()" :key="c.id" class="queue-row">
              <span class="queue-row__pos">#{{ i + 1 }}</span>
              <RouterLink :to="`/admin/customers/${c.user?.id}`" class="table-link queue-row__name">{{ c.user?.name ?? '—' }}</RouterLink>
              <span :class="['badge', statusClass(c.status)]">{{ c.status }}</span>
              <span v-if="c.status === 'active' && c.phase" class="badge badge--phase">
                {{ c.phase === 'payment' ? 'payment' : 'claim' }}
              </span>
              <span class="queue-row__amount">{{ formatPeso(c.amount) }}</span>
              <ClaimTimer v-if="c.status === 'active' && c.expires_at" :expires-at="c.expires_at" style="font-size:.82rem" />
            </div>
          </div>
        </div>
        <div v-if="stealQueue().length" class="queue-card">
          <h2 class="queue-card__title queue-card__title--steal">Steal Queue</h2>
          <div class="queue-list">
            <div v-for="(c, i) in stealQueue()" :key="c.id" class="queue-row">
              <span class="queue-row__pos">#{{ i + 1 }}</span>
              <RouterLink :to="`/admin/customers/${c.user?.id}`" class="table-link queue-row__name">{{ c.user?.name ?? '—' }}</RouterLink>
              <span :class="['badge', statusClass(c.status)]">{{ c.status }}</span>
              <span v-if="c.status === 'active' && c.phase" class="badge badge--phase">
                {{ c.phase === 'payment' ? 'payment' : 'claim' }}
              </span>
              <span class="queue-row__amount">{{ formatPeso(c.amount) }}</span>
              <ClaimTimer v-if="c.status === 'active' && c.expires_at" :expires-at="c.expires_at" style="font-size:.82rem" />
            </div>
          </div>
        </div>
      </div>

      <!-- History table -->
      <div class="history-section">
        <h2 class="section-title">Claim History</h2>
        <div class="table-wrap admin-table-card">
          <table>
            <thead>
              <tr>
                <th>Customer</th><th>Type</th><th>#</th><th>Status</th>
                <th>Amount</th><th>Payment</th><th>Claim Expires</th><th>Payment Expires</th><th>Created</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in [...mineQueue(), ...stealQueue(), ...claims.filter(x => x.type === 'grab' && ['active','waiting'].includes(x.status)), ...history()]" :key="`r-${c.id}`" :class="{ 'tr--live': ['active','waiting'].includes(c.status) }">
                <td><RouterLink :to="`/admin/customers/${c.user?.id}`" class="table-link">{{ c.user?.name ?? '—' }}</RouterLink></td>
                <td><span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span></td>
                <td class="td-muted">#{{ c.position }}</td>
                <td>
                  <span :class="['badge', statusClass(c.status)]">{{ outcomeLabel(c) }}</span>
                  <span v-if="c.status === 'active' && c.phase" class="badge badge--phase">
                    {{ c.phase === 'payment' ? 'payment' : 'claim' }}
                  </span>
                </td>
                <td class="td-price">{{ formatPeso(c.amount) }}</td>
                <td>
                  <span v-if="c.order" :class="['badge', `badge--${c.order.payment_status}`]">{{ c.order.payment_status.toUpperCase() }}</span>
                  <span v-else class="td-muted">—</span>
                </td>
                <td class="td-date">{{ formatDate(c.claim_expires_at) }}</td>
                <td class="td-date">{{ formatDate(c.payment_expires_at) }}</td>
                <td class="td-date">{{ formatDate(c.created_at) }}</td>
              </tr>
              <tr v-if="claims.length === 0"><td colspan="9" class="empty-cell">No claims for this product.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.75rem; gap: 1rem; }
.admin-eyebrow { font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .25rem; }
.back-link { font-size: .75rem; color: var(--color-seashell-muted); display: inline-block; margin-bottom: .375rem; transition: color var(--transition-fast); }
.back-link:hover { color: var(--color-spice-market); }
.admin-page-title { font-size: 1.5rem; font-weight: 700; color: var(--color-seashell); }

/* Status card */
.status-card {
  display: flex; flex-wrap: wrap; align-items: center;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.75rem; gap: 0;
}
.status-card__section { padding: .5rem 1.5rem; display: flex; flex-direction: column; gap: .375rem; }
.status-card__section--action { justify-content: center; align-items: flex-start; }
.status-card__divider { width: 1px; background: var(--color-balsamico-border); align-self: stretch; margin: .25rem 0; }
.status-card__label { font-size: .62rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--color-seashell-muted); }
.status-card__value { font-size: .9rem; font-weight: 600; color: var(--color-seashell); display: flex; align-items: center; gap: .5rem; }
.status-card__value--stamps { font-size: .78rem; font-weight: 500; line-height: 1.6; color: var(--color-seashell-muted); }
.status-card__muted { font-size: .875rem; color: var(--color-seashell-muted); }
.force-hint { font-size: .68rem; color: var(--color-seashell-muted); margin-top: .375rem; }

/* Queues */
.queues-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem; }
.queue-card {
  padding: 1.25rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
}
.queue-card__title { font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin-bottom: .875rem; }
.queue-card__title--mine  { color: var(--color-spice-market); }
.queue-card__title--steal { color: var(--color-steal); }
.queue-list { display: flex; flex-direction: column; gap: .125rem; }
.queue-row { display: flex; align-items: center; gap: .625rem; padding: .5rem 0; border-bottom: 1px solid var(--color-balsamico-border); font-size: .875rem; }
.queue-row:last-child { border-bottom: none; }
.queue-row__pos    { color: var(--color-seashell-muted); width: 1.5rem; flex-shrink: 0; }
.queue-row__name   { flex: 1; }
.queue-row__amount { margin-left: auto; font-weight: 600; flex-shrink: 0; font-size: .82rem; }

/* History */
.history-section {}
.section-title { font-size: .65rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--color-seashell-muted); margin-bottom: .875rem; }
.admin-table-card { background: var(--color-balsamico-light); border: 1px solid var(--color-balsamico-border); border-radius: var(--radius); }
.table-link { color: var(--color-seashell-strong); font-weight: 500; font-size: .875rem; transition: color var(--transition-fast); }
.table-link:hover { color: var(--color-spice-market); }
.td-muted  { color: var(--color-seashell-muted); font-size: .82rem; }
.td-date   { color: rgba(254,243,238,.3); font-size: .72rem; white-space: nowrap; }
.td-price  { font-weight: 600; font-size: .875rem; }
.empty-cell { text-align: center; color: var(--color-seashell-muted); padding: 2rem; }
.tr--live td { background: rgba(186,68,29,.04); }
</style>
