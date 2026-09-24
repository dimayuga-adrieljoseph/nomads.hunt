<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import ProductCard from '@/components/ProductCard.vue'
import { productService, type Product, type FilterOptions } from '@/services/product.service'

// ── State ────────────────────────────────────────────────────────────────────
const products    = ref<Product[]>([])
const filterOpts  = ref<FilterOptions>({ categories: [], sizes: [], conditions: [], statuses: [] })
const loading     = ref(false)
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

// ── Multi-select filter arrays ───────────────────────────────────────────────
const filters = reactive({
  search:     '',
  categories: [] as string[],
  sizes:      [] as string[],
  conditions: [] as string[],
  statuses:   [] as string[],
})

// View mode: 'rack' | 'grid'
const viewMode = ref<'rack' | 'grid'>('rack')

// Sort
const sortBy = ref('newest')
const sortOptions = [
  { value: 'newest',     label: 'Newest Finds' },
  { value: 'oldest',     label: 'Oldest Finds' },
  { value: 'price_asc',  label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
]

// Open dropdown tracker (one at a time)
const openDropdown = ref<string | null>(null)

// Mobile filter drawer
const mobileFiltersOpen = ref(false)

// ── Filter open/close helpers ────────────────────────────────────────────────
function toggleDropdown(name: string) {
  openDropdown.value = openDropdown.value === name ? null : name
}
function closeAll() { openDropdown.value = null }

// Close on outside click
function onDocClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.filter-dropdown')) closeAll()
}
onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))

// ── Multi-select toggle helpers ───────────────────────────────────────────────
function toggleArr(arr: string[], val: string) {
  const idx = arr.indexOf(val)
  idx === -1 ? arr.push(val) : arr.splice(idx, 1)
}

// ── Computed: active filter counts ───────────────────────────────────────────
const activeCats   = computed(() => filters.categories.length)
const activeSizes  = computed(() => filters.sizes.length)
const activeConds  = computed(() => filters.conditions.length)
const activeStats  = computed(() => filters.statuses.length)
const anyActive    = computed(() =>
  !!filters.search || activeCats.value || activeSizes.value || activeConds.value || activeStats.value
)

// ── Category label (for header) ──────────────────────────────────────────────
const categoryLabel = computed(() => {
  if (filters.categories.length === 1) return (filters.categories[0] ?? '').toUpperCase()
  if (filters.categories.length > 1)   return 'MIXED'
  return 'ALL PIECES'
})

// ── Load products ─────────────────────────────────────────────────────────────
let searchTimer: ReturnType<typeof setTimeout>

