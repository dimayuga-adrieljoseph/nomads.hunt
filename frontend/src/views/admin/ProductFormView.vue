<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { productService } from '@/services/product.service'

const route  = useRoute()
const router = useRouter()

const isEdit  = computed(() => !!route.params.id)
const loading = ref(false)
const saving  = ref(false)
const error   = ref('')
const fieldErrors = ref<Record<string, string>>({})

type ImageDraft = {
  key: string
  name: string
  preview: string
  file?: File
  existingId?: number
  objectUrl?: string
}

const imageDrafts = ref<ImageDraft[]>([])
const primaryImageKey = ref<string | null>(null)
const draggingFiles = ref(false)
let draftSequence = 0
const acceptedTypes = new Set(['image/jpeg', 'image/png', 'image/webp'])
const maxFileSize = 4 * 1024 * 1024

const form = ref({
  name: '', description: '', category: '', brand: '', size: '',
  condition: 'good' as 'excellent' | 'good' | 'fair' | 'poor',
  mine_price: '' as number | string, steal_price: '' as number | string, grab_price: '' as number | string,
  status: 'available',
})

const productId = computed(() => Number(route.params.id))

onMounted(async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const p = await productService.adminGet(productId.value)
    form.value = {
      name: p.name, description: p.description ?? '', category: p.category ?? '',
      brand: p.brand ?? '', size: p.size ?? '', condition: p.condition,
      mine_price: p.mine_price, steal_price: p.steal_price, grab_price: p.grab_price, status: p.status,
    }
    imageDrafts.value = (p.images ?? []).map((image) => ({
      key: `existing-${image.id}`,
      name: `Image ${image.sort_order + 1}`,
      preview: image.url,
      existingId: image.id,
    }))
    primaryImageKey.value = imageDrafts.value.find((image) => {
      const source = p.images?.find((item) => item.id === image.existingId)
      return source?.is_primary
    })?.key ?? imageDrafts.value[0]?.key ?? null
  } catch { error.value = 'Failed to load product.' }
  finally { loading.value = false }
})

function addFiles(fileList: FileList | File[]) {
  error.value = ''
  fieldErrors.value.images = ''

  Array.from(fileList).forEach((file) => {
    if (!acceptedTypes.has(file.type)) {
      fieldErrors.value.images = `${file.name} is not a JPG, PNG, or WebP image.`
      return
    }
    if (file.size > maxFileSize) {
      fieldErrors.value.images = `${file.name} is larger than 4 MB.`
      return
    }

    const objectUrl = URL.createObjectURL(file)
    const draft: ImageDraft = {
      key: `new-${++draftSequence}`,
      name: file.name,
      preview: objectUrl,
      file,
      objectUrl,
    }
    imageDrafts.value.push(draft)
    if (!primaryImageKey.value) primaryImageKey.value = draft.key
  })
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) addFiles(input.files)
  input.value = ''
}

function onFileDrop(e: DragEvent) {
  draggingFiles.value = false
  if (e.dataTransfer?.files) addFiles(e.dataTransfer.files)
}

function removeImage(key: string) {
  const index = imageDrafts.value.findIndex((image) => image.key === key)
  if (index < 0) return
  const removed = imageDrafts.value[index]
  if (!removed) return
  if (removed.existingId && !window.confirm('Remove this saved image when you save the product?')) return
  imageDrafts.value.splice(index, 1)
  if (removed.objectUrl) URL.revokeObjectURL(removed.objectUrl)

  if (primaryImageKey.value === key) {
    primaryImageKey.value = imageDrafts.value[index]?.key ?? imageDrafts.value[0]?.key ?? null
  }
}

function setPrimaryImage(key: string) {
  const index = imageDrafts.value.findIndex((image) => image.key === key)
  const selected = imageDrafts.value[index]
  if (!selected) return
  imageDrafts.value.splice(index, 1)
  imageDrafts.value.unshift(selected)
  primaryImageKey.value = key
}

function moveImage(index: number, direction: -1 | 1) {
  const moving = imageDrafts.value[index]
  if (!moving || moving.key === primaryImageKey.value) return
  const target = index + direction
  if (target < 1 || target >= imageDrafts.value.length) return
  const images = [...imageDrafts.value]
  const current = images[index]
  const adjacent = images[target]
  if (!current || !adjacent) return
  images[index] = adjacent
  images[target] = current
  imageDrafts.value = images
}

