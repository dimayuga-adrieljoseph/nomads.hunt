import api from './api'

export interface Product {
  id: number
  name: string
  description: string | null
  category: string | null
  brand: string | null
  size: string | null
  condition: 'excellent' | 'good' | 'fair' | 'poor'
  image_url: string | null
  mine_price: number
  steal_price: number
  grab_price: number
  status: 'available' | 'mine_pending' | 'steal_pending' | 'grab_pending' | 'sold'
  is_liked: boolean
  created_at: string
  updated_at: string
  // extras from show endpoint
  active_claim?: ActiveClaim | null
  mine_queue_count?: number
  steal_queue_count?: number
}

export interface ActiveClaim {
  id: number
  type: 'mine' | 'steal' | 'grab'
  user_id: number
  status: string
  /** 'claim' = holding the piece (others may Steal) | 'payment' = window open */
  phase: 'claim' | 'payment' | null
  claim_expires_at: string | null
  payment_starts_at: string | null
  payment_expires_at: string | null
  /** Deadline of the CURRENT phase (claim or payment) */
  expires_at: string | null
}

export interface FilterOptions {
  categories: string[]
  sizes: string[]
  conditions: string[]
  statuses: string[]
}

export interface ProductFilters {
  search?: string
  category?: string
  size?: string
  condition?: string
  status?: string
  page?: number
}

export interface PaginatedProducts {
  data: Product[]
  meta: { current_page: number; last_page: number; total: number }
  links: unknown
}

export const productService = {
  async list(filters: ProductFilters = {}) {
    const { data } = await api.get('/products', { params: filters })
    return data as PaginatedProducts
  },

  async get(id: number) {
    const { data } = await api.get(`/products/${id}`)
    return data.data as Product
  },

  async filterOptions() {
    const { data } = await api.get('/products/filter-options')
    return data as FilterOptions
  },

  async getClaims(productId: number) {
    const { data } = await api.get(`/products/${productId}/claims`)
    return data.data as ClaimRecord[]
  },

  // ── Admin ────────────────────────────────────────────────────────────────

  async adminList(params: Record<string, unknown> = {}) {
    const { data } = await api.get('/admin/products', { params })
    return data
  },

  async adminGet(id: number) {
    const { data } = await api.get(`/admin/products/${id}`)
    return data.data as Product
  },

  async adminCreate(payload: Partial<Product>) {
    const { data } = await api.post('/admin/products', payload)
    return data.data as Product
  },

  async adminUpdate(id: number, payload: Partial<Product>) {
    const { data } = await api.put(`/admin/products/${id}`, payload)
    return data.data as Product
  },

  async adminUploadImage(id: number, file: File) {
    const form = new FormData()
    form.append('image', file)
    const { data } = await api.post(`/admin/products/${id}/upload-image`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },
}

// Re-export for convenience
export interface ClaimRecord {
  id: number
  product_id: number
  user_id: number
  user_name?: string | null
  type: 'mine' | 'steal' | 'grab'
  position: number
  status: 'waiting' | 'active' | 'expired' | 'completed' | 'overridden' | 'cancelled'
  amount: number
  /** 'claim' = holding the piece (others may Steal) | 'payment' = window open */
  phase: 'claim' | 'payment' | null
  claim_expires_at: string | null
  payment_starts_at: string | null
  payment_expires_at: string | null
  /** Server-side truth: payment is open right now */
  can_pay: boolean
  /** Deadline of the CURRENT phase (claim or payment) */
  expires_at: string | null
  created_at: string
  product?: { id: number; name: string; image_url: string | null; status: string } | null
  order?: { id: number; order_number: string; payment_status: string; status: string; expires_at: string | null } | null
}
