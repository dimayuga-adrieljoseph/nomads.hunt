<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { productService, type Product } from '@/services/product.service'
import { claimService, type ClaimRecord } from '@/services/claim.service'
import ClaimLadder from '@/components/ClaimLadder.vue'
import { productStatusLabel, productStatusClass, conditionLabel, formatDate } from '@/utils/formatters'

const route   = useRoute()
const auth    = useAuthStore()
const product = ref<Product | null>(null)
const claims  = ref<ClaimRecord[]>([])
const loading = ref(true)
const loadError = ref('')

// ── Gallery state ────────────────────────────────────────────────────────────
// Currently only one image per product; this is wired to support more when the
// backend returns an images array. For now images = [image_url] if set.
const activeImage  = ref<string | null>(null)
const galleryImages = computed<string[]>(() => {
  if (!product.value) return []
  const imgs: string[] = []
  if (product.value.image_url) imgs.push(product.value.image_url)
  return imgs
})

function selectImage(url: string) { activeImage.value = url }

// ── Claim state ──────────────────────────────────────────────────────────────
// myActiveClaim is sourced from the user's own /my-claims response so it
// always includes the order relationship needed for the payment button.
const myActiveClaim = ref<ClaimRecord | null>(null)

const mineQueue  = computed(() =>
  claims.value.filter((c) => c.type === 'mine'  && ['active','waiting'].includes(c.status))
    .sort((a, b) => a.position - b.position)
)
const stealQueue = computed(() =>
  claims.value.filter((c) => c.type === 'steal' && ['active','waiting'].includes(c.status))
    .sort((a, b) => a.position - b.position)
)

// ── Load ─────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  loadError.value = ''
  const id = Number(route.params.id)
  try {
    // Always fetch the product and its public claim history in parallel.
    // For authenticated customers, also fetch their own claims so we have
    // order data (needed for the payment button on active claims).
    const requests: [Promise<Product>, Promise<ClaimRecord[]>, Promise<import('@/services/claim.service').MyClaims | null>] = [
      productService.get(id),
      productService.getClaims(id),
      auth.isCustomer ? claimService.myClaims() : Promise.resolve(null),
    ]
    const [p, c, mine] = await Promise.all(requests)
    product.value     = p
    claims.value      = c
    activeImage.value = p.image_url ?? null

    // Pick the user's active or waiting claim for this product from myClaims,
    // which includes the order relationship required for the Pay button.
    if (mine) {
      const all = [...mine.active, ...mine.waiting]
      myActiveClaim.value = all.find((cl: ClaimRecord) => cl.product_id === id) ?? null
    } else {
      myActiveClaim.value = null
    }
  } catch {
    loadError.value = 'Failed to load product. Please try again.'
  } finally {
    loading.value = false
  }
}

// ── Sold / unavailable ───────────────────────────────────────────────────────
const isUnavailable = computed(() =>
  product.value?.status === 'sold'
)

// ── Keyboard: ESC to go back ─────────────────────────────────────────────────
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') history.back()
}
onMounted(async () => {
  await load()
  window.addEventListener('keydown', onKey)
})
onUnmounted(() => window.removeEventListener('keydown', onKey))

// ── History toggle ────────────────────────────────────────────────────────────
function toggleHistory(e: MouseEvent) {
  const btn = e.currentTarget as HTMLButtonElement
  const expanded = btn.getAttribute('aria-expanded') === 'true'
  btn.setAttribute('aria-expanded', String(!expanded))
  const panel = document.getElementById('claim-history')
  if (panel) (panel as HTMLElement).hidden = expanded
}
</script>

