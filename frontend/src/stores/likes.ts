import { defineStore } from 'pinia'
import { ref } from 'vue'
import { likeService } from '@/services/like.service'
import type { Product } from '@/services/product.service'

type LikeState = Record<number, boolean>

export const useLikeStore = defineStore('likes', () => {
  const states = ref<LikeState>({})
  const pending = ref<LikeState>({})
  const errors = ref<Record<number, string>>({})

  function sync(products: readonly Product[]) {
    for (const product of products) {
      if (!pending.value[product.id]) states.value[product.id] = product.is_liked
    }
  }

  function isLiked(product: Pick<Product, 'id' | 'is_liked'>): boolean {
    return states.value[product.id] ?? product.is_liked
  }

  function errorFor(productId: number): string {
    return errors.value[productId] ?? ''
  }

  async function toggle(product: Product): Promise<boolean> {
    if (pending.value[product.id]) return isLiked(product)

    const previous = isLiked(product)
    const optimistic = !previous

    states.value[product.id] = optimistic
    pending.value[product.id] = true
    delete errors.value[product.id]

    try {
      const savedProduct = optimistic
        ? await likeService.like(product.id)
        : await likeService.unlike(product.id)

      states.value[product.id] = savedProduct.is_liked
      return savedProduct.is_liked
    } catch (error: unknown) {
      states.value[product.id] = previous
      const response = error as { response?: { data?: { message?: string } } }
      errors.value[product.id] = response.response?.data?.message ?? 'Could not update liked products.'
      throw error
    } finally {
      delete pending.value[product.id]
    }
  }

  function reset() {
    states.value = {}
    pending.value = {}
    errors.value = {}
  }

  return { states, pending, errors, sync, isLiked, errorFor, toggle, reset }
})
