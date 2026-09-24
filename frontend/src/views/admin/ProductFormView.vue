<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { productService } from '@/services/product.service'

const route  = useRoute()
const router = useRouter()

const isEdit  = computed(() => !!route.params.id)
const loading = ref(false)
const saving  = ref(false)
const error   = ref('')
const fieldErrors     = ref<Record<string, string>>({})
const imageFile       = ref<File | null>(null)
const imagePreview    = ref<string | null>(null)
const uploadingImage  = ref(false)

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
    if (p.image_url) imagePreview.value = p.image_url
  } catch { error.value = 'Failed to load product.' }
  finally { loading.value = false }
})

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  imageFile.value    = file
  imagePreview.value = URL.createObjectURL(file)
}

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
    let product
    if (isEdit.value) { product = await productService.adminUpdate(productId.value, payload) }
    else { product = await productService.adminCreate(payload) }

    if (imageFile.value && product?.id) {
      uploadingImage.value = true
      await productService.adminUploadImage(product.id, imageFile.value)
      uploadingImage.value = false
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

          <!-- Image -->
          <div class="form-section">
            <h2 class="form-section__title">Product Image</h2>
            <div class="image-upload">
              <div class="image-upload__preview">
                <img v-if="imagePreview" :src="imagePreview" alt="Preview" />
                <div v-else class="image-upload__placeholder">No image</div>
              </div>
              <label class="btn btn--ghost btn--sm image-upload__label">
                Choose Image
                <input type="file" accept="image/jpeg,image/png,image/webp" class="image-upload__input" @change="onFileChange" />
              </label>
              <p class="image-upload__hint">JPG, PNG or WebP — max 4 MB</p>
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
              {{ saving ? (uploadingImage ? 'Uploading…' : 'Saving…') : (isEdit ? 'Save Changes' : 'Create Product') }}
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
.image-upload__preview {
  width: 100%; aspect-ratio: 4/3;
  background: var(--color-balsamico-lighter);
  border: 1px solid var(--color-balsamico-border);
  overflow: hidden; display: flex; align-items: center; justify-content: center;
}
.image-upload__preview img { width: 100%; height: 100%; object-fit: cover; }
.image-upload__placeholder { color: var(--color-seashell-muted); font-size: .82rem; }
.image-upload__label { cursor: pointer; position: relative; display: inline-flex; align-self: flex-start; }
.image-upload__input { display: none; }
.image-upload__hint { font-size: .72rem; color: var(--color-seashell-muted); }

.form-actions { display: flex; gap: .75rem; justify-content: flex-end; }
</style>
