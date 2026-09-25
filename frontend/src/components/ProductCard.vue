<script setup lang="ts">
import { RouterLink } from 'vue-router'
import ProductLikeButton from '@/components/ProductLikeButton.vue'
import type { Product } from '@/services/product.service'
import { formatPeso, productStatusLabel, productStatusClass, conditionLabel } from '@/utils/formatters'

const props = defineProps<{
  product: Product
  viewMode?: 'rack' | 'grid'
  rotation?: number
}>()

const mode = props.viewMode ?? 'rack'
const deg  = props.rotation ?? 0
</script>

<template>
  <!-- ── RACK ITEM ──────────────────────────────────────────────────────── -->
  <article
    v-if="mode === 'rack'"
    class="rack-item"
    :class="{
      'rack-item--sold':      product.status === 'sold',
      'rack-item--claimed':   product.status === 'mine_pending' || product.status === 'steal_pending',
    }"
    :style="`--item-rot: ${deg}deg`"
  >
    <RouterLink
      :to="`/products/${product.id}`"
      class="product-card__link"
      :aria-label="`View ${product.name}${product.brand ? ' by ' + product.brand : ''} — ${formatPeso(product.mine_price)}`"
    >
      <span class="product-card__sr-only">View product</span>
    </RouterLink>
    <ProductLikeButton :product="product" />
    <!-- Status dot -->
    <span
      :class="['rack-item__dot', productStatusClass(product.status)]"
      :title="productStatusLabel(product.status)"
      aria-hidden="true"
    />

    <!-- Image block -->
    <div class="rack-item__img-wrap">
      <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.name"
        class="rack-item__img"
        loading="lazy"
      />
      <div v-else class="rack-item__img-placeholder" aria-hidden="true">
        <span>NO IMAGE</span>
      </div>

      <!-- Overlay gradient -->
      <div class="rack-item__overlay" aria-hidden="true" />

      <!-- SOLD overlay -->
      <div v-if="product.status === 'sold'" class="rack-item__sold-band" aria-label="Sold">
        <span class="rack-item__sold-text">SOLD</span>
      </div>
    </div>

    <!-- Info panel -->
    <div class="rack-item__info">
      <!-- Vertical product name label -->
      <div class="rack-item__name-wrap" aria-hidden="true">
        <span class="rack-item__brand">{{ product.brand ?? '' }}</span>
        <span class="rack-item__name">{{ product.name }}</span>
      </div>

      <!-- Price footer -->
      <div class="rack-item__footer">
        <span class="rack-item__price">{{ formatPeso(product.mine_price) }}</span>
        <span v-if="product.size" class="rack-item__size">{{ product.size }}</span>
      </div>
    </div>

    <!-- Hover reveal -->
    <div class="rack-item__hover-panel" aria-hidden="true">
      <span class="rack-item__hover-status" :class="productStatusClass(product.status)">
        {{ productStatusLabel(product.status) }}
      </span>
      <span class="rack-item__hover-mine">MINE {{ formatPeso(product.mine_price) }}</span>
      <span class="rack-item__hover-steal">STEAL {{ formatPeso(product.steal_price) }}</span>
      <span class="rack-item__hover-grab">GRAB {{ formatPeso(product.grab_price) }}</span>
    </div>
  </article>

  <!-- ── GRID CARD ──────────────────────────────────────────────────────── -->
  <article v-else class="grid-card" :class="{ 'grid-card--sold': product.status === 'sold' }">
    <RouterLink
      :to="`/products/${product.id}`"
      class="product-card__link"
      :aria-label="`View ${product.name}${product.brand ? ' by ' + product.brand : ''} — ${formatPeso(product.mine_price)}`"
    >
      <span class="product-card__sr-only">View product</span>
    </RouterLink>
    <ProductLikeButton :product="product" />
    <!-- Image -->
    <div class="grid-card__img-wrap">
      <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.name"
        class="grid-card__img"
        loading="lazy"
      />
      <div v-else class="grid-card__img-placeholder">NO IMAGE</div>

      <span :class="['badge', productStatusClass(product.status), 'grid-card__badge']">
        {{ productStatusLabel(product.status) }}
      </span>
    </div>

    <!-- Body -->
    <div class="grid-card__body">
      <div class="grid-card__meta">
        <span class="grid-card__brand">{{ product.brand ?? '' }}</span>
        <span class="grid-card__condition">{{ conditionLabel(product.condition) }}</span>
      </div>
      <h3 class="grid-card__name">{{ product.name }}</h3>
      <div v-if="product.size" class="grid-card__size">Size {{ product.size }}</div>

      <div class="grid-card__prices">
        <div class="grid-card__price-row grid-card__price-row--mine">
          <span class="grid-card__price-label">MINE</span>
          <span class="grid-card__price-value">{{ formatPeso(product.mine_price) }}</span>
        </div>
        <div class="grid-card__price-row grid-card__price-row--steal">
          <span class="grid-card__price-label">STEAL</span>
          <span class="grid-card__price-value">{{ formatPeso(product.steal_price) }}</span>
        </div>
        <div class="grid-card__price-row grid-card__price-row--grab">
          <span class="grid-card__price-label">GRAB</span>
          <span class="grid-card__price-value">{{ formatPeso(product.grab_price) }}</span>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.product-card__link {
  position: absolute;
  inset: 0;
  z-index: 10;
  border-radius: inherit;
}
.product-card__link:focus-visible {
  outline: 2px solid var(--color-spice-market);
  outline-offset: 2px;
}
.product-card__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* ═══════════════════════════════════════════════════════════════
   RACK ITEM
   ═══════════════════════════════════════════════════════════════ */
