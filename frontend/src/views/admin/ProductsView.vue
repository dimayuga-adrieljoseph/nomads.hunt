<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { productService } from '@/services/product.service'
import { formatPeso, productStatusLabel, productStatusClass, conditionLabel, formatDate } from '@/utils/formatters'

interface AdminProduct {
  id: number
  name: string
  brand: string | null
  category: string | null
  size: string | null
  condition: string
  mine_price: number
  steal_price: number
  grab_price: number
  status: string
  image_url: string | null
  created_at: string
}

const products    = ref<AdminProduct[]>([])
const loading     = ref(true)
const search      = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

let searchTimer: ReturnType<typeof setTimeout>

async function load(page = 1) {
  loading.value = true
  try {
    const res = await productService.adminList({
      search:  search.value || undefined,
      status:  statusFilter.value || undefined,
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
  searchTimer = setTimeout(() => load(1), 400)
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <h1 class="admin-page-title">Products</h1>
        <p class="admin-page-sub">{{ total }} total</p>
      </div>
      <RouterLink to="/admin/products/new" class="btn btn--primary btn--sm">+ New Product</RouterLink>
    </div>

    <!-- Filters -->
    <div class="toolbar card">
      <input
        v-model="search"
        type="search"
        class="form-input toolbar__search"
        placeholder="Search name, brand, category…"
        @input="onSearchInput"
      />
      <select v-model="statusFilter" class="form-select toolbar__select" @change="load(1)">
        <option value="">All Statuses</option>
        <option value="available">Available</option>
        <option value="mine_pending">Mine Active</option>
        <option value="steal_pending">Steal Active</option>
        <option value="grab_pending">Grab Payment</option>
        <option value="sold">Sold</option>
      </select>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="card table-wrap">
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
                <img
                  v-if="p.image_url"
                  :src="p.image_url"
                  class="product-cell__img"
                  :alt="p.name"
                />
                <div v-else class="product-cell__img-placeholder" />
                <span class="product-cell__name">{{ p.name }}</span>
              </div>
            </td>
            <td>{{ p.brand ?? '—' }}</td>
            <td>{{ p.size ?? '—' }}</td>
            <td>{{ conditionLabel(p.condition) }}</td>
            <td>{{ formatPeso(p.mine_price) }}</td>
            <td>{{ formatPeso(p.steal_price) }}</td>
            <td>{{ formatPeso(p.grab_price) }}</td>
            <td>
              <span :class="['badge', productStatusClass(p.status)]">
                {{ productStatusLabel(p.status) }}
              </span>
            </td>
            <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">{{ formatDate(p.created_at) }}</td>
            <td>
              <div class="row-actions">
                <RouterLink :to="`/admin/products/${p.id}/edit`" class="btn btn--ghost btn--sm">Edit</RouterLink>
                <RouterLink :to="`/admin/products/${p.id}/claims`" class="btn btn--ghost btn--sm">Claims</RouterLink>
              </div>
            </td>
          </tr>
          <tr v-if="products.length === 0">
            <td colspan="10" style="text-align:center;color:var(--color-muted);padding:2rem">No products found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
      <span style="font-size:.85rem;color:var(--color-muted)">{{ currentPage }} / {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
    </div>
  </div>
</template>

<style scoped>
.admin-page-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem; }
.admin-page-title  { font-size:1.5rem;font-weight:700; }
.admin-page-sub    { font-size:.8rem;color:var(--color-muted);margin-top:.2rem; }

.toolbar {
  display: flex;
  gap: .75rem;
  align-items: center;
  padding: .875rem 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.toolbar__search { flex:1;min-width:200px; }
.toolbar__select { width:auto;min-width:150px; }

.product-cell { display:flex;align-items:center;gap:.625rem;min-width:180px; }
.product-cell__img { width:36px;height:36px;object-fit:cover;border-radius:4px;flex-shrink:0; }
.product-cell__img-placeholder { width:36px;height:36px;background:var(--color-surface-2);border-radius:4px;flex-shrink:0; }
.product-cell__name { font-weight:500;font-size:.875rem; }

.row-actions { display:flex;gap:.375rem; }
</style>
