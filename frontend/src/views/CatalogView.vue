<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import ProductCard from '@/components/ProductCard.vue'
import { productService, type Product, type FilterOptions } from '@/services/product.service'

const products     = ref<Product[]>([])
const filterOpts   = ref<FilterOptions>({ categories: [], sizes: [], conditions: [], statuses: [] })
const loading      = ref(false)
const currentPage  = ref(1)
const lastPage     = ref(1)
const total        = ref(0)

const filters = reactive({
  search:    '',
  category:  '',
  size:      '',
  condition: '',
  status:    '',
})

let searchTimer: ReturnType<typeof setTimeout>

async function loadProducts(page = 1) {
  loading.value = true
  try {
    const res = await productService.list({ ...filters, page })
    products.value    = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
    total.value       = res.meta.total
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadProducts(1), 400)
}

function resetFilters() {
  filters.search    = ''
  filters.category  = ''
  filters.size      = ''
  filters.condition = ''
  filters.status    = ''
  loadProducts(1)
}

onMounted(async () => {
  filterOpts.value = await productService.filterOptions()
  await loadProducts()
})

watch([() => filters.category, () => filters.size, () => filters.condition, () => filters.status], () => {
  loadProducts(1)
})
</script>

<template>
  <div class="page-content">
    <div class="container">
      <!-- Header -->
      <div class="catalog-header">
        <div>
          <h1>Catalog</h1>
          <p class="catalog-sub">{{ total }} item{{ total !== 1 ? 's' : '' }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters card">
        <input
          v-model="filters.search"
          type="search"
          class="form-input filters__search"
          placeholder="Search by name, brand, category…"
          @input="onSearchInput"
        />

        <div class="filters__selects">
          <select v-model="filters.category" class="form-select">
            <option value="">All Categories</option>
            <option v-for="c in filterOpts.categories" :key="c" :value="c">{{ c }}</option>
          </select>

          <select v-model="filters.size" class="form-select">
            <option value="">All Sizes</option>
            <option v-for="s in filterOpts.sizes" :key="s" :value="s">{{ s }}</option>
          </select>

          <select v-model="filters.condition" class="form-select">
            <option value="">All Conditions</option>
            <option value="excellent">Excellent</option>
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="poor">Poor</option>
          </select>

          <select v-model="filters.status" class="form-select">
            <option value="">All Statuses</option>
            <option value="available">Available</option>
            <option value="mine_pending">Mine Active</option>
            <option value="steal_pending">Steal Active</option>
            <option value="grab_pending">Grab Payment</option>
            <option value="sold">Sold</option>
          </select>

          <button class="btn btn--ghost btn--sm" @click="resetFilters">Reset</button>
        </div>
      </div>

      <!-- Grid -->
      <div v-if="loading" class="spinner" />

      <div v-else-if="products.length === 0" class="catalog-empty">
        <p>No products found.</p>
        <button class="btn btn--ghost btn--sm" @click="resetFilters">Clear filters</button>
      </div>

      <div v-else class="catalog-grid">
        <ProductCard v-for="p in products" :key="p.id" :product="p" />
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="pagination">
        <button :disabled="currentPage === 1" @click="loadProducts(currentPage - 1)">← Prev</button>
        <span style="font-size:.85rem;color:var(--color-muted)">{{ currentPage }} / {{ lastPage }}</span>
        <button :disabled="currentPage === lastPage" @click="loadProducts(currentPage + 1)">Next →</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.catalog-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}
.catalog-sub { color: var(--color-muted); font-size: .875rem; margin-top: .25rem; }

.filters {
  padding: 1rem 1.25rem;
  margin-bottom: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: .875rem;
}
.filters__search { max-width: 100%; }
.filters__selects {
  display: flex;
  flex-wrap: wrap;
  gap: .625rem;
  align-items: center;
}
.filters__selects .form-select { width: auto; min-width: 140px; }

.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1.25rem;
}

.catalog-empty {
  text-align: center;
  padding: 4rem 1rem;
  color: var(--color-muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
</style>
