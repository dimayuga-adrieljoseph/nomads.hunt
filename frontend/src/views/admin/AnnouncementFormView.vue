<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AnnouncementBanner from '@/components/AnnouncementBanner.vue'
import {
  announcementService,
  type Announcement,
  type AnnouncementPayload,
  type AnnouncementStatus,
} from '@/services/announcement.service'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => route.params.id !== undefined)
const announcementId = computed(() => Number(route.params.id))

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string>>({})
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const localPreviewUrl = ref<string | null>(null)
const removeImage = ref(false)

const form = ref({
  title: '',
  label: '',
  description: '',
  event_date: '',
  event_time: '',
  location: '',
  starts_at: '',
  ends_at: '',
  priority: '0',
  status: 'DRAFT' as AnnouncementStatus,
})

function toDateTimeLocal(value: string | null): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
  return local.toISOString().slice(0, 16)
}

function setLocalPreview(file: File | null): void {
  if (localPreviewUrl.value) URL.revokeObjectURL(localPreviewUrl.value)
  localPreviewUrl.value = file ? URL.createObjectURL(file) : null
  if (file) imagePreview.value = localPreviewUrl.value
}

function populateForm(item: Announcement): void {
  form.value = {
    title: item.title,
    label: item.label ?? '',
    description: item.description ?? '',
    event_date: item.event_date ?? '',
    event_time: item.event_time ?? '',
    location: item.location ?? '',
    starts_at: toDateTimeLocal(item.starts_at),
    ends_at: toDateTimeLocal(item.ends_at),
    priority: String(item.priority ?? 0),
    status: item.status,
  }
  imagePreview.value = item.image_url
}

onMounted(async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    populateForm(await announcementService.adminGet(announcementId.value))
  } catch {
    error.value = 'Unable to load this announcement. Please try again.'
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => {
  if (localPreviewUrl.value) URL.revokeObjectURL(localPreviewUrl.value)
})

const previewAnnouncement = computed<Announcement>(() => ({
  id: announcementId.value || 0,
  title: form.value.title || 'Your announcement title',
  label: form.value.label || null,
  description: form.value.description || null,
  event_date: form.value.event_date || null,
  event_time: form.value.event_time || null,
  location: form.value.location || null,
  image_url: imagePreview.value,
  status: form.value.status,
  display_status: form.value.status,
  priority: Number(form.value.priority) || 0,
  starts_at: form.value.starts_at || null,
  ends_at: form.value.ends_at || null,
  is_eligible: false,
  is_active: false,
  created_at: '',
  updated_at: '',
}))

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    error.value = 'Please choose a JPG, PNG, or WebP image.'
    input.value = ''
    return
  }

  if (file.size > 4 * 1024 * 1024) {
    error.value = 'Image must be 4 MB or smaller.'
    input.value = ''
    return
  }

  error.value = ''
  removeImage.value = false
  imageFile.value = file
  setLocalPreview(file)
}

function removeCurrentImage(): void {
  if (localPreviewUrl.value) URL.revokeObjectURL(localPreviewUrl.value)
  localPreviewUrl.value = null
  imageFile.value = null
  imagePreview.value = null
  removeImage.value = true
}

function buildPayload(status: AnnouncementStatus): AnnouncementPayload {
  return {
    title: form.value.title.trim(),
    label: form.value.label.trim() || null,
    description: form.value.description.trim() || null,
    event_date: form.value.event_date || null,
    event_time: form.value.event_time.trim() || null,
    location: form.value.location.trim() || null,
    status,
    priority: Math.max(0, Number(form.value.priority) || 0),
    starts_at: form.value.starts_at || null,
    ends_at: form.value.ends_at || null,
  }
}

