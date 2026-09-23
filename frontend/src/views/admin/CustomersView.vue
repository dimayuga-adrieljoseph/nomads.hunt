<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminService } from '@/services/admin.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface CustomerRow {
  id: number
  name: string
  email: string
  claims_count: number
  orders_count: number
  total_spent: number
  created_at: string
}

const customers   = ref<CustomerRow[]>([])
const loading     = ref(true)
const search      = ref('')
const currentPage = ref(1)
const lastPage    = ref(1)
const total       = ref(0)

let searchTimer: ReturnType<typeof setTimeout>

async function load(page = 1) {
  loading.value = true
  try {
    const res = await adminService.customers({ search: search.value || undefined, page })
    customers.value   = res.data
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
        <h1 class="admin-page-title">Customers</h1>
        <p class="admin-page-sub">{{ total }} registered</p>
      </div>
    </div>

    <div class="toolbar card">
      <input
        v-model="search"
        type="search"
        class="form-input toolbar__search"
        placeholder="Search name or email…"
        @input="onSearchInput"
      />
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="card table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Claims</th>
            <th>Orders</th>
            <th>Total Spent</th>
            <th>Joined</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in customers" :key="c.id">
            <td style="font-weight:600">{{ c.name }}</td>
            <td style="color:var(--color-muted);font-size:.875rem">{{ c.email }}</td>
            <td>{{ c.claims_count }}</td>
            <td>{{ c.orders_count }}</td>
            <td style="font-weight:600">{{ formatPeso(c.total_spent) }}</td>
            <td style="white-space:nowrap;color:var(--color-muted);font-size:.75rem">{{ formatDate(c.created_at) }}</td>
            <td>
              <RouterLink :to="`/admin/customers/${c.id}`" class="btn btn--ghost btn--sm">View</RouterLink>
            </td>
          </tr>
          <tr v-if="customers.length === 0">
            <td colspan="7" style="text-align:center;color:var(--color-muted);padding:2rem">No customers found.</td>
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
.toolbar { display:flex;gap:.75rem;padding:.875rem 1rem;margin-bottom:1.25rem; }
.toolbar__search { flex:1; }
</style>
