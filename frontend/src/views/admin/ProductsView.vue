<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { productService } from '@/services/product.service'
import { formatPeso, productStatusLabel, productStatusClass, conditionLabel, formatDate } from '@/utils/formatters'

interface AdminProduct {
  id: number; name: string; brand: string | null; category: string | null
  size: string | null; condition: string; mine_price: number; steal_price: number
  grab_price: number; status: string; image_url: string | null; created_at: string
}

const products     = ref<AdminProduct[]>([])
const loading      = ref(true)
const search       = ref('')
const statusFilter = ref('')
const currentPage  = ref(1)
const lastPage     = ref(1)
const total        = ref(0)

let searchTimer: ReturnType<typeof setTimeout>

async function load(page = 1) {
  loading.value = true
  try {
    const res = await productService.adminList({
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      page,
    })
    products.value    = res.data
    currentPage.value = res.meta.current_page
    lastPage.value    = res.meta.last_page
    total.value       = res.meta.total
  } finally { loading.value = false }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin</p>
        <h1 class="admin-page-title">Products <span class="admin-count">{{ total }}</span></h1>
      </div>
      <RouterLink to="/admin/products/new" class="btn btn--primary btn--sm">+ New Product</RouterLink>
    </div>

    <!-- Toolbar -->
    <div class="admin-toolbar">
      <input
        v-model="search" type="search"
        class="admin-toolbar__search form-input"
        placeholder="Search name, brand, category…"
        @input="onSearchInput"
      />
      <select v-model="statusFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Statuses</option>
        <option value="available">Available</option>
        <option value="mine_pending">Mine Active</option>
        <option value="steal_pending">Steal Active</option>
        <option value="grab_pending">Grab Payment</option>
        <option value="sold">Sold</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="table-wrap admin-table-card">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Brand</th>
            <th>Size</th>
            <th>Condition</th>
            <th>Mine</th>
            <th>Steal</th>
            <th>Grab</th>
            <th>Status</th>
            <th>Added</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id">
            <td>
              <div class="product-cell">
                <div class="product-cell__img-wrap">
                  <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="product-cell__img" />
                  <div v-else class="product-cell__img-placeholder" aria-hidden="true" />
                </div>
                <span class="product-cell__name">{{ p.name }}</span>
              </div>
            </td>
            <td class="td-muted">{{ p.brand ?? '—' }}</td>
            <td class="td-muted">{{ p.size ?? '—' }}</td>
            <td class="td-muted">{{ conditionLabel(p.condition) }}</td>
            <td class="td-price td-price--mine">{{ formatPeso(p.mine_price) }}</td>
            <td class="td-price td-price--steal">{{ formatPeso(p.steal_price) }}</td>
            <td class="td-price td-price--grab">{{ formatPeso(p.grab_price) }}</td>
            <td>
              <span :class="['badge', productStatusClass(p.status)]">{{ productStatusLabel(p.status) }}</span>
            </td>
            <td class="td-date">{{ formatDate(p.created_at) }}</td>
            <td>
              <div class="row-actions">
                <RouterLink :to="`/admin/products/${p.id}/edit`" class="btn btn--ghost btn--sm">Edit</RouterLink>
                <RouterLink :to="`/admin/products/${p.id}/claims`" class="btn btn--ghost btn--sm">Claims</RouterLink>
              </div>
            </td>
          </tr>
          <tr v-if="products.length === 0">
            <td colspan="10" class="empty-cell">No products found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
      <span class="pagination__info">{{ currentPage }} / {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
    </div>
  </div>
</template>

<style scoped>
.admin-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.75rem; gap: 1rem; }
.admin-eyebrow { font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .3rem; }
.admin-page-title { font-size: 1.5rem; font-weight: 700; color: var(--color-seashell); display: flex; align-items: baseline; gap: .75rem; }
.admin-count { font-size: .85rem; color: var(--color-seashell-muted); font-weight: 400; }

.admin-toolbar {
  display: flex; gap: .75rem; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap;
}
.admin-toolbar__search { flex: 1; min-width: 200px; }
.admin-toolbar__select { width: auto; min-width: 160px; }

.admin-table-card {
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  border-radius: var(--radius);
}

.product-cell { display: flex; align-items: center; gap: .75rem; min-width: 180px; }
.product-cell__img-wrap { width: 36px; height: 36px; flex-shrink: 0; overflow: hidden; background: var(--color-balsamico-lighter); border: 1px solid var(--color-balsamico-border); }
.product-cell__img { width: 100%; height: 100%; object-fit: cover; }
.product-cell__img-placeholder { width: 100%; height: 100%; }
.product-cell__name { font-weight: 500; font-size: .875rem; color: var(--color-seashell-strong); }

.td-muted { color: var(--color-seashell-muted); font-size: .82rem; }
.td-date  { color: rgba(254,243,238,.3); font-size: .72rem; white-space: nowrap; }
.td-price { font-size: .82rem; font-weight: 600; white-space: nowrap; }
.td-price--mine  { color: var(--color-spice-market); }
.td-price--steal { color: var(--color-steal); }
.td-price--grab  { color: var(--color-grab); }

.row-actions { display: flex; gap: .375rem; }
.empty-cell { text-align: center; color: var(--color-seashell-muted); padding: 3rem; }
.pagination__info { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .08em; text-transform: uppercase; }
</style>
