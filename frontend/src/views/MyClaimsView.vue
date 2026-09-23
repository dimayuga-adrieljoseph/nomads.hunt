<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { claimService, type ClaimRecord } from '@/services/claim.service'
import { formatPeso, formatDate } from '@/utils/formatters'
import ClaimTimer from '@/components/ClaimTimer.vue'

const claims  = ref<{ active: ClaimRecord[]; waiting: ClaimRecord[]; completed: ClaimRecord[]; expired: ClaimRecord[] }>({ active: [], waiting: [], completed: [], expired: [] })
const loading = ref(true)

async function load() {
  loading.value = true
  try { claims.value = await claimService.myClaims() }
  finally { loading.value = false }
}

onMounted(load)
</script>

<template>
  <div class="page-content">
    <div class="container">
      <h1 style="margin-bottom:2rem">My Claims</h1>

      <div v-if="loading" class="spinner" />

      <template v-else>
        <!-- Active -->
        <section v-if="claims.active.length > 0" class="claims-section">
          <h2 class="claims-section__title claims-section__title--active">Active</h2>
          <div class="claims-list">
            <div v-for="c in claims.active" :key="c.id" class="claim-item card">
              <div class="claim-item__img-wrap">
                <img v-if="c.product?.image_url" :src="c.product.image_url" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </div>
              <div class="claim-item__body">
                <div class="claim-item__product-name">{{ c.product?.name ?? `Product #${c.product_id}` }}</div>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span class="badge badge--active">ACTIVE</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <div v-if="c.expires_at" class="claim-item__timer">
                  <ClaimTimer :expires-at="c.expires_at" @expired="load" />
                  <span style="font-size:.75rem;color:var(--color-muted);margin-left:.375rem">remaining</span>
                </div>
              </div>
              <div class="claim-item__actions">
                <RouterLink
                  v-if="c.order"
                  :to="`/orders/${c.order.id}/pay`"
                  class="btn btn--primary btn--sm"
                >Pay Now</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- Waiting -->
        <section v-if="claims.waiting.length > 0" class="claims-section">
          <h2 class="claims-section__title claims-section__title--waiting">Waiting</h2>
          <div class="claims-list">
            <div v-for="c in claims.waiting" :key="c.id" class="claim-item card">
              <div class="claim-item__img-wrap">
                <img v-if="c.product?.image_url" :src="c.product.image_url" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </div>
              <div class="claim-item__body">
                <div class="claim-item__product-name">{{ c.product?.name ?? `Product #${c.product_id}` }}</div>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span class="badge badge--waiting">QUEUE #{{ c.position }}</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <p class="claim-item__queue-note">Waiting for the current claimant.</p>
              </div>
              <div class="claim-item__actions">
                <RouterLink :to="`/products/${c.product_id}`" class="btn btn--ghost btn--sm">View</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- Completed -->
        <section v-if="claims.completed.length > 0" class="claims-section">
          <h2 class="claims-section__title claims-section__title--completed">Completed</h2>
          <div class="claims-list">
            <div v-for="c in claims.completed" :key="c.id" class="claim-item card claim-item--dim">
              <div class="claim-item__img-wrap">
                <img v-if="c.product?.image_url" :src="c.product.image_url" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </div>
              <div class="claim-item__body">
                <div class="claim-item__product-name">{{ c.product?.name ?? `Product #${c.product_id}` }}</div>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span class="badge badge--completed">COMPLETED</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <div class="claim-item__date">{{ formatDate(c.created_at) }}</div>
              </div>
              <div class="claim-item__actions">
                <RouterLink v-if="c.order" :to="`/receipt/${c.order.id}`" class="btn btn--ghost btn--sm">Receipt</RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- Expired / overridden / cancelled -->
        <section v-if="claims.expired.length > 0" class="claims-section">
          <h2 class="claims-section__title claims-section__title--expired">Expired / Overridden</h2>
          <div class="claims-list">
            <div v-for="c in claims.expired" :key="c.id" class="claim-item card claim-item--dim">
              <div class="claim-item__img-wrap">
                <img v-if="c.product?.image_url" :src="c.product.image_url" class="claim-item__img" />
                <div v-else class="claim-item__img-placeholder" />
              </div>
              <div class="claim-item__body">
                <div class="claim-item__product-name">{{ c.product?.name ?? `Product #${c.product_id}` }}</div>
                <div class="claim-item__meta">
                  <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                  <span :class="['badge', `badge--${c.status}`]">{{ c.status.toUpperCase() }}</span>
                  <span class="claim-item__amount">{{ formatPeso(c.amount) }}</span>
                </div>
                <div class="claim-item__date">{{ formatDate(c.created_at) }}</div>
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
          class="empty-state"
        >
          <p>You haven't placed any claims yet.</p>
          <RouterLink to="/" class="btn btn--primary btn--sm">Browse Catalog</RouterLink>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.claims-section { margin-bottom: 2.5rem; }
.claims-section__title {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  margin-bottom: 1rem;
  padding-bottom: .5rem;
  border-bottom: 1px solid var(--color-border);
}
.claims-section__title--active    { color: var(--color-grab); }
.claims-section__title--waiting   { color: #818cf8; }
.claims-section__title--completed { color: #34d399; }
.claims-section__title--expired   { color: var(--color-muted); }

.claims-list { display: flex; flex-direction: column; gap: .75rem; }

.claim-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
}
.claim-item--dim { opacity: .7; }

.claim-item__img-wrap { width: 64px; height: 64px; flex-shrink: 0; border-radius: var(--radius); overflow: hidden; background: var(--color-surface-2); }
.claim-item__img { width: 100%; height: 100%; object-fit: cover; }
.claim-item__img-placeholder { width: 100%; height: 100%; }

.claim-item__body { flex: 1; display: flex; flex-direction: column; gap: .375rem; min-width: 0; }
.claim-item__product-name { font-weight: 600; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.claim-item__meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.claim-item__amount { font-weight: 700; margin-left: auto; }
.claim-item__timer { display: flex; align-items: center; font-size: 1.25rem; }
.claim-item__queue-note { font-size: .8rem; color: var(--color-muted); }
.claim-item__date { font-size: .75rem; color: var(--color-muted); }

.claim-item__actions { flex-shrink: 0; }

.empty-state { text-align: center; padding: 4rem 1rem; color: var(--color-muted); display: flex; flex-direction: column; align-items: center; gap: 1rem; }
</style>
