<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import ProductCard from '@/components/ProductCard.vue'
import { likeService } from '@/services/like.service'
import type { Product } from '@/services/product.service'
import { useLikeStore } from '@/stores/likes'

const products = ref<Product[]>([])
const loading = ref(true)
const loadError = ref('')
const likes = useLikeStore()

const visibleProducts = computed(() =>
  products.value.filter((product) => likes.isLiked(product)),
)

async function load() {
  loading.value = true
  loadError.value = ''

  try {
    products.value = await likeService.list()
    likes.sync(products.value)
  } catch {
    loadError.value = 'Failed to load your liked products. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-content liked-page">
    <div class="container">
      <header class="liked-header">
        <div>
          <p class="liked-header__eyebrow">/ Saved Finds</p>
          <h1 class="liked-header__title display">Liked Products</h1>
          <p class="liked-header__sub">Your saved finds.</p>
        </div>
        <RouterLink to="/catalog" class="btn btn--ghost btn--sm">Start Hunting</RouterLink>
      </header>

      <div v-if="loading" class="spinner" />

      <div v-else-if="loadError" class="liked-empty" role="alert">
        <p>{{ loadError }}</p>
        <button class="btn btn--ghost btn--sm" @click="load">Try Again</button>
      </div>

      <div v-else-if="visibleProducts.length === 0" class="liked-empty">
        <span class="liked-empty__mark" aria-hidden="true">♡</span>
        <p class="liked-empty__label">No Finds Yet</p>
        <h2 class="liked-empty__title display">Nothing saved—yet.</h2>
        <p class="liked-empty__copy">
          You haven't liked any pieces yet.<br />
          Start hunting and save the pieces you want to keep an eye on.
        </p>
        <RouterLink to="/catalog" class="btn btn--primary btn--sm">Start Hunting →</RouterLink>
      </div>

      <div v-else class="liked-grid" role="list" aria-label="Liked products">
        <ProductCard
          v-for="product in visibleProducts"
          :key="product.id"
          :product="product"
          view-mode="grid"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.liked-page { min-height: 100vh; }
.liked-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
  margin-bottom: 3rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid var(--color-balsamico-border);
  flex-wrap: wrap;
}
.liked-header__eyebrow {
  margin-bottom: .5rem;
  color: var(--color-spice-market);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .2em;
  text-transform: uppercase;
}
.liked-header__title {
  color: var(--color-seashell);
  font-size: clamp(2rem, 5vw, 3.5rem);
}
.liked-header__sub {
  margin-top: .625rem;
  color: var(--color-seashell-muted);
  font-size: .9rem;
}
.liked-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1px;
}
.liked-empty {
  min-height: 26rem;
  padding: 5rem 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  text-align: center;
  color: var(--color-seashell-muted);
}
.liked-empty__mark {
  color: var(--color-spice-market);
  font-family: var(--font-display);
  font-size: 3.5rem;
  line-height: 1;
}
.liked-empty__label {
  color: var(--color-spice-market);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .22em;
  text-transform: uppercase;
}
.liked-empty__title { color: var(--color-seashell); font-size: 1.5rem; }
.liked-empty__copy { max-width: 34rem; line-height: 1.8; }
</style>
