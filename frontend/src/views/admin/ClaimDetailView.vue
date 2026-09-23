<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { claimService } from '@/services/claim.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

interface ClaimEntry {
  id: number
  user_id: number
  type: 'mine' | 'steal' | 'grab'
  position: number
  status: string
  amount: number
  expires_at: string | null
  created_at: string
  user: { id: number; name: string; email: string } | null
  order: {
    id: number
    order_number: string
    payment_status: string
    status: string
    expires_at: string | null
  } | null
}

interface ProductSummary {
  id: number
  name: string
  status: string
}

const route   = useRoute()
const product = ref<ProductSummary | null>(null)
const claims  = ref<ClaimEntry[]>([])
const activeClaim = ref<ClaimEntry | null>(null)
const loading = ref(true)
const forceExpiring = ref(false)
const message = ref('')
const msgType = ref<'success' | 'error'>('success')

const productId = Number(route.params.id)

async function load() {
  loading.value = true
  try {
    const data = await claimService.adminProductClaims(productId)
    product.value     = data.product
    claims.value      = data.claims
    activeClaim.value = data.active_claim
  } finally {
    loading.value = false
  }
}

async function forceExpire() {
  if (!activeClaim.value || forceExpiring.value) return
  if (!confirm(`Force-expire ${activeClaim.value.user?.name ?? 'this'}'s ${activeClaim.value.type.toUpperCase()} claim and advance the queue?`)) return

  forceExpiring.value = true
  try {
    await claimService.adminForceExpire(activeClaim.value.id)
    showMsg('Claim force-expired. Queue has been advanced.', 'success')
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    showMsg(err.response?.data?.message ?? 'Force expire failed.', 'error')
  } finally {
    forceExpiring.value = false
  }
}

function showMsg(text: string, type: 'success' | 'error') {
  message.value = text
  msgType.value = type
  setTimeout(() => { message.value = '' }, 5000)
}

