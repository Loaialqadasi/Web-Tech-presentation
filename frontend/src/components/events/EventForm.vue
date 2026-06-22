<script setup>
import { computed, reactive, watch } from 'vue'
import { eventSchema, validateSchema } from '../../utils/validators.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  mode: { type: String, default: 'create' },
  eventData: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const form = reactive({
  title: '',
  description: '',
  category: '',
  date: '',
  startTime: '',
  endTime: '',
  venue: '',
  capacity: 100,
  price: 'Free',
  imageUrl: '',
  status: 'open',
})

const errors = reactive({})

const statusOptions = [
  { label: 'Open', value: 'open' },
  { label: 'Closed', value: 'closed' },
  { label: 'Cancelled', value: 'cancelled' },
]

const isEdit = computed(() => props.mode === 'edit')
const dialogTitle = computed(() => (isEdit.value ? 'Edit Event' : 'Create Event'))

function resetErrors() {
  Object.keys(errors).forEach((k) => delete errors[k])
}

function resetForm() {
  form.title = ''
  form.description = ''
  form.category = props.categories[0] && props.categories[0] !== 'All' ? props.categories[0] : ''
  form.date = ''
  form.startTime = '09:00'
  form.endTime = '17:00'
  form.venue = ''
  form.capacity = 100
  form.price = 'Free'
  form.imageUrl = ''
  form.status = 'open'
  resetErrors()
}

function normalizeTime(value = '') {
  if (!value) return ''
  if (/^\d{2}:\d{2}$/.test(value)) return value

  const match = String(value).trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i)
  if (!match) return ''

  let hour = Number(match[1])
  const minute = match[2]
  const period = match[3].toUpperCase()

  if (period === 'PM' && hour < 12) hour += 12
  if (period === 'AM' && hour === 12) hour = 0

  return `${String(hour).padStart(2, '0')}:${minute}`
}

function hydrateForm(data) {
  if (!data) return

  form.title = data.title || ''
  form.description = data.description || ''
  form.category = data.category || ''
  form.date = data.date || ''
  form.startTime = normalizeTime(data.startTime) || '09:00'
  form.endTime = normalizeTime(data.endTime) || '17:00'
  form.venue = data.venue || ''
  form.capacity = Number(data.capacity || 100)
  form.price = data.price || 'Free'
  form.imageUrl = data.imageUrl || data.image || ''
  form.status = data.status || 'open'
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    resetErrors()
    if (isEdit.value && props.eventData) {
      hydrateForm(props.eventData)
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

watch(
  () => props.categories,
  (list) => {
    if (!list?.length) return
    if (!form.category || form.category === 'All') {
      form.category = list.find((c) => c !== 'All') || ''
    }
  },
  { immediate: true }
)

function close() {
  emit('update:modelValue', false)
}

function validate() {
  resetErrors()
  const nextErrors = validateSchema(form, eventSchema)

  if (form.endTime && form.startTime && form.endTime <= form.startTime) {
    nextErrors.endTime = 'End time must be after start time.'
  }

  Object.assign(errors, nextErrors)
  return Object.keys(nextErrors).length === 0
}

function onSubmit() {
  if (!validate()) return

  emit('submit', {
    title: form.title.trim(),
    description: form.description.trim(),
    category: form.category,
    date: form.date,
    startTime: form.startTime,
    endTime: form.endTime,
    venue: form.venue.trim(),
    capacity: Number(form.capacity),
    price: form.price.trim() || 'Free',
    imageUrl: form.imageUrl.trim(),
    status: form.status,
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="event-form-overlay" @click.self="close">
      <div class="event-form-dialog" role="dialog" aria-modal="true" :aria-label="dialogTitle">
        <header class="event-form-header">
          <h2>{{ dialogTitle }}</h2>
          <button type="button" class="icon-btn" @click="close">x</button>
        </header>

        <form class="event-form" @submit.prevent="onSubmit">
          <label>
            <span>Title</span>
            <input v-model="form.title" type="text" maxlength="200" placeholder="Event title" />
            <small v-if="errors.title" class="error">{{ errors.title }}</small>
          </label>

          <label>
            <span>Description</span>
            <textarea v-model="form.description" rows="4" placeholder="Describe the event"></textarea>
            <small v-if="errors.description" class="error">{{ errors.description }}</small>
          </label>

          <div class="grid-2">
            <label>
              <span>Category</span>
              <select v-model="form.category">
                <option v-for="cat in categories.filter((c) => c !== 'All')" :key="cat" :value="cat">{{ cat }}</option>
              </select>
              <small v-if="errors.category" class="error">{{ errors.category }}</small>
            </label>

            <label>
              <span>Status</span>
              <select v-model="form.status">
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </label>
          </div>

          <div class="grid-3">
            <label>
              <span>Date</span>
              <input v-model="form.date" type="date" />
              <small v-if="errors.date" class="error">{{ errors.date }}</small>
            </label>

            <label>
              <span>Start time</span>
              <input v-model="form.startTime" type="time" />
            </label>

            <label>
              <span>End time</span>
              <input v-model="form.endTime" type="time" />
              <small v-if="errors.endTime" class="error">{{ errors.endTime }}</small>
            </label>
          </div>

          <div class="grid-2">
            <label>
              <span>Venue</span>
              <input v-model="form.venue" type="text" maxlength="200" placeholder="Venue" />
              <small v-if="errors.venue" class="error">{{ errors.venue }}</small>
            </label>

            <label>
              <span>Capacity</span>
              <input v-model.number="form.capacity" type="number" min="1" max="10000" />
              <small v-if="errors.capacity" class="error">{{ errors.capacity }}</small>
            </label>
          </div>

          <div class="grid-2">
            <label>
              <span>Price</span>
              <input v-model="form.price" type="text" placeholder="Free or RM 20" />
            </label>

            <label>
              <span>Image URL</span>
              <input v-model="form.imageUrl" type="url" placeholder="https://example.com/image.jpg" />
            </label>
          </div>

          <p v-if="error" class="submit-error">{{ error }}</p>

          <footer class="event-form-footer">
            <button type="button" class="btn btn-ghost" @click="close" :disabled="saving">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : (isEdit ? 'Update Event' : 'Create Event') }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.event-form-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  z-index: 120;
}

.event-form-dialog {
  width: min(820px, 100%);
  max-height: calc(100vh - 48px);
  overflow: auto;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 24px 40px rgba(15, 23, 42, 0.24);
}

.event-form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.event-form-header h2 {
  margin: 0;
  font-size: 20px;
  color: #0f172a;
}

.icon-btn {
  border: 0;
  background: #f8fafc;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  cursor: pointer;
  color: #475569;
}

.event-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

label > span {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

input,
textarea,
select {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 14px;
  color: #0f172a;
  background: #fff;
}

input:focus,
textarea:focus,
select:focus {
  outline: none;
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18);
}

.grid-2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.grid-3 {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.error,
.submit-error {
  color: #dc2626;
  font-size: 12px;
}

.submit-error {
  margin: 0;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 10px 12px;
}

.event-form-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 6px;
}

.btn {
  border: 0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-primary {
  background: #0ea5e9;
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  background: #0284c7;
}

.btn-ghost {
  background: #fff;
  color: #334155;
  border: 1px solid #cbd5e1;
}

@media (max-width: 760px) {
  .grid-2,
  .grid-3 {
    grid-template-columns: 1fr;
  }

  .event-form-overlay {
    padding: 12px;
  }

  .event-form-dialog {
    max-height: calc(100vh - 24px);
  }
}
</style>