function imageDescriptors() {
  const newDrafts = imageDrafts.value.filter((image) => image.file)
  const files = newDrafts
    .map((image) => image.file)
    .filter((file): file is File => file !== undefined)
  const newIndexes = new Map(newDrafts.map((image, index) => [image.key, index + 1]))
  const order = imageDrafts.value.map((image) => image.existingId
    ? `existing:${image.existingId}`
    : `new:${newIndexes.get(image.key)}`)

  return {
    files,
    order,
    primary: primaryImageKey.value
      ? order[imageDrafts.value.findIndex((image) => image.key === primaryImageKey.value)]
      : null,
  }
}

onUnmounted(() => {
  imageDrafts.value.forEach((image) => {
    if (image.objectUrl) URL.revokeObjectURL(image.objectUrl)
  })
})

async function submit() {
  error.value = ''; fieldErrors.value = {}; saving.value = true
  try {
    const payload = {
      ...form.value,
      mine_price:  Number(form.value.mine_price),
      steal_price: Number(form.value.steal_price),
      grab_price:  Number(form.value.grab_price),
      status: form.value.status as 'available' | 'mine_pending' | 'steal_pending' | 'grab_pending' | 'sold',
    }
    const { files, order, primary } = imageDescriptors()

    if (isEdit.value) {
      await productService.adminUpdate(productId.value, payload, files, order, primary, true)
    } else {
      await productService.adminCreate(payload, files, order, primary)
    }
    router.push('/admin/products')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      const errs = err.response.data.errors
      Object.keys(errs).forEach((k) => { fieldErrors.value[k] = (errs[k] ?? [])[0] ?? '' })
    } else { error.value = err.response?.data?.message ?? 'Save failed.' }
  } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="admin-page-header">
      <div>
        <p class="admin-eyebrow">Admin / Products</p>
        <RouterLink to="/admin/products" class="back-link">← Back to Products</RouterLink>
        <h1 class="admin-page-title">{{ isEdit ? 'Edit Product' : 'New Product' }}</h1>
      </div>
    </div>

    <div v-if="loading" class="spinner" />

    <form v-else class="product-form" @submit.prevent="submit">
      <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>

      <div class="form-grid">
        <!-- Left column -->
        <div class="form-col">

          <!-- Basic info -->
          <div class="form-section">
            <h2 class="form-section__title">Basic Info</h2>
            <div class="form-group">
              <label for="pf-name" class="form-label">Name *</label>
              <input id="pf-name" v-model="form.name" type="text" class="form-input" required aria-required="true" />
              <span v-if="fieldErrors.name" class="form-error" role="alert">{{ fieldErrors.name }}</span>
            </div>
            <div class="form-group">
              <label for="pf-desc" class="form-label">Description</label>
              <textarea id="pf-desc" v-model="form.description" class="form-textarea" rows="4" />
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label for="pf-brand" class="form-label">Brand</label>
                <input id="pf-brand" v-model="form.brand" type="text" class="form-input" />
              </div>
              <div class="form-group">
                <label for="pf-cat" class="form-label">Category</label>
                <input id="pf-cat" v-model="form.category" type="text" class="form-input" />
              </div>
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label for="pf-size" class="form-label">Size</label>
                <input id="pf-size" v-model="form.size" type="text" class="form-input" placeholder="e.g. Large, US 10" />
              </div>
              <div class="form-group">
                <label for="pf-cond" class="form-label">Condition *</label>
                <select id="pf-cond" v-model="form.condition" class="form-select">
                  <option value="excellent">Excellent</option>
                  <option value="good">Good</option>
                  <option value="fair">Fair</option>
                  <option value="poor">Poor</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Pricing -->
          <div class="form-section">
            <h2 class="form-section__title">Pricing (₱)</h2>
            <div class="form-row-3">
              <div class="form-group">
                <label for="pf-mine" class="form-label form-label--mine">Mine Price *</label>
                <input id="pf-mine" v-model="form.mine_price" type="number" min="1" class="form-input" required />
                <span v-if="fieldErrors.mine_price" class="form-error" role="alert">{{ fieldErrors.mine_price }}</span>
              </div>
              <div class="form-group">
                <label for="pf-steal" class="form-label form-label--steal">Steal Price *</label>
                <input id="pf-steal" v-model="form.steal_price" type="number" min="1" class="form-input" required />
                <span v-if="fieldErrors.steal_price" class="form-error" role="alert">{{ fieldErrors.steal_price }}</span>
              </div>
              <div class="form-group">
                <label for="pf-grab" class="form-label form-label--grab">Grab Price *</label>
                <input id="pf-grab" v-model="form.grab_price" type="number" min="1" class="form-input" required />
                <span v-if="fieldErrors.grab_price" class="form-error" role="alert">{{ fieldErrors.grab_price }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right column -->
        <div class="form-col">

          <!-- Product images -->
          <div class="form-section">
            <h2 class="form-section__title">
              {{ isEdit ? 'Current Images' : 'Product Images' }}
            </h2>
            <div class="image-upload">
              <label
                class="image-upload__dropzone"
                :class="{ 'image-upload__dropzone--dragging': draggingFiles }"
                @dragenter.prevent="draggingFiles = true"
                @dragover.prevent
                @dragleave.prevent="draggingFiles = false"
                @drop.prevent="onFileDrop"
              >
                <span class="image-upload__dropzone-mark" aria-hidden="true">+</span>
                <strong>Add Product Images</strong>
                <span>Drag & drop or choose multiple JPG, PNG, or WebP files</span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  multiple
                  class="image-upload__input"
                  @change="onFileChange"
                />
              </label>
              <p class="image-upload__hint">JPG, PNG or WebP — 4 MB max per image</p>
              <span v-if="fieldErrors.images" class="form-error" role="alert">{{ fieldErrors.images }}</span>

              <div v-if="imageDrafts.length" class="image-grid" aria-label="Product image previews">
                <article
                  v-for="(image, index) in imageDrafts"
                  :key="image.key"
                  class="image-preview"
                  :class="{ 'image-preview--primary': image.key === primaryImageKey }"
                >
                  <div class="image-preview__frame">
                    <img :src="image.preview" :alt="`${image.name} preview`" />
                    <button
                      type="button"
                      class="image-preview__remove"
                      :aria-label="`Remove ${image.name}`"
                      @click="removeImage(image.key)"
                    >×</button>
                    <span v-if="image.key === primaryImageKey" class="image-preview__badge">Main</span>
                  </div>
                  <strong class="image-preview__name">{{ image.name }}</strong>
                  <div class="image-preview__actions">
                    <button
                      type="button"
                      class="image-preview__main"
                      :disabled="image.key === primaryImageKey"
                      @click="setPrimaryImage(image.key)"
                    >
                      {{ image.key === primaryImageKey ? 'Main image' : 'Set as main' }}
                    </button>
                    <span class="image-preview__order">
                      <button
                        type="button"
                        :disabled="index <= 1"
                        :aria-label="`Move ${image.name} left`"
                        @click="moveImage(index, -1)"
                      >←</button>
                      <button
                        type="button"
                        :disabled="image.key === primaryImageKey || index === imageDrafts.length - 1"
                        :aria-label="`Move ${image.name} right`"
                        @click="moveImage(index, 1)"
                      >→</button>
                    </span>
                  </div>
                </article>
              </div>
              <p v-else-if="isEdit" class="image-upload__empty">
                This product has no images. Add one before saving.
              </p>
            </div>
          </div>

          <!-- Status (edit only) -->
          <div v-if="isEdit" class="form-section">
            <h2 class="form-section__title">Status Override</h2>
            <div class="form-group">
              <label for="pf-status" class="form-label">Status</label>
              <select id="pf-status" v-model="form.status" class="form-select">
                <option value="available">Available</option>
                <option value="mine_pending">Mine Pending</option>
                <option value="steal_pending">Steal Pending</option>
                <option value="grab_pending">Grab Pending</option>
                <option value="sold">Sold</option>
              </select>
              <p class="form-hint">⚠ Only change this manually if you know what you're doing.</p>
            </div>
          </div>

          <!-- Submit -->
          <div class="form-actions">
            <RouterLink to="/admin/products" class="btn btn--ghost">Cancel</RouterLink>
            <button type="submit" class="btn btn--primary" :disabled="saving">
              {{ saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Product') }}
            </button>
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

.product-form { display: flex; flex-direction: column; gap: 1.25rem; }
.form-grid { display: grid; grid-template-columns: 1fr 380px; gap: 1.25rem; align-items: start; }
@media (max-width: 900px) { .form-grid { grid-template-columns: 1fr; } }

.form-col { display: flex; flex-direction: column; gap: 1.25rem; }
.form-section {
  padding: 1.75rem;
  background: var(--color-balsamico-light);
  border: 1px solid var(--color-balsamico-border);
  display: flex; flex-direction: column; gap: 1.1rem;
}
.form-section__title {
  font-size: .65rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  color: var(--color-seashell-muted); padding-bottom: .75rem;
  border-bottom: 1px solid var(--color-balsamico-border);
}

.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .875rem; }

.form-label--mine  { color: var(--color-spice-market); }
.form-label--steal { color: var(--color-steal); }
.form-label--grab  { color: var(--color-grab); }
.form-hint { font-size: .72rem; color: var(--color-seashell-muted); margin-top: .375rem; }

.image-upload { display: flex; flex-direction: column; gap: .875rem; }
.image-upload__dropzone {
  position: relative;
  min-height: 150px;
  padding: 1.5rem;
  border: 1px dashed var(--color-spice-border);
  background: var(--color-spice-dim);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  text-align: center;
  color: var(--color-seashell-muted);
  cursor: pointer;
  transition: border-color var(--transition-fast), background var(--transition-fast);
}
.image-upload__dropzone:hover,
.image-upload__dropzone--dragging {
  border-color: var(--color-spice-market);
  background: rgba(186,68,29,.22);
}
.image-upload__dropzone strong {
  color: var(--color-seashell);
  font-size: .82rem;
  letter-spacing: .06em;
  text-transform: uppercase;
}
.image-upload__dropzone span { font-size: .72rem; }
.image-upload__dropzone-mark {
  width: 38px;
  height: 38px;
  border: 1px solid var(--color-spice-market);
  display: grid;
  place-items: center;
  color: var(--color-spice-market) !important;
  font-size: 1.4rem !important;
}
.image-upload__input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.image-upload__hint, .image-upload__empty { color: var(--color-seashell-muted); font-size: .72rem; }
.image-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.image-preview {
  min-width: 0;
  padding: .5rem;
  border: 1px solid var(--color-balsamico-border);
  background: var(--color-balsamico-lighter);
}
.image-preview--primary { border-color: var(--color-spice-market); }
.image-preview__frame { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: var(--color-balsamico); }
.image-preview__frame img { width: 100%; height: 100%; object-fit: cover; }
.image-preview__remove {
  position: absolute;
  top: .375rem;
  right: .375rem;
  width: 28px;
  height: 28px;
  border: 1px solid var(--color-seashell-dim);
  background: rgba(26,15,5,.86);
  color: var(--color-seashell);
  font-size: 1.2rem;
  line-height: 1;
}
.image-preview__remove:hover { border-color: var(--color-spice-market); color: var(--color-spice-market); }
.image-preview__badge {
  position: absolute;
  left: .375rem;
  bottom: .375rem;
  padding: .2rem .45rem;
  background: var(--color-spice-market);
  color: var(--color-seashell);
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
}
.image-preview__name {
  display: block;
  margin-top: .45rem;
  overflow: hidden;
  color: var(--color-seashell-strong);
  font-size: .68rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.image-preview__actions, .image-preview__order { display: flex; align-items: center; }
.image-preview__actions { justify-content: space-between; gap: .35rem; margin-top: .35rem; }
.image-preview__main {
  padding: .25rem .4rem;
  color: var(--color-seashell-muted);
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
}
.image-preview__main:not(:disabled):hover { color: var(--color-spice-market); }
.image-preview__main:disabled { color: var(--color-spice-market); }
.image-preview__order button {
  width: 26px;
  height: 26px;
  border: 1px solid var(--color-balsamico-border);
  color: var(--color-seashell-muted);
}
.image-preview__order button:not(:disabled):hover { border-color: var(--color-spice-market); color: var(--color-spice-market); }
.image-preview__order button:disabled { opacity: .25; cursor: not-allowed; }

.form-actions { display: flex; gap: .75rem; justify-content: flex-end; }
</style>