.rack-item {
  position: relative;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  width: 200px;
  cursor: pointer;
  text-decoration: none;
  transform: rotate(var(--item-rot, 0deg));
  transform-origin: bottom center;
  transition: transform var(--transition-normal), z-index 0s;
  margin-right: -20px;
  z-index: 1;
}

.rack-item:hover {
  transform: rotate(0deg) translateY(-16px) scale(1.02);
  z-index: 20;
}

/* Status dot */
.rack-item__dot {
  position: absolute;
  top: .75rem;
  left: .75rem;
  z-index: 5;
  width: 10px; height: 10px;
  border-radius: 50%;
  background: rgba(254,243,238,.2);
  border: 1px solid rgba(254,243,238,.15);
  transition: all var(--transition-fast);
}
.rack-item:hover .rack-item__dot { transform: scale(1.2); }

/* Dot colors by status */
.rack-item__dot.badge--mine,
.rack-item__dot.badge--active    { background: var(--color-spice-market); border-color: var(--color-spice-market); box-shadow: 0 0 6px var(--color-spice-market); }
.rack-item__dot.badge--steal     { background: var(--color-steal); border-color: var(--color-steal); }
.rack-item__dot.badge--grab      { background: var(--color-grab); border-color: var(--color-grab); }
.rack-item__dot.badge--available { background: rgba(254,243,238,.5); }
.rack-item__dot.badge--sold      { background: transparent; }

/* Image */
.rack-item__img-wrap {
  position: relative;
  width: 100%;
  height: 480px;
  overflow: hidden;
  border: 1px solid var(--color-balsamico-border);
  background: var(--color-balsamico-lighter);
}

.rack-item__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--transition-normal);
}
.rack-item:hover .rack-item__img { transform: scale(1.05); }

.rack-item--sold .rack-item__img { filter: brightness(.65) saturate(.5); }

.rack-item__img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(254,243,238,.12);
  font-size: .65rem;
  letter-spacing: .2em;
}

.rack-item__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgba(26,15,5,.9)   0%,
    rgba(26,15,5,.4)   35%,
    rgba(26,15,5,.1)   65%,
    transparent        100%
  );
  transition: opacity var(--transition-normal);
}
.rack-item:hover .rack-item__overlay { opacity: .55; }

/* SOLD diagonal band */
.rack-item__sold-band {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-35deg);
  pointer-events: none;
  z-index: 4;
}
.rack-item__sold-text {
  font-family: var(--font-display);
  font-size: 1.4rem;
  letter-spacing: .2em;
  color: rgba(254,243,238,.3);
  white-space: nowrap;
  border: 1px solid rgba(254,243,238,.1);
  padding: .2rem 1rem;
}

/* Info panel */
.rack-item__info {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  padding: 1rem .875rem .875rem;
  z-index: 3;
  display: flex;
  flex-direction: column;
  gap: .625rem;
}

.rack-item__name-wrap {
  display: flex;
  flex-direction: column;
  gap: .2rem;
  overflow: hidden;
}

.rack-item__brand {
  font-size: .6rem;
  font-weight: 700;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: rgba(254,243,238,.4);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Vertical name — key rack effect */
.rack-item__name {
  font-size: .82rem;
  font-weight: 700;
  color: var(--color-seashell);
  line-height: 1.25;
  /* Vertical writing for rack labels */
  writing-mode: vertical-lr;
  text-orientation: mixed;
  transform: rotate(180deg);
  height: 140px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color var(--transition-fast);
}
.rack-item:hover .rack-item__name { color: var(--color-seashell); }

.rack-item__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
}

