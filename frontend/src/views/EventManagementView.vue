<script setup>
import { computed, onMounted, ref } from 'vue'

import EventCard from '../components/shared/EventCard.vue'
import LoadingSpinner from '../components/shared/LoadingSpinner.vue'
import EmptyState from '../components/shared/EmptyState.vue'
import SearchBar from '../components/shared/SearchBar.vue'
import CategoryFilter from '../components/shared/CategoryFilter.vue'
import EventForm from '../components/events/EventForm.vue'
import EventDetailModal from '../components/events/EventDetailModal.vue'

import {
  createEventAgendaItem,
  createEvent,
  deleteEventAgendaItem,
  deleteEvent,
  errMsg,
  fetchEventAgenda,
  fetchEventById,
  fetchEventCategories,
  fetchEvents,
  updateEventAgendaItem,
  updateEvent,
} from '../service/api.js'

const loading = ref(true)
const actionLoading = ref(false)
const events = ref([])
const categories = ref(['All'])
const error = ref('')
const actionError = ref('')

const search = ref('')
const activeCategory = ref('All')
const statusFilter = ref('')

const showForm = ref(false)
const formMode = ref('create')
const editingEvent = ref(null)

const showDetail = ref(false)
const detailEvent = ref(null)

const agendaLoading = ref(false)
const agendaError = ref('')
const agendaEvent = ref(null)
const agendaItems = ref([])
const editingAgendaId = ref(null)
const agendaForm = ref({
  title: '',
  description: '',
  startTime: '',
  endTime: '',
})
const agendaManagerRef = ref(null)

const statusOptions = [
  { label: 'All statuses', value: '' },
  { label: 'Open', value: 'open' },
  { label: 'Closed', value: 'closed' },
  { label: 'Cancelled', value: 'cancelled' },
]

const organizerCount = computed(() => new Set(events.value.map((e) => e.organizerId)).size)

async function loadCategories() {
  try {
    const list = await fetchEventCategories()
    categories.value = Array.isArray(list) && list.length ? list : ['All']
  } catch {
    categories.value = ['All']
  }
}