<template>
  <div class="detail-page">
    <div class="container">

      <!-- Back -->
      <RouterLink to="/catalog" class="detail-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Rack
      </RouterLink>

      <div v-if="loading" class="spinner" style="margin: 6rem auto" />

      <div v-else-if="loadError" class="page-error" role="alert">
        {{ loadError }}
      </div>

      <div v-else-if="product" class="detail">

        <!-- ── LEFT: Gallery ────────────────────────────────────────────── -->
        <div class="detail__gallery">

          <!-- Thumbnails column -->
          <div
            v-if="galleryImages.length > 1"
            class="detail__thumbs"
            role="list"
            aria-label="Product images"
          >
            <button
              v-for="(img, i) in galleryImages"
              :key="i"
              :class="['detail__thumb', { 'detail__thumb--active': activeImage === img }]"
              :aria-label="`View image ${i + 1}`"
              :aria-pressed="activeImage === img"
              role="listitem"
              @click="selectImage(img)"
            >
              <img :src="img" :alt="`${product.name} image ${i + 1}`" loading="lazy" />
            </button>
          </div>

          <!-- Main image -->
          <div
            class="detail__main-img-wrap"
            :class="{ 'detail__main-img-wrap--unavailable': isUnavailable }"
          >
            <img
              v-if="activeImage"
              :src="activeImage"
              :alt="product.name"
              class="detail__main-img"
            />
            <div v-else class="detail__main-img-placeholder">
              <span>NO IMAGE</span>
            </div>

            <!-- Status badge overlay -->
            <div class="detail__img-badge-wrap" aria-hidden="true">
              <span
                v-if="product.status !== 'available'"
                :class="['badge', productStatusClass(product.status), 'detail__img-badge']"
              >
                {{ productStatusLabel(product.status) }}
              </span>
            </div>

            <!-- SOLD overlay band -->
            <div v-if="isUnavailable" class="detail__sold-band" aria-label="This piece is sold">
              <span class="detail__sold-text display">SOLD</span>
            </div>
          </div>
        </div>

        <!-- ── RIGHT: Info + Claim Ladder ──────────────────────────────── -->
        <div class="detail__info">

          <!-- Status + brand -->
          <div class="detail__top-meta">
            <span class="detail__brand">{{ product.brand ?? 'Unknown Brand' }}</span>
            <span :class="['badge', productStatusClass(product.status)]">
              {{ productStatusLabel(product.status) }}
            </span>
          </div>

          <!-- Title -->
          <h1 class="detail__title display">{{ product.name }}</h1>

          <!-- Category tag -->
          <p v-if="product.category" class="detail__category">{{ product.category }}</p>

          <!-- Description -->
          <p v-if="product.description" class="detail__desc">{{ product.description }}</p>

          <!-- ── Metadata table ───────────────────────────────────────── -->
          <div class="detail__meta-table" role="table" aria-label="Product details">
            <div v-if="product.size" class="detail__meta-row" role="row">
              <span class="detail__meta-label" role="rowheader">Size</span>
              <span class="detail__meta-value" role="cell">{{ product.size }}</span>
            </div>
            <div v-if="product.category" class="detail__meta-row" role="row">
              <span class="detail__meta-label" role="rowheader">Category</span>
              <span class="detail__meta-value" role="cell">{{ product.category }}</span>
            </div>
            <div class="detail__meta-row" role="row">
              <span class="detail__meta-label" role="rowheader">Condition</span>
              <span class="detail__meta-value" role="cell">{{ conditionLabel(product.condition) }}</span>
            </div>
            <div v-if="product.brand" class="detail__meta-row" role="row">
              <span class="detail__meta-label" role="rowheader">Brand</span>
              <span class="detail__meta-value" role="cell">{{ product.brand }}</span>
            </div>
          </div>

          <div class="detail__divider" />

          <!-- ── Claim Ladder ─────────────────────────────────────────── -->
          <ClaimLadder
            :product="product"
            :my-active-claim="myActiveClaim"
            @refresh="load"
          />

          <!-- ── Queue info ───────────────────────────────────────────── -->
          <template v-if="mineQueue.length > 0 || stealQueue.length > 0">
            <div class="detail__divider" />
            <div class="detail__queues">
              <div v-if="mineQueue.length" class="detail__queue">
                <h3 class="detail__queue-title detail__queue-title--mine">Mine Queue</h3>
                <div v-for="(c, i) in mineQueue" :key="c.id" class="detail__queue-item">
                  <span class="detail__queue-pos">#{{ i + 1 }}</span>
                  <span class="detail__queue-name">{{ c.user_name ?? `Customer #${c.user_id}` }}</span>
                  <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
                </div>
              </div>
              <div v-if="stealQueue.length" class="detail__queue">
                <h3 class="detail__queue-title detail__queue-title--steal">Steal Queue</h3>
                <div v-for="(c, i) in stealQueue" :key="c.id" class="detail__queue-item">
                  <span class="detail__queue-pos">#{{ i + 1 }}</span>
                  <span class="detail__queue-name">{{ c.user_name ?? `Customer #${c.user_id}` }}</span>
                  <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
                </div>
              </div>
            </div>
          </template>

          <!-- ── Claim history ────────────────────────────────────────── -->
          <div v-if="claims.length > 0" class="detail__history">
            <button
              class="detail__history-toggle"
              aria-controls="claim-history"
              aria-expanded="false"
              @click="toggleHistory"
            >
              <span>Claim History ({{ claims.length }})</span>
              <svg width="10" height="6" viewBox="0 0 10 6" fill="currentColor" aria-hidden="true">
                <path d="M0 0l5 6 5-6z"/>
              </svg>
            </button>
            <div id="claim-history" class="detail__history-list" hidden>
              <div v-for="c in claims" :key="c.id" class="detail__history-item">
                <span class="detail__history-user">{{ c.user_name ?? `Customer #${c.user_id}` }}</span>
                <span :class="['badge', `badge--${c.type}`]">{{ c.type.toUpperCase() }}</span>
                <span :class="['badge', `badge--${c.status}`]">{{ c.status }}</span>
                <span class="detail__history-date">{{ formatDate(c.created_at) }}</span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Page wrapper ──────────────────────────────────────────────────────────── */