// Group claims into mine queue, steal queue, grab, and history
function mineQueue()  { return claims.value.filter(c => c.type === 'mine'  && ['active','waiting'].includes(c.status)).sort((a,b) => a.position - b.position) }
function stealQueue() { return claims.value.filter(c => c.type === 'steal' && ['active','waiting'].includes(c.status)).sort((a,b) => a.position - b.position) }
function history()    { return claims.value.filter(c => !['active','waiting'].includes(c.status)).sort((a,b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()) }

function statusColorClass(status: string) {
  const map: Record<string,string> = {
    active: 'badge--active', waiting: 'badge--waiting',
    completed: 'badge--completed', expired: 'badge--expired',
    overridden: 'badge--overridden', cancelled: 'badge--cancelled',
  }
  return map[status] ?? 'badge--default'
}

onMounted(load)
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <RouterLink to="/admin/claims" class="back-link">← All Claims</RouterLink>
        <h1 class="admin-page-title">{{ product?.name ?? 'Claim Detail' }}</h1>
      </div>
      <button class="btn btn--ghost btn--sm" @click="load">↻ Refresh</button>
    </div>

    <div v-if="loading" class="spinner" />

    <template v-else>
      <div v-if="message" :class="['alert', msgType === 'success' ? 'alert--success' : 'alert--error']" style="margin-bottom:1rem">
        {{ message }}
      </div>

      <!-- Current status card -->
      <div class="status-card card">
        <div class="status-card__left">
          <div class="status-card__label">Current Status</div>
          <div class="status-card__status">
            <span :class="['badge', `badge--${product?.status?.replace('_pending','')}`]" style="font-size:.85rem;padding:.35rem .9rem">
              {{ (product?.status ?? '—').replace('_', ' ').toUpperCase() }}
            </span>
          </div>
        </div>

        <template v-if="activeClaim">
          <div class="status-card__divider" />
          <div class="status-card__section">
            <div class="status-card__label">Active Claimant</div>
            <RouterLink :to="`/admin/customers/${activeClaim.user?.id}`" class="status-card__value table-link">
              {{ activeClaim.user?.name ?? '—' }}
            </RouterLink>
          </div>
          <div class="status-card__divider" />
          <div class="status-card__section">
            <div class="status-card__label">Claim Type</div>
            <span :class="['badge', `badge--${activeClaim.type}`]">{{ activeClaim.type.toUpperCase() }}</span>
          </div>
          <div class="status-card__divider" />
          <div class="status-card__section">
            <div class="status-card__label">Amount</div>
            <div class="status-card__value">{{ formatPeso(activeClaim.amount) }}</div>
          </div>
          <div class="status-card__divider" />
          <div class="status-card__section">
            <div class="status-card__label">Payment</div>
            <span :class="['badge', activeClaim.order ? `badge--${activeClaim.order.payment_status}` : 'badge--cancelled']">
              {{ activeClaim.order?.payment_status?.toUpperCase() ?? 'NO ORDER' }}
            </span>
          </div>
          <div class="status-card__divider" />
          <div class="status-card__section">
            <div class="status-card__label">Time Remaining</div>
            <div v-if="activeClaim.expires_at" class="status-card__timer">
              <ClaimTimer :expires-at="activeClaim.expires_at" style="font-size:1.5rem" @expired="load" />
            </div>
            <span v-else style="color:var(--color-muted)">—</span>
          </div>
          <div class="status-card__divider" />
          <div class="status-card__section status-card__section--action">
            <button
              class="btn btn--danger"
              :disabled="forceExpiring"
              @click="forceExpire"
            >
              {{ forceExpiring ? 'Expiring…' : '⚡ FORCE EXPIRE' }}
            </button>
            <p class="force-expire-hint">Runs identical logic to a real timeout</p>
          </div>
        </template>

        <div v-else-if="product?.status !== 'sold'" class="status-card__section">
          <span style="color:var(--color-muted);font-size:.875rem">No active claim.</span>
        </div>
      </div>

      <!-- Queues side by side -->
      <div class="queues-grid">
        <!-- Mine queue -->
        <div v-if="mineQueue().length > 0" class="queue-card card">
          <h2 class="queue-card__title queue-card__title--mine">Mine Queue</h2>
          <div class="queue-list">
            <div v-for="(c, i) in mineQueue()" :key="c.id" class="queue-row">
              <span class="queue-row__pos">#{{ i + 1 }}</span>
              <RouterLink :to="`/admin/customers/${c.user?.id}`" class="queue-row__name table-link">
                {{ c.user?.name ?? '—' }}
              </RouterLink>
              <span :class="['badge', statusColorClass(c.status)]">{{ c.status }}</span>
              <span class="queue-row__amount">{{ formatPeso(c.amount) }}</span>
              <div v-if="c.status === 'active' && c.expires_at" class="queue-row__timer">
                <ClaimTimer :expires-at="c.expires_at" style="font-size:.85rem" />
              </div>
            </div>
          </div>
        </div>

        <!-- Steal queue -->
        <div v-if="stealQueue().length > 0" class="queue-card card">
          <h2 class="queue-card__title queue-card__title--steal">Steal Queue</h2>
          <div class="queue-list">
            <div v-for="(c, i) in stealQueue()" :key="c.id" class="queue-row">
              <span class="queue-row__pos">#{{ i + 1 }}</span>
              <RouterLink :to="`/admin/customers/${c.user?.id}`" class="queue-row__name table-link">
                {{ c.user?.name ?? '—' }}
              </RouterLink>
              <span :class="['badge', statusColorClass(c.status)]">{{ c.status }}</span>
              <span class="queue-row__amount">{{ formatPeso(c.amount) }}</span>
              <div v-if="c.status === 'active' && c.expires_at" class="queue-row__timer">
                <ClaimTimer :expires-at="c.expires_at" style="font-size:.85rem" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Full claim history -->
      <div class="history-section">
        <h2 class="section-title">Claim History</h2>
        <div class="card table-wrap">
          <table>
            <thead>
              <tr>
                <th>Customer</th>
                <th>Type</th>
                <th>#</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Payment</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <!-- Active / waiting first -->
              <tr
                v-for="c in [...mineQueue(), ...stealQueue(), ...claims.filter(x => x.type === 'grab' && ['active','waiting'].includes(x.status))]"
                :key="`live-${c.id}`"
                class="tr--live"
              >
                <td>
                  <RouterLink :to="`/admin/customers/${c.user?.id}`" class="table-link">
                    {{ c.user?.name ?? '—' }}
                  </RouterLink>
                </td>
                <td><span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span></td>
                <td style="color:var(--color-muted)">#{{ c.position }}</td>
                <td><span :class="['badge', statusColorClass(c.status)]">{{ c.status }}</span></td>
                <td>{{ formatPeso(c.amount) }}</td>
                <td>
                  <span v-if="c.order" :class="['badge', `badge--${c.order.payment_status}`]">
                    {{ c.order.payment_status.toUpperCase() }}
                  </span>
                  <span v-else style="color:var(--color-muted)">—</span>
                </td>
                <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">{{ formatDate(c.created_at) }}</td>
              </tr>
              <!-- Historical -->
              <tr v-for="c in history()" :key="`hist-${c.id}`">
                <td>
                  <RouterLink :to="`/admin/customers/${c.user?.id}`" class="table-link">
                    {{ c.user?.name ?? '—' }}
                  </RouterLink>
                </td>
                <td><span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span></td>
                <td style="color:var(--color-muted)">#{{ c.position }}</td>
                <td><span :class="['badge', statusColorClass(c.status)]">{{ c.status }}</span></td>
                <td>{{ formatPeso(c.amount) }}</td>
                <td>
                  <span v-if="c.order" :class="['badge', `badge--${c.order.payment_status}`]">
                    {{ c.order.payment_status.toUpperCase() }}
                  </span>
                  <span v-else style="color:var(--color-muted)">—</span>
                </td>
                <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">{{ formatDate(c.created_at) }}</td>
              </tr>
              <tr v-if="claims.length === 0">
                <td colspan="7" style="text-align:center;color:var(--color-muted);padding:2rem">No claims for this product.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-page-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem; }
