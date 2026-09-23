<script setup lang="ts">
/**
 * ClaimTimer — purely visual countdown from an expires_at ISO timestamp.
 * The backend is authoritative; this is display-only.
 */
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'

const props = defineProps<{
  expiresAt: string | null | undefined
  urgent?: boolean   // show red when under 60s
}>()

const emit = defineEmits<{ (e: 'expired'): void }>()

const remaining = ref(0)
let   timer: ReturnType<typeof setInterval> | null = null

function calc() {
  if (!props.expiresAt) { remaining.value = 0; return }
  const diff = Math.floor((new Date(props.expiresAt).getTime() - Date.now()) / 1000)
  remaining.value = Math.max(0, diff)
  if (remaining.value === 0) emit('expired')
}

const display = computed(() => {
  const s = remaining.value
  const m = Math.floor(s / 60)
  const sec = s % 60
  return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const isUrgent = computed(() => remaining.value > 0 && remaining.value <= 60)

function start() {
  calc()
  timer = setInterval(calc, 1000)
}

onMounted(start)
onBeforeUnmount(() => { if (timer) clearInterval(timer) })
watch(() => props.expiresAt, () => {
  if (timer) clearInterval(timer)
  start()
})
</script>

<template>
  <span :class="['timer', { 'timer--urgent': isUrgent, 'timer--expired': remaining === 0 }]">
    <slot name="prefix" />
    {{ remaining === 0 ? 'EXPIRED' : display }}
  </span>
</template>

<style scoped>
.timer {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  font-size: inherit;
  letter-spacing: .04em;
  color: var(--color-text);
  transition: color .3s;
}
.timer--urgent  { color: var(--color-steal); }
.timer--expired { color: var(--color-muted); }
</style>