async function loadEvents() {
  loading.value = true
  error.value = ''

  try {
    events.value = await fetchEvents({
      search: search.value,
      category: activeCategory.value,
      status: statusFilter.value,
    })
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

async function init() {
  await Promise.all([loadCategories(), loadEvents()])
}

onMounted(init)

function applyFilters() {
  loadEvents()
}

function clearFilters() {
  search.value = ''
  activeCategory.value = 'All'
  statusFilter.value = ''
  loadEvents()
}

function openCreateModal() {
  actionError.value = ''
  formMode.value = 'create'
  editingEvent.value = null
  showForm.value = true
}

async function openEditModal(eventRow) {
  actionError.value = ''
  actionLoading.value = true
  formMode.value = 'edit'
  try {
    editingEvent.value = await fetchEventById(eventRow.id)
    showForm.value = true
  } catch (e) {
    actionError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

async function submitForm(payload) {
  actionError.value = ''
  actionLoading.value = true

  try {
    if (formMode.value === 'create') {
      await createEvent(payload)
    } else {
      await updateEvent(editingEvent.value.id, payload)
    }
    showForm.value = false
    await loadEvents()
  } catch (e) {
    actionError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

async function viewDetails(eventRow) {
  actionError.value = ''
  actionLoading.value = true
  try {
    detailEvent.value = await fetchEventById(eventRow.id)
    showDetail.value = true
  } catch (e) {
    actionError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

async function removeEvent(eventRow) {
  const ok = window.confirm(`Delete "${eventRow.title}"? This cannot be undone.`)
  if (!ok) return

  actionError.value = ''
  actionLoading.value = true

  try {
    await deleteEvent(eventRow.id)
    await loadEvents()
  } catch (e) {
    actionError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

function resetAgendaForm() {
  editingAgendaId.value = null
  agendaForm.value = {
    title: '',
    description: '',
    startTime: '',
    endTime: '',
  }
}

async function openAgendaManager(eventRow) {
  agendaEvent.value = eventRow
  agendaError.value = ''
  agendaLoading.value = true
  resetAgendaForm()
  try {
    agendaItems.value = await fetchEventAgenda(eventRow.id)
  } catch (e) {
    agendaError.value = errMsg(e)
  } finally {
    agendaLoading.value = false
    requestAnimationFrame(() => {
      agendaManagerRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  }
}

function editAgendaItem(item) {
  editingAgendaId.value = item.agendaId
  agendaForm.value = {
    title: item.title,
    description: item.description || '',
    startTime: item.startTime,
    endTime: item.endTime,
  }
}

async function submitAgendaItem() {
  if (!agendaEvent.value) return
  agendaError.value = ''
  agendaLoading.value = true
  try {
    if (editingAgendaId.value) {
      await updateEventAgendaItem(agendaEvent.value.id, editingAgendaId.value, agendaForm.value)
    } else {
      await createEventAgendaItem(agendaEvent.value.id, agendaForm.value)
    }
    agendaItems.value = await fetchEventAgenda(agendaEvent.value.id)
    resetAgendaForm()
  } catch (e) {
    agendaError.value = errMsg(e)
  } finally {
    agendaLoading.value = false
  }
}

async function removeAgendaItem(item) {
  if (!agendaEvent.value) return
  const ok = window.confirm(`Delete agenda item "${item.title}"?`)
  if (!ok) return

  agendaError.value = ''
  agendaLoading.value = true
  try {
    await deleteEventAgendaItem(agendaEvent.value.id, item.agendaId)
    agendaItems.value = await fetchEventAgenda(agendaEvent.value.id)
    if (editingAgendaId.value === item.agendaId) resetAgendaForm()
  } catch (e) {
    agendaError.value = errMsg(e)
  } finally {
    agendaLoading.value = false
  }
}
</script>

<template>
  <div class="manage-events">
    <header class="manage-events__hero">
      <div>
        <p class="manage-events__eyebrow">Organizer and Admin Console</p>
        <h1>Event Management</h1>
        <p>Create, update, and monitor event lifecycle with real-time feedback metrics.</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">Create Event</button>
    </header>

    <section class="manage-events__stats">
      <article>
        <h3>{{ events.length }}</h3>
        <p>Events listed</p>
      </article>
      <article>
        <h3>{{ events.filter((e) => e.status === 'open').length }}</h3>
        <p>Open events</p>
      </article>
      <article>
        <h3>{{ events.reduce((n, e) => n + Number(e.attendees || 0), 0) }}</h3>
        <p>Total attendees</p>
      </article>
      <article>
        <h3>{{ organizerCount }}</h3>
        <p>Organizers</p>
      </article>
    </section>

    <section class="manage-events__filters">
      <div class="manage-events__search">
        <SearchBar v-model="search" placeholder="Search title, description, or venue" />
      </div>

      <div class="manage-events__filter-row">
        <CategoryFilter v-model="activeCategory" :categories="categories" />
        <select v-model="statusFilter" class="status-select">
          <option v-for="option in statusOptions" :key="option.value || 'all'" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <button class="btn btn-primary" @click="applyFilters">Apply</button>
        <button class="btn btn-ghost" @click="clearFilters">Clear</button>
      </div>
    </section>

    <p v-if="actionError" class="error-banner">{{ actionError }}</p>

    <section class="manage-events__content">
      <LoadingSpinner v-if="loading || actionLoading" label="Loading events..." />

      <EmptyState
        v-else-if="error"
        icon="⚠️"
        title="Failed to load events"
        :description="error"
      >
        <template #action>
          <button class="btn btn-primary" @click="loadEvents">Retry</button>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="events.length === 0"
        icon="🗂️"
        title="No events found"
        description="Try changing filters or create your first event."
      >
        <template #action>
          <button class="btn btn-primary" @click="openCreateModal">Create Event</button>
        </template>
      </EmptyState>

      <ul v-else class="event-list">
        <li v-for="eventRow in events" :key="eventRow.id">
          <div class="event-item">
            <EventCard :event="eventRow" />
            <div class="event-item__actions">
              <button type="button" class="btn btn-ghost" @click="viewDetails(eventRow)">Details</button>
              <button type="button" class="btn btn-ghost" @click="openAgendaManager(eventRow)">Agenda</button>
              <button type="button" class="btn btn-ghost" @click="openEditModal(eventRow)">Edit</button>
              <button type="button" class="btn btn-danger" @click="removeEvent(eventRow)">Delete</button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <section v-if="agendaEvent" ref="agendaManagerRef" class="agenda-manager">
      <header class="agenda-manager__head">
        <h2>Event Agenda</h2>
        <p>Managing agenda for <strong>{{ agendaEvent.title }}</strong></p>
      </header>

      <p v-if="agendaError" class="error-banner">{{ agendaError }}</p>

      <div class="agenda-manager__grid">
        <form class="agenda-form" @submit.prevent="submitAgendaItem">
          <h3>{{ editingAgendaId ? 'Edit Agenda Item' : 'Add Agenda Item' }}</h3>
          <label>
            Title
            <input v-model="agendaForm.title" required maxlength="200" />
          </label>
          <label>
            Description
            <textarea v-model="agendaForm.description" rows="3"></textarea>
          </label>
          <div class="time-row">
            <label>
              Start Time
              <input v-model="agendaForm.startTime" required placeholder="09:00 AM" />
            </label>
            <label>
              End Time
              <input v-model="agendaForm.endTime" required placeholder="10:00 AM" />
            </label>
          </div>
          <div class="agenda-form__actions">
            <button class="btn btn-primary" type="submit" :disabled="agendaLoading">
              {{ editingAgendaId ? 'Update Item' : 'Add Item' }}
            </button>
            <button class="btn btn-ghost" type="button" @click="resetAgendaForm">Clear</button>
          </div>
        </form>

        <div class="agenda-listing">
          <LoadingSpinner v-if="agendaLoading" label="Loading agenda..." />
          <EmptyState
            v-else-if="agendaItems.length === 0"
            icon="🗓️"
            title="No agenda items"
            description="Add the first agenda item for this event."
          />
          <ul v-else>
            <li v-for="item in agendaItems" :key="item.agendaId" class="agenda-item">
              <div>
                <p class="agenda-item__time">{{ item.startTime }} - {{ item.endTime }}</p>
                <h4>{{ item.title }}</h4>
                <p class="agenda-item__desc">{{ item.description }}</p>
              </div>
              <div class="agenda-item__actions">
                <button type="button" class="btn btn-ghost" @click="editAgendaItem(item)">Edit</button>
                <button type="button" class="btn btn-danger" @click="removeAgendaItem(item)">Delete</button>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <EventForm
      v-model="showForm"
      :mode="formMode"
      :event-data="editingEvent"
      :categories="categories"
      :saving="actionLoading"
      :error="actionError"
      @submit="submitForm"
    />

    <EventDetailModal
      v-model="showDetail"
      :event="detailEvent"
    />
  </div>
</template>

<style scoped>
.manage-events {
  max-width: 1240px;
  margin: 0 auto;
  padding: 28px 20px 64px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.manage-events__hero {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 12px;
  padding: 20px;
  border-radius: 14px;
  background: linear-gradient(125deg, #0f172a, #0f766e);
  color: #fff;
}

.manage-events__eyebrow {
  margin: 0 0 8px;
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 700;
  opacity: 0.85;
}

.manage-events__hero h1 {
  margin: 0;
  font-size: clamp(26px, 4vw, 36px);
}

.manage-events__hero p {
  margin: 6px 0 0;
  color: #dbeafe;
}

.manage-events__stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.manage-events__stats article {
  background: #fff;
  border: 1px solid #dbe4ee;
  border-radius: 12px;
  padding: 14px;
}

.manage-events__stats h3 {
  margin: 0;
  font-size: 24px;
  color: #0f172a;
}

.manage-events__stats p {
  margin: 3px 0 0;
  color: #475569;
  font-size: 13px;
}

.manage-events__filters {
  background: #fff;
  border: 1px solid #dbe4ee;
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.manage-events__search {
  width: 100%;
}

.manage-events__filter-row {
  display: grid;
  grid-template-columns: 1fr 180px auto auto;
  gap: 10px;
  align-items: center;
}

.status-select {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px;
  color: #0f172a;
  background: #fff;
}

.manage-events__content {
  background: #fff;
  border: 1px solid #dbe4ee;
  border-radius: 12px;
  padding: 14px;
}

.event-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 14px;
}

.event-item {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: stretch;
}

.event-item__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  justify-content: center;
  position: relative;
  z-index: 2;
}

.event-item :deep(.event-card) {
  position: relative;
  z-index: 1;
}

.btn {
  border: 0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary {
  background: #0284c7;
  color: #fff;
}

.btn-primary:hover {
  background: #0369a1;
}

.btn-ghost {
  background: #fff;
  color: #334155;
  border: 1px solid #cbd5e1;
}

.btn-danger {
  background: #dc2626;
  color: #fff;
}

.btn-danger:hover {
  background: #b91c1c;
}

.error-banner {
  margin: 0;
  color: #b91c1c;
  border: 1px solid #fecaca;
  background: #fef2f2;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 13px;
}

.agenda-manager {
  background: #fff;
  border: 1px solid #dbe4ee;
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  scroll-margin-top: 96px;
}

.agenda-manager__head h2 {
  margin: 0;
  color: #0f172a;
}

.agenda-manager__head p {
  margin: 4px 0 0;
  color: #64748b;
}

.agenda-manager__grid {
  display: grid;
  grid-template-columns: minmax(0, 340px) 1fr;
  gap: 12px;
}

.agenda-form {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.agenda-form h3 {
  margin: 0 0 4px;
  color: #0f172a;
}

.agenda-form label {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 13px;
  color: #475569;
}

.agenda-form input,
.agenda-form textarea {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 9px;
  font: inherit;
}

.time-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.agenda-form__actions {
  display: flex;
  gap: 8px;
}

.agenda-listing ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.agenda-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.agenda-item__time {
  margin: 0;
  color: #4338ca;
  font-size: 12px;
  font-weight: 700;
}

.agenda-item h4 {
  margin: 4px 0 0;
  color: #0f172a;
}

.agenda-item__desc {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 13px;
}

.agenda-item__actions {
  display: flex;
  gap: 6px;
}

@media (max-width: 980px) {
  .manage-events__stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .manage-events__filter-row {
    grid-template-columns: 1fr;
  }

  .event-item {
    grid-template-columns: 1fr;
  }

  .agenda-manager__grid {
    grid-template-columns: 1fr;
  }

  .time-row {
    grid-template-columns: 1fr;
  }

  .event-item__actions {
    flex-direction: row;
    justify-content: flex-start;
  }
}

@media (max-width: 640px) {
  .manage-events {
    padding: 16px 12px 40px;
  }

  .manage-events__hero {
    flex-direction: column;
    align-items: flex-start;
  }

  .manage-events__stats {
    grid-template-columns: 1fr;
  }
}
</style>