.detail-page {
  min-height: 100vh;
  background: var(--color-balsamico);
  padding-top: calc(var(--nav-height) + 2rem);
  padding-bottom: 6rem;
}

/* ── Back link ─────────────────────────────────────────────────────────────── */
.detail-back {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  margin-bottom: 2.5rem;
  transition: color var(--transition-fast);
}
.detail-back:hover { color: var(--color-spice-market); }
.detail-back:focus-visible { outline: 2px solid var(--color-spice-market); outline-offset: 2px; }

/* ── Two-column layout ─────────────────────────────────────────────────────── */
.detail {
  display: grid;
  grid-template-columns: 1fr 480px;
  gap: 4rem;
  align-items: start;
}
@media (max-width: 1024px) {
  .detail { grid-template-columns: 1fr 400px; gap: 2.5rem; }
}
@media (max-width: 768px) {
  .detail { grid-template-columns: 1fr; gap: 2rem; }
}

/* ── Gallery ───────────────────────────────────────────────────────────────── */
.detail__gallery {
  display: flex;
  gap: 1rem;
  position: sticky;
  top: calc(var(--nav-height) + 1.5rem);
  align-self: start;
}

/* Thumbnails */
.detail__thumbs {
  display: flex;
  flex-direction: column;
  gap: .625rem;
  flex-shrink: 0;
}
.detail__thumb {
  width: 68px; height: 68px;
  border: 2px solid transparent;
  border-radius: var(--radius);
  overflow: hidden;
  background: var(--color-balsamico-lighter);
  cursor: pointer;
  padding: 0;
  transition: border-color var(--transition-fast);
  flex-shrink: 0;
}
.detail__thumb img { width: 100%; height: 100%; object-fit: cover; }
.detail__thumb:hover { border-color: rgba(254,243,238,.25); }
.detail__thumb--active {
  border-color: var(--color-spice-market);
  box-shadow: 0 0 0 1px var(--color-spice-market);
}
.detail__thumb:focus-visible { outline: 2px solid var(--color-spice-market); outline-offset: 2px; }

/* Main image */
.detail__main-img-wrap {
  position: relative;
  flex: 1;
  min-height: 560px;
  background: var(--color-balsamico-lighter);
  border: 1px solid var(--color-balsamico-border);
  overflow: hidden;
}
.detail__main-img-wrap--unavailable .detail__main-img { filter: brightness(.7) saturate(.5); }

