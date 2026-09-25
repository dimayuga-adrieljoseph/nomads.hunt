<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLikeStore } from '@/stores/likes'
import type { Product } from '@/services/product.service'

const props = withDefaults(defineProps<{
  product: Product
  variant?: 'card' | 'detail'
}>(), {
  variant: 'card',
})

const auth = useAuthStore()
const likes = useLikeStore()
const route = useRoute()
const router = useRouter()
const localError = ref('')

const isLiked = computed(() => likes.isLiked(props.product))
const isPending = computed(() => likes.pending[props.product.id] ?? false)
const errorMessage = computed(() => localError.value || likes.errorFor(props.product.id))
const label = computed(() => isLiked.value
  ? `Remove ${props.product.name} from liked products`
  : `Like ${props.product.name}`,
)

async function toggle(event: MouseEvent) {
  event.stopPropagation()
  localError.value = ''

  if (!auth.isCustomer) {
    if (!auth.isLoggedIn) {
      await router.push({ name: 'login', query: { redirect: route.fullPath } })
    } else {
      localError.value = 'Sign in with a customer account to like products.'
    }
    return
  }

  try {
    await likes.toggle(props.product)
  } catch {
    // The shared store owns rollback and the server error message.
  }
}
</script>

<template>
  <div :class="['like-control', `like-control--${variant}`]">
    <button
      type="button"
      :class="['like-button', { 'like-button--active': isLiked }]"
      :aria-label="label"
      :title="label"
      :aria-pressed="isLiked"
      :aria-busy="isPending"
      :disabled="isPending"
      @click="toggle"
    >
      <svg
        :class="['like-button__heart', { 'like-button__heart--active': isLiked }]"
        width="22"
        height="22"
        viewBox="0 0 24 24"
        :fill="isLiked ? 'currentColor' : 'none'"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
      </svg>
      <span class="like-control__sr-only">{{ label }}</span>
    </button>
    <span v-if="errorMessage" class="like-control__error" role="alert">
      {{ errorMessage }}
    </span>
  </div>
</template>

<style scoped>
.like-control {
  position: relative;
  display: inline-flex;
  align-items: center;
}
.like-control--card { position: absolute; top: .75rem; right: .75rem; z-index: 20; }
.like-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  border: 1px solid rgba(254,243,238,.25);
  border-radius: 50%;
  color: var(--color-seashell-muted);
  background: rgba(26,15,5,.82);
  box-shadow: 0 2px 10px rgba(26,15,5,.18);
  transition: color var(--transition-fast), border-color var(--transition-fast),
    background var(--transition-fast), transform var(--transition-fast);
}
.like-button:hover:not(:disabled) {
  color: var(--color-seashell);
  border-color: var(--color-spice-market);
  transform: scale(1.06);
}
.like-button--active,
.like-button--active:hover:not(:disabled) {
  color: var(--color-spice-market);
  border-color: var(--color-spice-market);
  background: rgba(26,15,5,.94);
}
.like-button:disabled { cursor: wait; }
.like-button__heart { display: block; transform-origin: center; }
.like-button__heart--active { animation: like-heart-in 180ms ease; }
.like-control--detail .like-button {
  width: 2.875rem;
  height: 2.875rem;
  color: var(--color-seashell-muted);
  background: var(--color-balsamico-light);
  border-color: var(--color-spice-border);
}
.like-control--detail .like-button--active { color: var(--color-spice-market); }
.like-control__error {
  position: absolute;
  top: calc(100% + .5rem);
  right: 0;
  z-index: 30;
  width: max-content;
  max-width: 15rem;
  padding: .45rem .6rem;
  color: #E8906A;
  background: var(--color-balsamico-light);
  border: 1px solid rgba(186,68,29,.5);
  font-size: .68rem;
  line-height: 1.4;
  text-align: left;
}
.like-control--detail .like-control__error { right: auto; left: 0; }
.like-control__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
@keyframes like-heart-in {
  0% { transform: scale(.82); opacity: .65; }
  70% { transform: scale(1.1); }
  100% { transform: scale(1); opacity: 1; }
}
@media (prefers-reduced-motion: reduce) {
  .like-button { transition: none; }
  .like-button__heart--active { animation: none; }
}
</style>
