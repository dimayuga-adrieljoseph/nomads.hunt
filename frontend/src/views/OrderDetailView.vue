<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { orderService, type Order } from '@/services/order.service'
import { formatPeso, formatDate } from '@/utils/formatters'

const route  = useRoute()
const router = useRouter()
const order  = ref<Order | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    order.value = await orderService.get(Number(route.params.id))
  } catch {
    router.push('/my-orders')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-content">
    <div class="container" style="max-width:600px">
      <RouterLink to="/my-orders" class="back-link">← My Orders</RouterLink>

      <div v-if="loading" class="spinner" />

      <div v-else-if="order" class="card" style="padding:2rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
          <h1 style="font-size:1.25rem">Order #{{ order.order_number }}</h1>
          <span :class="['badge', order.payment_status === 'paid' ? 'badge--paid' : `badge--${order.payment_status}`]">
            {{ order.payment_status.toUpperCase() }}
          </span>
        </div>

        <div v-if="order.product" class="receipt__product" style="display:flex;gap:1rem;align-items:center;margin-bottom:1.5rem">
          <img v-if="order.product.image_url" :src="order.product.image_url" style="width:72px;height:72px;object-fit:cover;border-radius:var(--radius)" />
          <div>
            <div style="font-weight:600">{{ order.product.name }}</div>
            <span :class="['badge', `badge--${order.claim_type}`]" style="margin-top:.375rem">{{ order.claim_type.toUpperCase() }}</span>
          </div>
        </div>

        <hr class="divider" />

        <div style="display:flex;flex-direction:column;gap:.875rem">
          <div style="display:flex;justify-content:space-between"><span style="color:var(--color-muted)">Amount</span><strong style="font-size:1.25rem">{{ formatPeso(order.amount) }}</strong></div>
          <div style="display:flex;justify-content:space-between"><span style="color:var(--color-muted)">Status</span><span>{{ order.status }}</span></div>
          <div v-if="order.paid_at" style="display:flex;justify-content:space-between"><span style="color:var(--color-muted)">Paid at</span><span>{{ formatDate(order.paid_at) }}</span></div>
          <div style="display:flex;justify-content:space-between"><span style="color:var(--color-muted)">Created</span><span>{{ formatDate(order.created_at) }}</span></div>
        </div>

        <hr class="divider" />

        <div style="display:flex;gap:.75rem">
          <RouterLink
            v-if="order.payment_status === 'pending'"
            :to="`/orders/${order.id}/pay`"
            class="btn btn--primary btn--full"
          >Pay Now</RouterLink>
          <RouterLink
            v-if="order.payment_status === 'paid'"
            :to="`/receipt/${order.id}`"
            class="btn btn--ghost btn--full"
          >View Receipt</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.back-link { display:inline-block;margin-bottom:1.5rem;font-size:.875rem;color:var(--color-muted); }
.back-link:hover { color:var(--color-text); }
</style>