.detail__main-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  aspect-ratio: 3/4;
}
.detail__main-img-placeholder {
  width: 100%;
  aspect-ratio: 3/4;
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(254,243,238,.12);
  font-size: .75rem;
  letter-spacing: .2em;
}

/* Status badge on image */
.detail__img-badge-wrap {
  position: absolute;
  top: 1rem; left: 1rem;
}
.detail__img-badge { font-size: .65rem; }

/* SOLD band */
.detail__sold-band {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.detail__sold-text {
  font-size: 3rem;
  letter-spacing: .25em;
  color: rgba(254,243,238,.25);
  border: 2px solid rgba(254,243,238,.1);
  padding: .5rem 2rem;
  transform: rotate(-15deg);
  white-space: nowrap;
}

/* ── Info panel ────────────────────────────────────────────────────────────── */
.detail__info {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.detail__top-meta {
  display: flex;
  align-items: center;
  gap: .875rem;
  flex-wrap: wrap;
}
.detail__brand {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
}

.detail__title {
  font-size: clamp(1.8rem, 4vw, 3rem);
  color: var(--color-spice-market);
  line-height: 1.05;
  letter-spacing: .02em;
}

.detail__category {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(254,243,238,.3);
  border-left: 2px solid var(--color-spice-market);
  padding-left: .625rem;
}

.detail__desc {
  font-size: .9rem;
  line-height: 1.75;
  color: var(--color-seashell-muted);
}

/* ── Metadata table ─────────────────────────────────────────────────────────── */
.detail__meta-table {
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  overflow: hidden;
}
.detail__meta-row {
  display: grid;
  grid-template-columns: 120px 1fr;
  align-items: center;
  padding: .75rem 1rem;
  border-bottom: 1px solid var(--color-balsamico-border);
  gap: 1rem;
}
.detail__meta-row:last-child { border-bottom: none; }
.detail__meta-label {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
}
.detail__meta-value {
  font-size: .9rem;
  font-weight: 500;
  color: var(--color-seashell-strong);
}

.detail__divider {
  border: none;
  border-top: 1px solid var(--color-balsamico-border);
  margin: .25rem 0;
}

/* ── Queues ────────────────────────────────────────────────────────────────── */
.detail__queues { display: flex; flex-direction: column; gap: 1rem; }
.detail__queue {}
.detail__queue-title {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  margin-bottom: .625rem;
}
.detail__queue-title--mine  { color: var(--color-spice-market); }
.detail__queue-title--steal { color: var(--color-steal); }
.detail__queue-item {
  display: flex;
  align-items: center;
  gap: .625rem;
  padding: .5rem 0;
  border-bottom: 1px solid var(--color-balsamico-border);
  font-size: .82rem;
}
.detail__queue-item:last-child { border-bottom: none; }
.detail__queue-pos  { color: var(--color-seashell-muted); width: 1.5rem; flex-shrink: 0; }
.detail__queue-name { flex: 1; color: var(--color-seashell-strong); }

/* ── Claim history ─────────────────────────────────────────────────────────── */
.detail__history-toggle {
  background: none;
  border: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  font-family: var(--font-body);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  cursor: pointer;
  padding: .75rem 0;
  border-top: 1px solid var(--color-balsamico-border);
  transition: color var(--transition-fast);
}
.detail__history-toggle:hover { color: var(--color-seashell); }
.detail__history-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }
.detail__history-toggle svg { transition: transform var(--transition-fast); }

.detail__history-list[hidden] { display: none; }
.detail__history-list { padding-top: .5rem; }
.detail__history-item {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
  padding: .5rem 0;
  border-bottom: 1px solid var(--color-balsamico-border);
  font-size: .78rem;
}
.detail__history-item:last-child { border-bottom: none; }
.detail__history-user { font-weight: 600; flex: 1; min-width: 80px; }
.detail__history-date { color: var(--color-seashell-muted); font-size: .72rem; margin-left: auto; }

.page-error {
  text-align: center;
  padding: 6rem 1rem;
  color: var(--color-seashell-muted);
}
</style>
