<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AnnouncementBanner from '@/components/AnnouncementBanner.vue'
import {
  announcementService,
  type Announcement,
  type AnnouncementDisplayStatus,
} from '@/services/announcement.service'
import { formatDate } from '@/utils/formatters'

const announcements = ref<Announcement[]>([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const previewAnnouncement = ref<Announcement | null>(null)
let searchTimer: ReturnType<typeof setTimeout>

async function load(page = 1): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await announcementService.adminList({
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      page,
    })
    announcements.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
    total.value = response.meta.total
  } catch {
    error.value = 'Unable to load announcements. Please try again.'
  } finally {
    loading.value = false
  }
}

function onSearchInput(): void {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void load(1), 400)
}

function statusLabel(status: AnnouncementDisplayStatus): string {
  const labels: Record<AnnouncementDisplayStatus, string> = {
    DRAFT: 'Draft',
    PUBLISHED: 'Published',
    ARCHIVED: 'Archived',
    ACTIVE: 'Active',
    SCHEDULED: 'Scheduled',
    EXPIRED: 'Expired',
  }
  return labels[status] ?? status
}

function statusClass(status: AnnouncementDisplayStatus): string {
  const classes: Record<AnnouncementDisplayStatus, string> = {
    DRAFT: 'badge--waiting',
    PUBLISHED: 'badge--available',
    ARCHIVED: 'badge--sold',
    ACTIVE: 'badge--active',
    SCHEDULED: 'badge--pending',
    EXPIRED: 'badge--expired',
  }
  return classes[status] ?? 'badge--default'
}

function eventDate(value: string | null): string {
  if (!value) return '—'
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function schedule(item: Announcement): string {
  const starts = item.starts_at ? formatDate(item.starts_at) : 'Immediate'
  const ends = item.ends_at ? formatDate(item.ends_at) : 'No expiry'
  return `${starts} → ${ends}`
}

async function runAction(
  item: Announcement,
  action: 'publish' | 'unpublish' | 'archive',
): Promise<void> {
  error.value = ''
  try {
    await announcementService.adminAction(item.id, action)
    await load(currentPage.value)
  } catch {
    error.value = 'Unable to update announcement status. Please try again.'
  }
}

async function remove(item: Announcement): Promise<void> {
  if (!window.confirm(`Delete “${item.title}”? This cannot be undone.`)) return

  error.value = ''
  try {
    await announcementService.adminDelete(item.id)
    const nextPage = announcements.value.length === 1 && currentPage.value > 1
      ? currentPage.value - 1
      : currentPage.value
    await load(nextPage)
  } catch {
    error.value = 'Unable to delete announcement. Please try again.'
  }
}

function showPreview(item: Announcement): void {
  previewAnnouncement.value = item
}

onMounted(() => void load())
</script>
<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin</p>
        <h1 class="admin-page-title">Announcements <span class="admin-count">{{ total }}</span></h1>
      </div>
      <RouterLink to="/admin/announcements/new" class="btn btn--primary btn--sm">+ New Announcement</RouterLink>
    </div>

    <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

    <div class="admin-toolbar">
      <input
        v-model="search"
        type="search"
        class="admin-toolbar__search form-input"
        placeholder="Search title, label, location…"
        @input="onSearchInput"
      />
      <select v-model="statusFilter" class="form-select admin-toolbar__select" @change="load(1)">
        <option value="">All Statuses</option>
        <option value="ACTIVE">Active</option>
        <option value="SCHEDULED">Scheduled</option>
        <option value="EXPIRED">Expired</option>
        <option value="DRAFT">Draft</option>
        <option value="PUBLISHED">Published</option>
        <option value="ARCHIVED">Archived</option>
      </select>
      <button class="btn btn--ghost btn--sm" :disabled="loading" @click="load(1)">↻ Refresh</button>
    </div>

    <div v-if="loading" class="spinner" />

    <div v-else class="table-wrap admin-table-card">
      <table>
        <thead>
          <tr>
            <th>Announcement</th>
            <th>Event</th>
            <th>Status</th>
            <th>Schedule</th>
            <th>Priority</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in announcements" :key="item.id">
            <td>
              <div class="announcement-cell">
                <div class="announcement-cell__image">
                  <img v-if="item.image_url" :src="item.image_url" :alt="item.title" />
                  <span v-else aria-hidden="true">—</span>
                </div>
                <div class="announcement-cell__copy">
                  <strong>{{ item.title }}</strong>
                  <small v-if="item.label">{{ item.label }}</small>
                </div>
              </div>
            </td>
            <td class="td-muted">
              <div>{{ eventDate(item.event_date) }}</div>
              <small>{{ item.event_time ?? '—' }}</small>
            </td>
            <td>
              <span :class="['badge', statusClass(item.display_status)]">{{ statusLabel(item.display_status) }}</span>
              <span v-if="item.is_active" class="homepage-flag">On homepage</span>
            </td>
            <td class="td-schedule">{{ schedule(item) }}</td>
            <td class="td-priority">{{ item.priority }}</td>
            <td>
              <div class="row-actions row-actions--wrap">
                <button class="btn btn--ghost btn--sm" @click="showPreview(item)">Preview</button>
                <RouterLink :to="`/admin/announcements/${item.id}/edit`" class="btn btn--ghost btn--sm">Edit</RouterLink>
                <button
                  v-if="item.status !== 'PUBLISHED'"
                  class="btn btn--ghost btn--sm"
                  @click="runAction(item, 'publish')"
                >Publish</button>
                <button
                  v-else
                  class="btn btn--ghost btn--sm"
                  @click="runAction(item, 'unpublish')"
                >Unpublish</button>
                <button
                  v-if="item.status !== 'ARCHIVED'"
                  class="btn btn--ghost btn--sm"
                  @click="runAction(item, 'archive')"
                >Archive</button>
                <button class="btn btn--danger btn--sm" @click="remove(item)">Delete</button>
              </div>
            </td>
          </tr>
          <tr v-if="announcements.length === 0">
            <td colspan="6" class="empty-cell">No announcements found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="currentPage === 1" @click="load(currentPage - 1)">← Prev</button>
      <span class="pagination__info">{{ currentPage }} / {{ lastPage }}</span>
      <button :disabled="currentPage === lastPage" @click="load(currentPage + 1)">Next →</button>
    </div>

    <div v-if="previewAnnouncement" class="preview-backdrop" @click.self="previewAnnouncement = null">
      <div class="preview-dialog" role="dialog" aria-modal="true" aria-label="Announcement preview">
        <div class="preview-dialog__header">
          <div>
            <p class="admin-eyebrow">Homepage Preview</p>
            <h2>{{ previewAnnouncement.title }}</h2>
          </div>
          <button class="btn btn--ghost btn--sm" @click="previewAnnouncement = null">Close</button>
        </div>
        <AnnouncementBanner :announcement="previewAnnouncement" />
        <p class="preview-dialog__meta">{{ schedule(previewAnnouncement) }}</p>
      </div>
    </div>
  </div>
</template>
<style scoped>
.admin-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.75rem; }
.admin-eyebrow { margin-bottom: .3rem; color: var(--color-spice-market); font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; }
.admin-page-title { display: flex; align-items: baseline; gap: .75rem; color: var(--color-seashell); font-size: 1.5rem; font-weight: 700; }
.admin-count { color: var(--color-seashell-muted); font-size: .85rem; font-weight: 400; }
.admin-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem; }
.admin-toolbar__search { flex: 1; min-width: 220px; }
.admin-toolbar__select { width: auto; min-width: 160px; }
.admin-table-card { background: var(--color-balsamico-light); border: 1px solid var(--color-balsamico-border); border-radius: var(--radius); }

