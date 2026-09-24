import api from './api'

export interface Order {
  id: number
  order_number: string
  amount: number
  claim_type: 'mine' | 'steal' | 'grab'
  payment_status: 'pending' | 'paid' | 'expired' | 'cancelled'
  status: 'pending' | 'completed' | 'cancelled'
  paid_at: string | null
  expires_at: string | null
  created_at: string
  product?: { id: number; name: string; image_url: string | null; status: string } | null
  claim?: { id: number; type: string; status: string; phase: 'claim' | 'payment' | null } | null
  user?: { id: number; name: string; email: string } | null
}

export const orderService = {
  async myOrders(page = 1) {
    const { data } = await api.get('/my-orders', { params: { page } })
    return data
  },

  async get(orderId: number) {
    const { data } = await api.get(`/orders/${orderId}`)
    return data.data as Order
  },

  async pay(orderId: number) {
    const { data } = await api.post(`/orders/${orderId}/pay`)
    return data as { message: string; data: Order }
  },

  // ── Admin ────────────────────────────────────────────────────────────────

  async adminList(params: Record<string, unknown> = {}) {
    const { data } = await api.get('/admin/orders', { params })
    return data
  },

  async adminGet(orderId: number) {
    const { data } = await api.get(`/admin/orders/${orderId}`)
    return data.data as Order
  },
}
