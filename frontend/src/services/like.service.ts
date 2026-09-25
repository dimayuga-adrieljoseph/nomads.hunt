import api from './api'
import type { Product } from './product.service'

export const likeService = {
  async list() {
    const { data } = await api.get('/likes')
    return data.data as Product[]
  },

  async like(productId: number) {
    const { data } = await api.post(`/products/${productId}/like`)
    return data.data as Product
  },

  async unlike(productId: number) {
    const { data } = await api.delete(`/products/${productId}/like`)
    return data.data as Product
  },
}
