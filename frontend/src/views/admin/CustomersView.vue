<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { adminService } from '@/services/admin.service'
import { formatPeso, formatDate } from '@/utils/formatters'

interface CustomerRow {
  id: number; name: string; email: string
  claims_count: number; orders_count: number; total_spent: number; created_at: string
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
    customers.value = res.data; currentPage.value = res.meta.current_page
    lastPage.value = res.meta.last_page; total.value = res.meta.total
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
        <h1 class="admin-page-title">Customers <span class="admin-count">{{ total }}</span></h1>
      </div>
    </div>

    <div class="admin-toolbar">
      <input
        v-model="search" type="search"
        class="form-input admin-toolbar__search"
        placeholder="Search name or email…"
        @input="onSearchInput"
      />
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="table-wrap admin-table-card">
      <table>
        <thead>
          <tr>
            <th>Name</th><th>Email</th><th>Claims</th>
            <th>Orders</th><th>Total Spent</th><th>Joined</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in customers" :key="c.id">
            <td class="td-name">{{ c.name }}</td>
            <td class="td-muted">{{ c.email }}</td>
            <td class="td-num">{{ c.claims_count }}</td>
            <td class="td-num">{{ c.orders_count }}</td>
            <td class="td-price">{{ formatPeso(c.total_spent) }}</td>
            <td class="td-date">{{ formatDate(c.created_at) }}</td>
            <td>
              <RouterLink :to="`/admin/customers/${c.id}`" class="btn btn--ghost btn--sm">View</RouterLink>
            </td>
          </tr>
          <tr v-if="customers.length === 0"><td colspan="7" class="empty-cell">No customers found.</td></tr>
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
.admin-toolbar { display: flex; gap: .75rem; margin-bottom: 1.25rem; }
.admin-toolbar__search { flex: 1; max-width: 340px; }
.admin-table-card { background: var(--color-balsamico-light); border: 1px solid var(--color-balsamico-border); border-radius: var(--radius); }
.td-name  { font-weight: 600; color: var(--color-seashell); font-size: .875rem; }
.td-muted { color: var(--color-seashell-muted); font-size: .82rem; }
.td-date  { color: rgba(254,243,238,.3); font-size: .72rem; white-space: nowrap; }
.td-price { font-weight: 600; font-size: .875rem; }
.td-num   { font-size: .875rem; color: var(--color-seashell-strong); }
.empty-cell { text-align: center; color: var(--color-seashell-muted); padding: 3rem; }
.pagination__info { font-size: .78rem; color: var(--color-seashell-muted); letter-spacing: .08em; text-transform: uppercase; }
</style>