async function submitWithStatus(status: AnnouncementStatus): Promise<void> {
  if (saving.value) return
  saving.value = true
  error.value = ''
  fieldErrors.value = {}

  try {
    const payload = buildPayload(status)
    if (isEdit.value) {
      await announcementService.adminUpdate(
        announcementId.value,
        payload,
        imageFile.value,
        removeImage.value,
      )
    } else {
      await announcementService.adminCreate(payload, imageFile.value)
    }
    await router.push('/admin/announcements')
  } catch (requestError: unknown) {
    const response = (requestError as {
      response?: { data?: { errors?: Record<string, string[]> } }
    }).response
    if (response?.data?.errors) {
      Object.entries(response.data.errors).forEach(([key, messages]) => {
        fieldErrors.value[key] = messages[0] ?? ''
      })
    }
    error.value = 'Unable to save announcement. Please try again.'
  } finally {
    saving.value = false
  }
}
</script>
<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin / Announcements</p>
        <RouterLink to="/admin/announcements" class="back-link">← Back to Announcements</RouterLink>
        <h1 class="admin-page-title">{{ isEdit ? 'Edit Announcement' : 'New Announcement' }}</h1>
      </div>
    </div>

    <div v-if="loading" class="spinner" />

    <form v-else class="announcement-form" @submit.prevent="submitWithStatus(form.status)">
      <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

      <div class="form-grid">
        <div class="form-col">
          <section class="form-section">
            <h2 class="form-section__title">Announcement Content</h2>
            <div class="form-group">
              <label for="af-label" class="form-label">Label</label>
              <input id="af-label" v-model="form.label" type="text" class="form-input" placeholder="NEXT FIELD" />
              <span v-if="fieldErrors.label" class="form-error">{{ fieldErrors.label }}</span>
            </div>
            <div class="form-group">
              <label for="af-title" class="form-label">Title *</label>
              <input id="af-title" v-model="form.title" type="text" class="form-input" required />
              <span v-if="fieldErrors.title" class="form-error">{{ fieldErrors.title }}</span>
            </div>
            <div class="form-group">
              <label for="af-description" class="form-label">Description</label>
              <textarea id="af-description" v-model="form.description" class="form-textarea" rows="3" placeholder="Date | Time | Location" />
              <span v-if="fieldErrors.description" class="form-error">{{ fieldErrors.description }}</span>
            </div>
          </section>

          <section class="form-section">
            <h2 class="form-section__title">Event Details</h2>
            <div class="form-row-2">
              <div class="form-group">
                <label for="af-event-date" class="form-label">Event Date</label>
                <input id="af-event-date" v-model="form.event_date" type="date" class="form-input" />
              </div>
              <div class="form-group">
                <label for="af-event-time" class="form-label">Event Time</label>
                <input id="af-event-time" v-model="form.event_time" type="text" class="form-input" placeholder="11AM–9PM" />
              </div>
            </div>
            <div class="form-group">
              <label for="af-location" class="form-label">Location</label>
              <input id="af-location" v-model="form.location" type="text" class="form-input" placeholder="G Studios, Alabang" />
            </div>
          </section>

          <section class="form-section">
            <h2 class="form-section__title">Publication Schedule</h2>
            <div class="form-row-2">
              <div class="form-group">
                <label for="af-starts" class="form-label">Start Showing</label>
                <input id="af-starts" v-model="form.starts_at" type="datetime-local" class="form-input" />
                <span v-if="fieldErrors.starts_at" class="form-error">{{ fieldErrors.starts_at }}</span>
              </div>
              <div class="form-group">
                <label for="af-ends" class="form-label">Stop Showing</label>
                <input id="af-ends" v-model="form.ends_at" type="datetime-local" class="form-input" />
                <span v-if="fieldErrors.ends_at" class="form-error">{{ fieldErrors.ends_at }}</span>
              </div>
            </div>
            <div class="form-group">
              <label for="af-priority" class="form-label">Homepage Priority</label>
              <input id="af-priority" v-model="form.priority" type="number" min="0" max="100" class="form-input" />
              <p class="form-hint">Higher priority wins when more than one announcement is eligible.</p>
            </div>
          </section>
        </div>

        <div class="form-col">
          <section class="form-section preview-section">
            <h2 class="form-section__title">Live Homepage Preview</h2>
            <p class="form-hint preview-section__hint">This uses the same banner component as the public homepage.</p>
            <div class="preview-wrap">
              <AnnouncementBanner :announcement="previewAnnouncement" />
            </div>
          </section>

          <section class="form-section">
            <h2 class="form-section__title">Background Image</h2>
            <div class="image-upload">
              <div class="image-upload__preview">
                <img v-if="imagePreview" :src="imagePreview" alt="Announcement preview" />
                <div v-else class="image-upload__placeholder">No image selected</div>
              </div>
              <label class="btn btn--ghost btn--sm image-upload__label">
                Choose Image
                <input type="file" accept="image/jpeg,image/png,image/webp" class="image-upload__input" @change="onFileChange" />
              </label>
              <button v-if="imagePreview" type="button" class="btn btn--danger btn--sm" @click="removeCurrentImage">Remove Image</button>
              <p class="image-upload__hint">JPG, PNG or WebP — max 4 MB</p>
            </div>
          </section>

          <section class="form-section">
            <h2 class="form-section__title">Publication Status</h2>
            <div class="form-group">
              <label for="af-status" class="form-label">Saved Status</label>
              <select id="af-status" v-model="form.status" class="form-select">
                <option value="DRAFT">Draft</option>
                <option value="PUBLISHED">Published</option>
                <option value="ARCHIVED">Archived</option>
              </select>
              <p class="form-hint">Published announcements follow the schedule above automatically.</p>
            </div>
            <span v-if="fieldErrors.status" class="form-error">{{ fieldErrors.status }}</span>
          </section>

          <div class="form-actions">
            <RouterLink to="/admin/announcements" class="btn btn--ghost">Cancel</RouterLink>
            <button type="button" class="btn btn--ghost" :disabled="saving" @click="submitWithStatus('DRAFT')">Save Draft</button>
            <button type="submit" class="btn btn--primary" :disabled="saving">{{ saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Announcement') }}</button>
            <button type="button" class="btn btn--primary" :disabled="saving" @click="submitWithStatus('PUBLISHED')">Publish</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>
<style scoped>
.admin-page-header { margin-bottom: 2rem; }
.admin-eyebrow { font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--color-spice-market); margin-bottom: .375rem; }
.back-link { font-size: .75rem; color: var(--color-seashell-muted); display: inline-block; margin-bottom: .375rem; transition: color var(--transition-fast); }
.back-link:hover { color: var(--color-spice-market); }
.admin-page-title { font-size: 1.5rem; font-weight: 700; color: var(--color-seashell); }

.announcement-form { display: flex; flex-direction: column; gap: 1.25rem; }
.form-grid { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 1.25rem; align-items: start; }
.form-col { display: flex; flex-direction: column; gap: 1.25rem; min-width: 0; }
.form-section {
  padding: 1.75rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}
.form-section__title {
  padding-bottom: .75rem;
  border-bottom: 1px solid var(--color-balsamico-border);
  color: var(--color-seashell-muted);
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
}
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
.form-hint, .image-upload__hint { margin-top: .375rem; color: var(--color-seashell-muted); font-size: .72rem; }
.preview-section__hint { margin: -.35rem 0 0; }
.preview-wrap { overflow: hidden; border: 1px solid var(--color-balsamico-border); }
.preview-wrap :deep(.announcement-banner) { min-height: 140px; }

.image-upload { display: flex; flex-direction: column; align-items: flex-start; gap: .875rem; }
.image-upload__preview {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  background: var(--color-balsamico-lighter);
  border: 1px solid var(--color-balsamico-border);
}
.image-upload__preview img { width: 100%; height: 100%; object-fit: cover; }
.image-upload__placeholder { color: var(--color-seashell-muted); font-size: .82rem; }
.image-upload__label { cursor: pointer; }
.image-upload__input { display: none; }
.form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .75rem; }

@media (max-width: 1050px) {
  .form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .form-section { padding: 1.25rem; }
  .form-row-2 { grid-template-columns: 1fr; }
  .form-actions { justify-content: stretch; }
  .form-actions .btn { flex: 1 1 auto; }
}
</style>
