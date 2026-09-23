import api from './api'
import type { ClaimRecord } from './product.service'

export type { ClaimRecord }

export interface MyClaims {
  active: ClaimRecord[]
  waiting: ClaimRecord[]
  completed: ClaimRecord[]
  expired: ClaimRecord[]
}

export const claimService = {
  async mine(productId: number) {
    const { data } = await api.post(`/products/${productId}/mine`)
    return data as { message: string; claim: ClaimRecord }
  },

  async steal(productId: number) {
    const { data } = await api.post(`/products/${productId}/steal`)
    return data as { message: string; claim: ClaimRecord }
  },

  async grab(productId: number) {
    const { data } = await api.post(`/products/${productId}/grab`)
    return data as { message: string; claim: ClaimRecord }
  },

  async myClaims() {
    const { data } = await api.get('/my-claims')
    return data as MyClaims
  },

  // ── Admin ────────────────────────────────────────────────────────────────

  async adminList(params: Record<string, unknown> = {}) {
    const { data } = await api.get('/admin/claims', { params })
    return data
  },

  async adminProductClaims(productId: number) {
    const { data } = await api.get(`/admin/products/${productId}/claims`)
    return data
  },

  async adminForceExpire(claimId: number) {
    const { data } = await api.post(`/admin/claims/${claimId}/force-expire`)
    return data
  },
}