async function loadProducts(page = 1) {
  loading.value = true
  try {
    const res = await productService.list({
      search:    filters.search    || undefined,
      category:  (filters.categories.length ? filters.categories.join(',') : undefined),
      size:      (filters.sizes.length      ? filters.sizes.join(',')      : undefined),
      condition: (filters.conditions.length ? filters.conditions.join(',') : undefined),
      status:    (filters.statuses.length   ? filters.statuses.join(',')   : undefined),
      page,
    })
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
  filters.search     = ''
  filters.categories = []
  filters.sizes      = []
  filters.conditions = []
  filters.statuses   = []
  loadProducts(1)
}

onMounted(async () => {
  filterOpts.value = await productService.filterOptions()
  await loadProducts()
})

watch(
  [() => [...filters.categories], () => [...filters.sizes], () => [...filters.conditions], () => [...filters.statuses]],
  () => loadProducts(1)
)

// ── Rotations for rack view ───────────────────────────────────────────────────
const rots = [-2, 1.5, -1, 3, -2.5, 0.5, 2, -1.5, 3.5, -0.5]
function rot(i: number) { return rots[i % rots.length] }
</script>

<template>
  <div class="catalog-page">

    <!-- ── Catalog header ──────────────────────────────────────────────────── -->
    <div class="catalog-header">
      <div class="container catalog-header__inner">
        <div class="catalog-header__title-block">
          <h1 class="catalog-header__title display">{{ categoryLabel }}</h1>
          <p class="catalog-header__sub">
            Rack {{ currentPage }} of {{ lastPage }}
            <span class="catalog-header__count">&nbsp;·&nbsp; {{ total }} piece{{ total !== 1 ? 's' : '' }}</span>
          </p>
        </div>

        <div class="catalog-header__controls">
          <!-- View toggle -->
          <div class="view-toggle" role="group" aria-label="View mode">
            <button
              :class="['view-toggle__btn', { 'view-toggle__btn--active': viewMode === 'rack' }]"
              aria-label="Rack view"
              :aria-pressed="viewMode === 'rack'"
              @click="viewMode = 'rack'"
            >
              <!-- Vertical bars icon -->
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <rect x="1" y="2" width="3" height="12" rx="1"/>
                <rect x="6" y="2" width="3" height="12" rx="1"/>
                <rect x="11" y="2" width="3" height="12" rx="1"/>
              </svg>
            </button>
            <button
              :class="['view-toggle__btn', { 'view-toggle__btn--active': viewMode === 'grid' }]"
              aria-label="Grid view"
              :aria-pressed="viewMode === 'grid'"
              @click="viewMode = 'grid'"
            >
              <!-- Grid icon -->
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <rect x="1" y="1" width="6" height="6" rx="1"/>
                <rect x="9" y="1" width="6" height="6" rx="1"/>
                <rect x="1" y="9" width="6" height="6" rx="1"/>
                <rect x="9" y="9" width="6" height="6" rx="1"/>
              </svg>
            </button>
          </div>

          <!-- Sort -->
          <div class="sort-control filter-dropdown">
            <button
              class="sort-control__btn"
              :class="{ 'sort-control__btn--open': openDropdown === 'sort' }"
              aria-label="Sort products"
              :aria-expanded="openDropdown === 'sort'"
              @click.stop="toggleDropdown('sort')"
            >
              <span class="sort-control__label">Sort by</span>
              <span class="sort-control__value">{{ sortOptions.find(s => s.value === sortBy)?.label }}</span>
              <svg width="10" height="6" viewBox="0 0 10 6" fill="currentColor" aria-hidden="true" class="sort-control__chevron">
                <path d="M0 0l5 6 5-6z"/>
              </svg>
            </button>
            <ul v-if="openDropdown === 'sort'" class="filter-dropdown__panel sort-panel" role="listbox">
              <li
                v-for="opt in sortOptions"
                :key="opt.value"
                role="option"
                :aria-selected="sortBy === opt.value"
                :class="['sort-panel__item', { 'sort-panel__item--active': sortBy === opt.value }]"
                @click.stop="sortBy = opt.value; closeAll()"
              >{{ opt.label }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Filter bar ───────────────────────────────────────────────────────── -->
    <div class="filter-bar" role="search" aria-label="Product filters">
      <div class="container filter-bar__inner">

        <!-- Search -->
        <div class="filter-search filter-dropdown">
          <label for="cat-search" class="sr-only">Search products</label>
          <svg class="filter-search__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input
            id="cat-search"
            v-model="filters.search"
            type="search"
            class="filter-search__input"
            placeholder="Search pieces…"
            @input="onSearchInput"
          />
        </div>

        <!-- Category -->
        <div class="filter-dropdown" :class="{ 'filter-dropdown--active': activeCats > 0 }">
          <button
            :class="['filter-btn', { 'filter-btn--active': activeCats > 0 }]"
            :aria-expanded="openDropdown === 'category'"
            aria-haspopup="listbox"
            @click.stop="toggleDropdown('category')"
          >
            Category
            <span v-if="activeCats > 0" class="filter-btn__count">{{ activeCats }}</span>
            <svg width="8" height="5" viewBox="0 0 8 5" fill="currentColor" aria-hidden="true" class="filter-btn__caret">
              <path d="M0 0l4 5 4-5z"/>
            </svg>
          </button>
          <ul v-if="openDropdown === 'category'" class="filter-dropdown__panel" role="listbox" aria-multiselectable="true" aria-label="Category filter">
            <li
              v-for="c in filterOpts.categories"
              :key="c"
              role="option"
              :aria-selected="filters.categories.includes(c)"
              :class="['filter-option', { 'filter-option--selected': filters.categories.includes(c) }]"
              @click.stop="toggleArr(filters.categories, c)"
            >
              <span class="filter-option__check" aria-hidden="true">{{ filters.categories.includes(c) ? '✓' : '' }}</span>
              {{ c }}
            </li>
            <li v-if="filterOpts.categories.length === 0" class="filter-option filter-option--empty">No options</li>
          </ul>
        </div>

        <!-- Size -->
        <div class="filter-dropdown" :class="{ 'filter-dropdown--active': activeSizes > 0 }">
          <button
            :class="['filter-btn', { 'filter-btn--active': activeSizes > 0 }]"
            :aria-expanded="openDropdown === 'size'"
            aria-haspopup="listbox"
            @click.stop="toggleDropdown('size')"
          >
            Size
            <span v-if="activeSizes > 0" class="filter-btn__count">{{ activeSizes }}</span>
            <svg width="8" height="5" viewBox="0 0 8 5" fill="currentColor" aria-hidden="true" class="filter-btn__caret">
              <path d="M0 0l4 5 4-5z"/>
            </svg>
          </button>
          <ul v-if="openDropdown === 'size'" class="filter-dropdown__panel" role="listbox" aria-multiselectable="true" aria-label="Size filter">
            <li
              v-for="s in filterOpts.sizes"
              :key="s"
              role="option"
              :aria-selected="filters.sizes.includes(s)"
              :class="['filter-option', { 'filter-option--selected': filters.sizes.includes(s) }]"
              @click.stop="toggleArr(filters.sizes, s)"
            >
              <span class="filter-option__check" aria-hidden="true">{{ filters.sizes.includes(s) ? '✓' : '' }}</span>
              {{ s }}
            </li>
          </ul>
        </div>

        <!-- Condition -->
        <div class="filter-dropdown" :class="{ 'filter-dropdown--active': activeConds > 0 }">
          <button
            :class="['filter-btn', { 'filter-btn--active': activeConds > 0 }]"
            :aria-expanded="openDropdown === 'condition'"
            aria-haspopup="listbox"
            @click.stop="toggleDropdown('condition')"
          >
            Condition
            <span v-if="activeConds > 0" class="filter-btn__count">{{ activeConds }}</span>
            <svg width="8" height="5" viewBox="0 0 8 5" fill="currentColor" aria-hidden="true" class="filter-btn__caret">
              <path d="M0 0l4 5 4-5z"/>
            </svg>
          </button>
          <ul v-if="openDropdown === 'condition'" class="filter-dropdown__panel" role="listbox" aria-multiselectable="true" aria-label="Condition filter">
            <li
              v-for="(label, val) in { excellent: 'Excellent', good: 'Good', fair: 'Fair', poor: 'Poor' }"
              :key="val"
              role="option"
              :aria-selected="filters.conditions.includes(val)"
              :class="['filter-option', { 'filter-option--selected': filters.conditions.includes(val) }]"
              @click.stop="toggleArr(filters.conditions, val)"
            >
              <span class="filter-option__check" aria-hidden="true">{{ filters.conditions.includes(val) ? '✓' : '' }}</span>
              {{ label }}
            </li>
          </ul>
        </div>

        <!-- Status -->
        <div class="filter-dropdown" :class="{ 'filter-dropdown--active': activeStats > 0 }">
          <button
            :class="['filter-btn', { 'filter-btn--active': activeStats > 0 }]"
            :aria-expanded="openDropdown === 'status'"
            aria-haspopup="listbox"
            @click.stop="toggleDropdown('status')"
          >
            Status
            <span v-if="activeStats > 0" class="filter-btn__count">{{ activeStats }}</span>
            <svg width="8" height="5" viewBox="0 0 8 5" fill="currentColor" aria-hidden="true" class="filter-btn__caret">
              <path d="M0 0l4 5 4-5z"/>
            </svg>
          </button>
          <ul v-if="openDropdown === 'status'" class="filter-dropdown__panel" role="listbox" aria-multiselectable="true" aria-label="Status filter">
            <li
              v-for="(label, val) in { available: 'Available', mine_pending: 'Mine Active', steal_pending: 'Steal Active', grab_pending: 'Grab Payment', sold: 'Sold' }"
              :key="val"
              role="option"
              :aria-selected="filters.statuses.includes(val)"
              :class="['filter-option', { 'filter-option--selected': filters.statuses.includes(val) }]"
              @click.stop="toggleArr(filters.statuses, val)"
            >
              <span class="filter-option__check" aria-hidden="true">{{ filters.statuses.includes(val) ? '✓' : '' }}</span>
              {{ label }}
            </li>
          </ul>
        </div>

        <!-- Reset -->
        <button
          v-if="anyActive"
          class="filter-reset"
          aria-label="Clear all filters"
          @click="resetFilters"
        >
          Clear ✕
        </button>

        <!-- Mobile filter trigger -->
        <button class="filter-mobile-trigger" aria-label="Open filters" @click="mobileFiltersOpen = true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
          </svg>
          Filters
          <span v-if="anyActive" class="filter-mobile-trigger__dot" aria-hidden="true" />
        </button>
      </div>
    </div>

    <!-- ── Mobile filter drawer ─────────────────────────────────────────────── -->
    <div
      v-if="mobileFiltersOpen"
      class="mobile-filters"
      role="dialog"
      aria-modal="true"
      aria-label="Filters"
    >
      <div class="mobile-filters__header">
        <span class="mobile-filters__title">Filters</span>
        <button class="mobile-filters__close" aria-label="Close filters" @click="mobileFiltersOpen = false">✕</button>
      </div>
      <div class="mobile-filters__body">
        <!-- Search -->
        <div class="form-group">
          <label for="mob-search" class="form-label">Search</label>
          <input id="mob-search" v-model="filters.search" type="search" class="form-input" placeholder="Search pieces…" @input="onSearchInput" />
        </div>

        <!-- Category checkboxes -->
        <div class="form-group">
          <label class="form-label">Category</label>
          <div class="mobile-filters__checks">
            <label v-for="c in filterOpts.categories" :key="c" class="mobile-filter-check">
              <input type="checkbox" :value="c" :checked="filters.categories.includes(c)" @change="toggleArr(filters.categories, c)" />
              <span>{{ c }}</span>
            </label>
          </div>
        </div>

        <!-- Size checkboxes -->
        <div class="form-group">
          <label class="form-label">Size</label>
          <div class="mobile-filters__checks">
            <label v-for="s in filterOpts.sizes" :key="s" class="mobile-filter-check">
              <input type="checkbox" :value="s" :checked="filters.sizes.includes(s)" @change="toggleArr(filters.sizes, s)" />
              <span>{{ s }}</span>
            </label>
          </div>
        </div>

        <!-- Condition checkboxes -->
        <div class="form-group">
          <label class="form-label">Condition</label>
          <div class="mobile-filters__checks">
            <label v-for="(label, val) in { excellent: 'Excellent', good: 'Good', fair: 'Fair', poor: 'Poor' }" :key="val" class="mobile-filter-check">
              <input type="checkbox" :value="val" :checked="filters.conditions.includes(val)" @change="toggleArr(filters.conditions, val)" />
              <span>{{ label }}</span>
            </label>
          </div>
        </div>

        <!-- Status checkboxes -->
        <div class="form-group">
          <label class="form-label">Status</label>
          <div class="mobile-filters__checks">
            <label v-for="(label, val) in { available: 'Available', mine_pending: 'Mine Active', steal_pending: 'Steal Active', grab_pending: 'Grab Payment', sold: 'Sold' }" :key="val" class="mobile-filter-check">
              <input type="checkbox" :value="val" :checked="filters.statuses.includes(val)" @change="toggleArr(filters.statuses, val)" />
              <span>{{ label }}</span>
            </label>
          </div>
        </div>
      </div>
      <div class="mobile-filters__footer">
        <button class="btn btn--ghost btn--sm" @click="resetFilters">Reset</button>
        <button class="btn btn--primary btn--sm" @click="mobileFiltersOpen = false">Apply</button>
      </div>
    </div>
    <div v-if="mobileFiltersOpen" class="mobile-filters__backdrop" @click="mobileFiltersOpen = false" />

    <!-- ── Product area ──────────────────────────────────────────────────────── -->
    <div class="catalog-body container">
      <div v-if="loading" class="spinner" style="margin: 5rem auto" />

      <div v-else-if="products.length === 0" class="catalog-empty">
        <p>No pieces found matching your hunt.</p>
        <button class="btn btn--ghost btn--sm" @click="resetFilters">Clear filters</button>
      </div>

      <template v-else>
        <!-- RACK view -->
        <div v-if="viewMode === 'rack'" class="catalog-rack" role="list" aria-label="Product rack">
          <ProductCard
            v-for="(p, i) in products"
            :key="p.id"
            :product="p"
            view-mode="rack"
            :rotation="rot(i)"
            role="listitem"
          />
        </div>

        <!-- GRID view -->
        <div v-else class="catalog-grid" role="list" aria-label="Product grid">
          <ProductCard
            v-for="p in products"
            :key="p.id"
            :product="p"
            view-mode="grid"
            :rotation="0"
            role="listitem"
          />
        </div>
      </template>
    </div>

    <!-- ── Pagination ────────────────────────────────────────────────────────── -->
    <div v-if="lastPage > 1" class="container">
      <div class="pagination">
        <button :disabled="currentPage === 1" @click="loadProducts(currentPage - 1)">← Prev</button>
        <span class="pagination__info">{{ currentPage }} / {{ lastPage }}</span>
        <button :disabled="currentPage === lastPage" @click="loadProducts(currentPage + 1)">Next →</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Screen-reader only */
.sr-only { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }

/* ── Catalog page wrapper ──────────────────────────────────────────────────── */
.catalog-page {
  min-height: 100vh;
  background: var(--color-balsamico);
  padding-top: var(--nav-height);
  padding-bottom: 5rem;
}

/* ── Header ────────────────────────────────────────────────────────────────── */
.catalog-header {
  padding: 3rem 0 1.5rem;
  border-bottom: 1px solid var(--color-balsamico-border);
}
.catalog-header__inner {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
  flex-wrap: wrap;
}
.catalog-header__title {
  font-size: clamp(2.5rem, 6vw, 5rem);
  color: var(--color-spice-market);
  letter-spacing: .04em;
  line-height: 1;
}
.catalog-header__sub {
  font-size: .78rem;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
  margin-top: .5rem;
}
.catalog-header__count { color: rgba(254,243,238,.3); }

.catalog-header__controls {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-shrink: 0;
}

/* ── View toggle ───────────────────────────────────────────────────────────── */
.view-toggle {
  display: flex;
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  overflow: hidden;
}
.view-toggle__btn {
  background: transparent;
  border: none;
  color: var(--color-seashell-muted);
  padding: .5rem .75rem;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: all var(--transition-fast);
  border-right: 1px solid var(--color-balsamico-border);
}
.view-toggle__btn:last-child { border-right: none; }
.view-toggle__btn:hover { color: var(--color-seashell); background: var(--color-seashell-dim); }
.view-toggle__btn--active { color: var(--color-spice-market); background: var(--color-spice-dim); }

/* ── Sort control ──────────────────────────────────────────────────────────── */
.sort-control { position: relative; }
.sort-control__btn {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: transparent;
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  color: var(--color-seashell-muted);
  font-family: var(--font-body);
  font-size: .75rem;
  padding: .5rem .875rem;
  cursor: pointer;
  white-space: nowrap;
  transition: border-color var(--transition-fast);
}
.sort-control__btn:hover,
.sort-control__btn--open { border-color: var(--color-spice-market); color: var(--color-spice-market); }
.sort-control__label { font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; color: var(--color-seashell-muted); }
.sort-control__value { font-weight: 600; color: inherit; }
.sort-control__chevron { margin-left: .25rem; opacity: .5; }

.sort-panel { list-style: none; min-width: 180px; }
.sort-panel__item {
  padding: .6rem 1rem;
  font-size: .82rem;
  cursor: pointer;
  transition: background var(--transition-fast);
  color: var(--color-seashell-muted);
}
.sort-panel__item:hover { background: var(--color-seashell-dim); color: var(--color-seashell); }
.sort-panel__item--active { color: var(--color-spice-market); font-weight: 600; }

/* ── Filter bar ────────────────────────────────────────────────────────────── */
.filter-bar {
  padding: .875rem 0;
  border-bottom: 1px solid var(--color-balsamico-border);
  position: sticky;
  top: var(--nav-height);
  z-index: 100;
  background: var(--color-balsamico);
}
.filter-bar__inner {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
}

/* Search in filter bar */
.filter-search {
  display: flex;
  align-items: center;
  gap: .5rem;
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  padding: .4rem .75rem;
  background: transparent;
  min-width: 160px;
  transition: border-color var(--transition-fast);
}
.filter-search:focus-within { border-color: var(--color-spice-market); }
.filter-search__icon { color: var(--color-seashell-muted); flex-shrink: 0; }
.filter-search__input {
  background: transparent;
  border: none;
  outline: none;
  color: var(--color-seashell);
  font-family: var(--font-body);
  font-size: .82rem;
  width: 100%;
}
.filter-search__input::placeholder { color: rgba(254,243,238,.2); }

/* Filter buttons */
.filter-dropdown { position: relative; }

.filter-btn {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: transparent;
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  color: var(--color-seashell-muted);
  font-family: var(--font-body);
  font-size: .78rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .45rem .875rem;
  cursor: pointer;
  white-space: nowrap;
  transition: all var(--transition-fast);
}
.filter-btn:hover { border-color: rgba(254,243,238,.2); color: var(--color-seashell); }

/* ACTIVE state — solid light background */
.filter-btn--active {
  background: var(--color-seashell);
  border-color: var(--color-seashell);
  color: var(--color-balsamico);
}
.filter-btn--active:hover {
  background: rgba(254,243,238,.9);
  color: var(--color-balsamico);
}

.filter-btn__count {
  background: var(--color-spice-market);
  color: var(--color-seashell);
  font-size: .6rem;
  font-weight: 700;
  width: 16px; height: 16px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
/* When active, count uses balsamico */
.filter-btn--active .filter-btn__count {
  background: var(--color-balsamico);
  color: var(--color-seashell);
}
.filter-btn__caret { opacity: .4; }

/* Dropdown panel */
.filter-dropdown__panel {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 200;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  min-width: 160px;
  list-style: none;
  padding: .375rem 0;
  box-shadow: 0 8px 24px rgba(0,0,0,.4);
  max-height: 300px;
  overflow-y: auto;
}

.filter-option {
  display: flex;
  align-items: center;
  gap: .625rem;
  padding: .55rem 1rem;
  font-size: .82rem;
  cursor: pointer;
  color: var(--color-seashell-muted);
  transition: all var(--transition-fast);
}
.filter-option:hover { background: var(--color-seashell-dim); color: var(--color-seashell); }
.filter-option--selected { color: var(--color-seashell); }
.filter-option--empty { color: rgba(254,243,238,.2); cursor: default; font-style: italic; }
.filter-option__check {
  width: 14px; height: 14px;
  border: 1px solid var(--color-balsamico-border);
  border-radius: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .6rem;
  flex-shrink: 0;
  color: var(--color-spice-market);
  background: transparent;
  transition: all var(--transition-fast);
}
.filter-option--selected .filter-option__check {
  background: var(--color-spice-market);
  border-color: var(--color-spice-market);
  color: var(--color-seashell);
}

.filter-reset {
  background: transparent;
  border: none;
  color: var(--color-spice-market);
  font-family: var(--font-body);
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  cursor: pointer;
  padding: .45rem .5rem;
  transition: opacity var(--transition-fast);
}
.filter-reset:hover { opacity: .7; }

/* Mobile trigger (hidden on desktop) */
.filter-mobile-trigger {
  display: none;
  align-items: center;
  gap: .5rem;
  background: transparent;
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
  color: var(--color-seashell-muted);
  font-family: var(--font-body);
  font-size: .78rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: .5rem .875rem;
  cursor: pointer;
  position: relative;
}
.filter-mobile-trigger__dot {
  position: absolute;
  top: 4px; right: 4px;
  width: 6px; height: 6px;
  background: var(--color-spice-market);
  border-radius: 50%;
}

/* ── Mobile filter drawer ──────────────────────────────────────────────────── */
.mobile-filters {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 300;
  background: var(--color-balsamico-light);
  border-top: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  max-height: 80vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.mobile-filters__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--color-balsamico-border);
  flex-shrink: 0;
}
.mobile-filters__title { font-size: .85rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.mobile-filters__close { background: none; border: none; color: var(--color-seashell-muted); font-size: 1rem; cursor: pointer; padding: .25rem; }
.mobile-filters__body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; flex: 1; }
.mobile-filters__footer { display: flex; gap: .75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--color-balsamico-border); flex-shrink: 0; }
.mobile-filters__checks { display: flex; flex-wrap: wrap; gap: .625rem; margin-top: .5rem; }
.mobile-filter-check { display: flex; align-items: center; gap: .375rem; font-size: .85rem; cursor: pointer; color: var(--color-seashell-muted); }
.mobile-filter-check input { accent-color: var(--color-spice-market); }
.mobile-filters__backdrop { position: fixed; inset: 0; z-index: 295; background: rgba(26,15,5,.6); }

/* ── Product body ──────────────────────────────────────────────────────────── */
.catalog-body { padding-top: 3rem; }

/* RACK layout */
.catalog-rack {
  display: flex;
  align-items: flex-end;
  gap: 0;
  min-height: 520px;
  overflow-x: auto;
  padding-bottom: 1rem;
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}
.catalog-rack::-webkit-scrollbar { height: 3px; }
.catalog-rack::-webkit-scrollbar-thumb { background: var(--color-balsamico-border); }

/* GRID layout */
.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1px;
}

/* ── Empty state ───────────────────────────────────────────────────────────── */
.catalog-empty {
  text-align: center;
  padding: 6rem 1rem;
  color: var(--color-seashell-muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
}

/* Pagination info */
.pagination__info {
  font-size: .78rem;
  color: var(--color-seashell-muted);
  letter-spacing: .08em;
  text-transform: uppercase;
}

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  /* Hide desktop filter buttons, show mobile trigger */
  .filter-bar__inner > .filter-dropdown,
  .filter-bar__inner > .filter-search,
  .filter-bar__inner > .filter-reset {
    display: none;
  }
  .filter-mobile-trigger { display: flex; }
  .catalog-header__controls .sort-control { display: none; }

  .catalog-rack {
    min-height: 340px;
  }
  .catalog-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