.admin-page-title  { font-size:1.5rem;font-weight:700;margin-top:.25rem; }
.back-link { font-size:.8rem;color:var(--color-muted);display:inline-block;margin-bottom:.375rem; }
.back-link:hover { color:var(--color-text); }

/* Status card */
.status-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.5rem;
}
.status-card__label { font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--color-muted);margin-bottom:.375rem; }
.status-card__value { font-size:.95rem;font-weight:600; }
.status-card__timer { font-size:1.5rem;font-weight:700; }
.status-card__section { padding:.5rem 1.5rem;display:flex;flex-direction:column;gap:.25rem; }
.status-card__section--action { justify-content:center; }
.status-card__left    { padding:.5rem 1.5rem .5rem 0; display:flex;flex-direction:column;gap:.25rem; }
.status-card__divider { width:1px;background:var(--color-border);align-self:stretch;margin:.25rem 0; }
.force-expire-hint { font-size:.7rem;color:var(--color-muted);margin-top:.375rem;text-align:center; }

/* Queues */
.queues-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.25rem;margin-bottom:1.5rem; }
.queue-card { padding:1.25rem; }
.queue-card__title { font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.875rem; }
.queue-card__title--mine  { color:var(--color-mine); }
.queue-card__title--steal { color:var(--color-steal); }
.queue-list { display:flex;flex-direction:column;gap:.25rem; }
.queue-row {
  display:flex;align-items:center;gap:.625rem;
  padding:.5rem 0;border-bottom:1px solid var(--color-border);font-size:.875rem;
}
.queue-row:last-child { border-bottom:none; }
.queue-row__pos    { color:var(--color-muted);width:1.5rem;flex-shrink:0; }
.queue-row__name   { flex:1; }
.queue-row__amount { margin-left:auto;font-weight:600;flex-shrink:0; }
.queue-row__timer  { flex-shrink:0; }

/* History */
.history-section { margin-top:.5rem; }
.section-title {
  font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--color-muted);margin-bottom:.875rem;
}
.tr--live td { background:rgba(255,255,255,.02); }

.table-link { color:var(--color-text);font-weight:500; }
.table-link:hover { text-decoration:underline; }
</style>
