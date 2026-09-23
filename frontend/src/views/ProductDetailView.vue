<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { productService, type Product } from '@/services/product.service'
import { claimService, type ClaimRecord } from '@/services/claim.service'
import ClaimLadder from '@/components/ClaimLadder.vue'
import { formatPeso, productStatusLabel, productStatusClass, conditionLabel, formatDate } from '@/utils/formatters'

const route   = useRoute()
const auth    = useAuthStore()
const product = ref<Product | null>(null)
const claims  = ref<ClaimRecord[]>([])
const loading = ref(true)

// The current user's active/waiting claim on this product
const myActiveClaim = computed<ClaimRecord | null>(() => {
  if (!auth.isCustomer) return null
  return claims.value.find(
    (c) => c.user_id === auth.user?.id && ['active', 'waiting'].includes(c.status)
  ) ?? null
})

// Separate mine and steal queues for the detail panel
const mineQueue  = computed(() => claims.value.filter((c) => c.type === 'mine'  && ['active', 'waiting'].includes(c.status)).sort((a, b) => a.position - b.position))
const stealQueue = computed(() => claims.value.filter((c) => c.type === 'steal' && ['active', 'waiting'].includes(c.status)).sort((a, b) => a.position - b.position))

async function load() {
  loading.value = true
  const id = Number(route.params.id)
  try {
    const [p, c] = await Promise.all([
      productService.get(id),
      productService.getClaims(id),
    ])
    product.value = p
    claims.value  = c
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-content">
    <div class="container">
      <!-- Back -->
      <RouterLink to="/" class="back-link">← Back to Catalog</RouterLink>

      <div v-if="loading" class="spinner" />

      <div v-else-if="product" class="detail">
        <!-- Left: product info -->
        <div class="detail__left">
          <!-- Image -->
          <div class="detail__img-wrap card">
            <img
              v-if="product.image_url"
              :src="product.image_url"
              :alt="product.name"
              class="detail__img"
            />
            <div v-else class="detail__img-placeholder">No Image</div>
          </div>

          <!-- Queue panels (visible when active) -->
          <div v-if="mineQueue.length > 0" class="queue-panel card">
            <h3 class="queue-panel__title">Mine Queue</h3>
            <div v-for="(c, i) in mineQueue" :key="c.id" class="queue-item">
              <span class="queue-item__pos">#{{ i + 1 }}</span>
              <span class="queue-item__name">{{ c.user_name ?? `Customer #${c.user_id}` }}</span>
              <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
            </div>
          </div>

          <div v-if="stealQueue.length > 0" class="queue-panel card">
            <h3 class="queue-panel__title queue-panel__title--steal">Steal Queue</h3>
            <div v-for="(c, i) in stealQueue" :key="c.id" class="queue-item">
              <span class="queue-item__pos">#{{ i + 1 }}</span>
              <span class="queue-item__name">{{ c.user_name ?? `Customer #${c.user_id}` }}</span>
              <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
            </div>
          </div>
        </div>

        <!-- Right: product details + claim ladder -->
        <div class="detail__right">
          <!-- Product header -->
          <div class="detail__header">
            <div class="detail__meta">
              <span class="detail__brand">{{ product.brand ?? 'Unknown Brand' }}</span>
              <span :class="['badge', productStatusClass(product.status)]">
                {{ productStatusLabel(product.status) }}
              </span>
            </div>
            <h1 class="detail__name">{{ product.name }}</h1>
          </div>

          <!-- Specs -->
          <div class="detail__specs">
            <div v-if="product.size" class="spec">
              <span class="spec__label">Size</span>
              <span class="spec__value">{{ product.size }}</span>
            </div>
            <div v-if="product.category" class="spec">
              <span class="spec__label">Category</span>
              <span class="spec__value">{{ product.category }}</span>
            </div>
            <div class="spec">
              <span class="spec__label">Condition</span>
              <span class="spec__value">{{ conditionLabel(product.condition) }}</span>
            </div>
          </div>

          <p v-if="product.description" class="detail__desc">{{ product.description }}</p>

          <hr class="divider" />

          <!-- Prices summary -->
          <div class="detail__prices">
            <div class="detail__price detail__price--mine">
              <span class="detail__price-label">Mine</span>
              <span class="detail__price-value">{{ formatPeso(product.mine_price) }}</span>
            </div>
            <div class="detail__price detail__price--steal">
              <span class="detail__price-label">Steal</span>
              <span class="detail__price-value">{{ formatPeso(product.steal_price) }}</span>
            </div>
            <div class="detail__price detail__price--grab">
              <span class="detail__price-label">Grab</span>
              <span class="detail__price-value">{{ formatPeso(product.grab_price) }}</span>
            </div>
          </div>

          <hr class="divider" />

          <!-- Claim ladder -->
          <ClaimLadder
            :product="product"
            :my-active-claim="myActiveClaim"
            @refresh="load"
          />

          <!-- Claim history -->
          <div v-if="claims.length > 0" class="claim-history">
            <h3 class="claim-history__title">Claim History</h3>
            <div v-for="c in claims" :key="c.id" class="claim-history__item">
              <span class="claim-history__user">{{ c.user_name ?? `Customer #${c.user_id}` }}</span>
              <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
              <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
              <span class="claim-history__date">{{ formatDate(c.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.back-link {
  display: inline-block;
  margin-bottom: 1.5rem;
  font-size: .875rem;
  color: var(--color-muted);
  transition: color .15s;
}
.back-link:hover { color: var(--color-text); }

.detail {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  align-items: start;
}
@media (max-width: 768px) {
  .detail { grid-template-columns: 1fr; }
}

.detail__left  { display: flex; flex-direction: column; gap: 1rem; }
.detail__right { display: flex; flex-direction: column; gap: 1.25rem; }

.detail__img-wrap { overflow: hidden; }
.detail__img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
}
.detail__img-placeholder {
  width: 100%;
  aspect-ratio: 4/3;
  background: var(--color-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-muted);
  font-size: .9rem;
}

.queue-panel { padding: 1rem; }
.queue-panel__title {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-mine);
  margin-bottom: .75rem;
}
.queue-panel__title--steal { color: var(--color-steal); }
.queue-item {
  display: flex;
  align-items: center;
  gap: .625rem;
  padding: .375rem 0;
  border-bottom: 1px solid var(--color-border);
  font-size: .85rem;
}
.queue-item:last-child { border-bottom: none; }
.queue-item__pos  { color: var(--color-muted); width: 1.5rem; }
.queue-item__name { flex: 1; }

.detail__header { display: flex; flex-direction: column; gap: .5rem; }
.detail__meta   { display: flex; align-items: center; gap: .75rem; }
.detail__brand  { font-size: .75rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--color-muted); }
.detail__name   { font-size: 1.75rem; line-height: 1.2; }

.detail__specs  { display: flex; flex-wrap: wrap; gap: .875rem; }
.spec { display: flex; flex-direction: column; gap: .2rem; }
.spec__label { font-size: .7rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: .08em; }
.spec__value  { font-weight: 600; font-size: .9rem; }

.detail__desc { font-size: .9rem; color: var(--color-muted); line-height: 1.65; }

.detail__prices {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .75rem;
}
.detail__price {
  padding: .875rem;
  border-radius: var(--radius);
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: .25rem;
}
.detail__price--mine  { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.2); }
.detail__price--steal { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.2); }
.detail__price--grab  { background: rgba(34,197,94,.1);  border: 1px solid rgba(34,197,94,.2); }
.detail__price-label  { font-size: .65rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.detail__price--mine  .detail__price-label  { color: var(--color-mine); }
.detail__price--steal .detail__price-label  { color: var(--color-steal); }
.detail__price--grab  .detail__price-label  { color: var(--color-grab); }
.detail__price-value  { font-size: 1.1rem; font-weight: 700; }

.claim-history { margin-top: 1rem; }
.claim-history__title {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: .75rem;
}
.claim-history__item {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
  padding: .5rem 0;
  border-bottom: 1px solid var(--color-border);
  font-size: .8rem;
}
.claim-history__item:last-child { border-bottom: none; }
.claim-history__user { font-weight: 600; flex: 1; min-width: 80px; }
.claim-history__date { color: var(--color-muted); font-size: .75rem; margin-left: auto; }
</style>
