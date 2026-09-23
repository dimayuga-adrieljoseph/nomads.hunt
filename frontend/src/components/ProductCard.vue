<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Product } from '@/services/product.service'
import { formatPeso, productStatusLabel, productStatusClass, conditionLabel } from '@/utils/formatters'

defineProps<{ product: Product }>()
</script>

<template>
  <RouterLink :to="`/products/${product.id}`" class="product-card">
    <!-- Image -->
    <div class="product-card__img-wrap">
      <img
        v-if="product.image_url"
        :src="product.image_url"
        :alt="product.name"
        class="product-card__img"
      />
      <div v-else class="product-card__img-placeholder">
        <span>No Image</span>
      </div>

      <span :class="['badge', productStatusClass(product.status), 'product-card__badge']">
        {{ productStatusLabel(product.status) }}
      </span>
    </div>

    <!-- Info -->
    <div class="product-card__body">
      <div class="product-card__meta">
        <span class="product-card__brand">{{ product.brand ?? 'Unknown' }}</span>
        <span class="product-card__condition">{{ conditionLabel(product.condition) }}</span>
      </div>

      <h3 class="product-card__name">{{ product.name }}</h3>

      <div v-if="product.size" class="product-card__size">Size: {{ product.size }}</div>

      <div class="product-card__prices">
        <div class="price-row price-row--mine">
          <span class="price-label">MINE</span>
          <span class="price-value">{{ formatPeso(product.mine_price) }}</span>
        </div>
        <div class="price-row price-row--steal">
          <span class="price-label">STEAL</span>
          <span class="price-value">{{ formatPeso(product.steal_price) }}</span>
        </div>
        <div class="price-row price-row--grab">
          <span class="price-label">GRAB</span>
          <span class="price-value">{{ formatPeso(product.grab_price) }}</span>
        </div>
      </div>
    </div>
  </RouterLink>
</template>

<style scoped>
.product-card {
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: border-color .2s, transform .2s;
}
.product-card:hover {
  border-color: #444;
  transform: translateY(-2px);
}

.product-card__img-wrap {
  position: relative;
  aspect-ratio: 4/3;
  background: var(--color-surface-2);
  overflow: hidden;
}
.product-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .3s;
}
.product-card:hover .product-card__img { transform: scale(1.04); }

.product-card__img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-muted);
  font-size: .8rem;
  letter-spacing: .06em;
}

.product-card__badge {
  position: absolute;
  top: .75rem;
  right: .75rem;
}

.product-card__body {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .5rem;
  flex: 1;
}

.product-card__meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: .75rem;
}
.product-card__brand   { color: var(--color-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
.product-card__condition { color: var(--color-muted); }

.product-card__name {
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.35;
}

.product-card__size {
  font-size: .8rem;
  color: var(--color-muted);
}

.product-card__prices {
  margin-top: .5rem;
  display: flex;
  flex-direction: column;
  gap: .25rem;
}

.price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: .8rem;
  padding: .2rem .375rem;
  border-radius: 4px;
}
.price-row--mine  { background: rgba(245,158,11,.08); }
.price-row--steal { background: rgba(239,68,68,.08); }
.price-row--grab  { background: rgba(34,197,94,.08); }

.price-label { font-weight: 700; letter-spacing: .05em; font-size: .7rem; }
.price-row--mine  .price-label  { color: var(--color-mine); }
.price-row--steal .price-label  { color: var(--color-steal); }
.price-row--grab  .price-label  { color: var(--color-grab); }
.price-value { font-weight: 600; }
</style>
