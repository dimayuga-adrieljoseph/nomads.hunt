<script setup lang="ts">
import { computed } from 'vue'
import type { PublicAnnouncement } from '@/services/announcement.service'

const props = defineProps<{
  announcement: PublicAnnouncement | null
}>()

const description = computed(() => {
  const item = props.announcement
  if (!item) return ''
  if (item.description) return item.description

  return [item.event_date, item.event_time, item.location]
    .filter(Boolean)
    .join(' | ')
})

const bannerStyle = computed<Record<string, string>>(() => {
  const imageUrl = props.announcement?.image_url
  if (!imageUrl) return { '--announcement-image': 'none' }

  const safeUrl = imageUrl.replaceAll('"', '\\"')
  return { '--announcement-image': `url("${safeUrl}")` }
})
</script>

<template>
  <section
    v-if="announcement"
    class="announcement-banner"
    :style="bannerStyle"
    aria-label="Announcement"
  >
    <div class="announcement-banner__inner container">
      <div class="announcement-banner__left">
        <span v-if="announcement.label" class="announcement-banner__label display">
          {{ announcement.label }}
        </span>
        <span v-if="description" class="announcement-banner__event">
          {{ description }}
        </span>
      </div>
      <div class="announcement-banner__right">
        <h2 class="announcement-banner__heading display">{{ announcement.title }}</h2>
      </div>
    </div>
    <div class="announcement-banner__overlay" aria-hidden="true" />
  </section>
</template>

<style scoped>
.announcement-banner {
  position: relative;
  height: 140px;
  overflow: hidden;
  border-top: 1px solid var(--color-balsamico-border);
  border-bottom: 1px solid var(--color-balsamico-border);
  background-color: var(--color-balsamico);
  background-image:
    linear-gradient(105deg, rgba(26, 15, 5, .98), rgba(26, 15, 5, .72)),
    var(--announcement-image, none);
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
}

.announcement-banner__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(26, 15, 5, .7), transparent 75%);
  pointer-events: none;
}

.announcement-banner__inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 100%;
  gap: 2rem;
}

.announcement-banner__left {
  display: flex;
  flex-direction: column;
  gap: .5rem;
  min-width: 0;
}

.announcement-banner__label {
  align-self: flex-start;
  padding: .2rem .75rem;
  color: var(--color-seashell);
  background: var(--color-spice-market);
  font-size: 1.1rem;
  letter-spacing: .12em;
  line-height: 1.4;
  text-transform: uppercase;
}

.announcement-banner__event {
  color: var(--color-seashell-muted);
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: .08em;
  line-height: 1.5;
  text-transform: uppercase;
}

.announcement-banner__right {
  min-width: 0;
  text-align: right;
}

.announcement-banner__heading {
  color: var(--color-spice-market);
  font-size: clamp(1.8rem, 4vw, 3rem);
  letter-spacing: .04em;
  line-height: 1.15;
}

@media (max-width: 768px) {
  .announcement-banner {
    height: auto;
    min-height: 140px;
    padding: 1.5rem 0;
  }

  .announcement-banner__inner {
    height: auto;
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .announcement-banner__right {
    text-align: left;
  }

  .announcement-banner__heading {
    font-size: 1.8rem;
  }
}
</style>