.rack-item__price {
  font-size: .875rem;
  font-weight: 700;
  color: var(--color-spice-market);
  transition: color var(--transition-fast);
}
.rack-item:hover .rack-item__price { color: var(--color-seashell); }

.rack-item__size {
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(254,243,238,.35);
  background: rgba(254,243,238,.06);
  padding: .15rem .4rem;
  border: 1px solid rgba(254,243,238,.08);
}

/* Hover reveal panel (shown on hover) */
.rack-item__hover-panel {
  position: absolute;
  top: 3.5rem;
  right: .875rem;
  z-index: 5;
  display: flex;
  flex-direction: column;
  gap: .3rem;
  align-items: flex-end;
  opacity: 0;
  transform: translateX(6px);
  transition: opacity var(--transition-normal), transform var(--transition-normal);
  pointer-events: none;
}
.rack-item:hover .rack-item__hover-panel {
  opacity: 1;
  transform: translateX(0);
}

.rack-item__hover-status {
  font-size: .6rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: .15rem .5rem;
  border-radius: 0;
}
.rack-item__hover-mine,
.rack-item__hover-steal,
.rack-item__hover-grab {
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .06em;
  color: rgba(254,243,238,.6);
  background: rgba(26,15,5,.75);
  padding: .15rem .45rem;
  white-space: nowrap;
}
.rack-item__hover-mine  { color: var(--color-spice-market); }
.rack-item__hover-steal { color: var(--color-steal); }
.rack-item__hover-grab  { color: var(--color-grab); }

/* ═══════════════════════════════════════════════════════════════
   GRID CARD
   ═══════════════════════════════════════════════════════════════ */
.grid-card {
  position: relative;
  display: flex;
  flex-direction: column;
  background: var(--color-balsamico);
  border: 1px solid var(--color-balsamico-border);
  text-decoration: none;
  transition: border-color var(--transition-fast), background var(--transition-fast);
  overflow: hidden;
}
.grid-card:hover { border-color: var(--color-spice-border); background: var(--color-balsamico-light); }

.grid-card--sold { opacity: .7; }

.grid-card__img-wrap {
  position: relative;
  aspect-ratio: 3/4;
  background: var(--color-balsamico-lighter);
  overflow: hidden;
}
.grid-card__img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform var(--transition-normal);
}
.grid-card:hover .grid-card__img { transform: scale(1.04); }
.grid-card--sold .grid-card__img { filter: brightness(.65) saturate(.4); }

.grid-card__img-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: rgba(254,243,238,.12);
  font-size: .65rem; letter-spacing: .18em;
}

.grid-card__badge {
  position: absolute;
  top: .75rem; left: .75rem;
}

.grid-card__body {
  padding: 1.1rem 1rem;
  display: flex;
  flex-direction: column;
  gap: .5rem;
  flex: 1;
}

.grid-card__meta {
  display: flex;
  justify-content: space-between;
  font-size: .7rem;
  letter-spacing: .06em;
}
.grid-card__brand    { color: var(--color-seashell-muted); font-weight: 600; text-transform: uppercase; }
.grid-card__condition{ color: rgba(254,243,238,.3); }

.grid-card__name {
  font-size: .95rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--color-seashell);
}

.grid-card__size {
  font-size: .72rem;
  color: rgba(254,243,238,.35);
  letter-spacing: .08em;
  text-transform: uppercase;
}

.grid-card__prices { display: flex; flex-direction: column; gap: .2rem; margin-top: .375rem; }
.grid-card__price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .25rem .5rem;
  font-size: .78rem;
  border-left: 2px solid transparent;
}
.grid-card__price-row--mine  {
  border-left-color: var(--color-spice-market);
  background: rgba(186,68,29,.06);
}
.grid-card__price-row--steal {
  border-left-color: var(--color-steal);
  background: rgba(212,105,26,.06);
}
.grid-card__price-row--grab  {
  border-left-color: var(--color-grab);
  background: rgba(232,160,80,.06);
}
.grid-card__price-label { font-weight: 700; letter-spacing: .08em; font-size: .65rem; }
.grid-card__price-row--mine  .grid-card__price-label { color: var(--color-spice-market); }
.grid-card__price-row--steal .grid-card__price-label { color: var(--color-steal); }
.grid-card__price-row--grab  .grid-card__price-label { color: var(--color-grab); }
.grid-card__price-value { font-weight: 600; color: var(--color-seashell-strong); }
</style>