.announcement-cell { display: flex; align-items: center; gap: .75rem; min-width: 220px; }
.announcement-cell__image { display: flex; align-items: center; justify-content: center; width: 48px; height: 38px; flex-shrink: 0; overflow: hidden; color: var(--color-seashell-muted); background: var(--color-balsamico-lighter); border: 1px solid var(--color-balsamico-border); }
.announcement-cell__image img { width: 100%; height: 100%; object-fit: cover; }
.announcement-cell__copy { display: flex; flex-direction: column; gap: .2rem; }
.announcement-cell__copy strong { color: var(--color-seashell-strong); font-size: .875rem; font-weight: 600; }
.announcement-cell__copy small { color: var(--color-spice-market); font-size: .65rem; font-weight: 700; letter-spacing: .1em; }
.td-muted { color: var(--color-seashell-muted); font-size: .82rem; }
.td-muted small { color: rgba(254,243,238,.3); font-size: .72rem; }
.td-schedule { max-width: 220px; color: var(--color-seashell-muted); font-size: .75rem; line-height: 1.45; }
.td-priority { color: var(--color-spice-market); font-size: .85rem; font-weight: 600; }
.homepage-flag { display: block; width: fit-content; margin-top: .35rem; color: var(--color-spice-market); font-size: .62rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.row-actions { display: flex; gap: .375rem; }
.row-actions--wrap { flex-wrap: wrap; min-width: 190px; }
.empty-cell { padding: 3rem; color: var(--color-seashell-muted); text-align: center; }
.pagination__info { color: var(--color-seashell-muted); font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; }

.preview-backdrop { position: fixed; inset: 0; z-index: 200; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(26,15,5,.78); }
.preview-dialog { width: min(900px, 100%); overflow: hidden; background: var(--color-balsamico-light); border: 1px solid var(--color-spice-border); box-shadow: 0 20px 70px rgba(0,0,0,.45); }
.preview-dialog__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem; }
.preview-dialog__header h2 { color: var(--color-seashell); font-size: 1.1rem; }
.preview-dialog__meta { padding: .9rem 1.5rem 1.25rem; color: var(--color-seashell-muted); font-size: .75rem; }

@media (max-width: 700px) {
  .admin-page-header { flex-direction: column; }
  .admin-toolbar__select { flex: 1; }
  .preview-dialog__header { padding: 1rem; }
}
</style>