<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { authState } from '../service/auth.js'
import {
  errMsg,
  fetchCalendarEvents,
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationAsRead,
  syncCalendar as syncCalendarApi,
} from '../service/api.js'
import { formatDate, relativeDay } from '../utils/format.js'

const loading = ref(true)
const busy = ref(false)
const error = ref('')
const syncNotice = ref('')
const notifications = ref([])
const calendarEvents = ref([])
const filter = ref('all')
let refreshHandle = null

const userId = computed(() => authState.user?.id || authState.user?.user_id || null)

const unreadCount = computed(() => notifications.value.filter((n) => !n.read).length)

const filteredNotifications = computed(() => {
  if (filter.value === 'unread') return notifications.value.filter((n) => !n.read)
  if (filter.value === 'read') return notifications.value.filter((n) => n.read)
  return notifications.value
})

const upcomingReminders = computed(() => {
  const today = new Date()
  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime()
  const inFourteenDays = start + (14 * 24 * 60 * 60 * 1000)

  return [...calendarEvents.value]
    .filter((event) => {
      const when = new Date(event.date).getTime()
      return when >= start && when <= inFourteenDays
    })
    .sort((a, b) => new Date(a.date) - new Date(b.date))
    .slice(0, 4)
})

const hasSyncedCalendar = computed(() => calendarEvents.value.length > 0)

const groupedByDay = computed(() => {
  const groups = {}
  for (const item of filteredNotifications.value) {
    const d = item.createdAt ? new Date(item.createdAt) : new Date()
    const key = d.toDateString()
    if (!groups[key]) {
      groups[key] = { label: d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }), items: [] }
    }
    groups[key].items.push(item)
  }
  return Object.values(groups)
})

function iconFor(type) {
  if (type === 'success') return '✓'
  if (type === 'warning') return '!'
  return 'i'
}

function timeAgo(timestamp) {
  if (!timestamp) return 'Just now'
  const seconds = Math.floor((Date.now() - new Date(timestamp).getTime()) / 1000)
  if (seconds < 60) return 'Just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  return `${Math.floor(seconds / 86400)}d ago`
}

async function loadNotifications() {
  if (!userId.value) return
  if (!notifications.value.length) loading.value = true
  error.value = ''
  try {
    const payload = await fetchNotifications(userId.value)
    notifications.value = payload.notifications || []
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

async function loadCalendarEvents() {
  if (!userId.value) return
  try {
    const payload = await fetchCalendarEvents(userId.value)
    calendarEvents.value = payload.bookedEvents || payload.events || []
  } catch (e) {
    error.value = errMsg(e)
  }
}

async function syncCalendar() {
  if (busy.value) return
  busy.value = true
  syncNotice.value = ''
  try {
    const res = await syncCalendarApi()
    syncNotice.value = res.message || 'Calendar synchronized.'
    await loadNotifications()
    await loadCalendarEvents()
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    busy.value = false
  }
}

function handleVisibility() {
  if (document.visibilityState === 'visible') {
    loadNotifications()
    loadCalendarEvents()
  }
}

async function markRead(notification) {
  if (notification.read || busy.value) return
  busy.value = true
  try {
    await markNotificationAsRead(notification.id)
    notification.read = true
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    busy.value = false
  }
}

async function markAllRead() {
  if (!userId.value || busy.value || unreadCount.value === 0) return
  busy.value = true
  try {
    await markAllNotificationsRead(userId.value)
    notifications.value = notifications.value.map((n) => ({ ...n, read: true }))
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  loadNotifications()
  loadCalendarEvents()
  refreshHandle = window.setInterval(() => {
    loadNotifications()
    loadCalendarEvents()
  }, 30000)
  document.addEventListener('visibilitychange', handleVisibility)
})

onUnmounted(() => {
  if (refreshHandle) window.clearInterval(refreshHandle)
  document.removeEventListener('visibilitychange', handleVisibility)
})
</script>

<template>
  <section class="notif-page">
    <div class="notif-shell">
      <div class="notif-heading">
        <h1><strong>Notifications &amp; Calendar</strong></h1>
        <p>Sync your calendar, view event reminders, and manage notifications.</p>
      </div>

      <p v-if="error" class="notif-error">{{ error }}</p>

      <div class="notif-layout">
        <article class="panel panel--main">
          <div class="panel__toolbar">
            <div class="notif-filters">
              <button :class="{ active: filter === 'all' }" @click="filter = 'all'">All</button>
              <button :class="{ active: filter === 'unread' }" @click="filter = 'unread'">Unread</button>
            </div>
            <button class="mark-all" :disabled="busy || unreadCount === 0" @click="markAllRead">
              Mark all read
            </button>
          </div>

          <div v-if="loading" class="notif-state">Loading notifications...</div>
          <div v-else-if="groupedByDay.length === 0" class="notif-state notif-state--dashed">No notifications here.</div>

          <div v-else class="notif-list">
            <article v-for="group in groupedByDay" :key="group.label" class="notif-group">
              <h2>{{ group.label }}</h2>
              <div class="notif-items">
                <div
                  v-for="item in group.items"
                  :key="item.id"
                  class="notif-item"
                  :class="[item.type || 'info', { unread: !item.read }]"
                >
                  <span class="notif-icon">{{ iconFor(item.type) }}</span>
                  <div class="notif-content">
                    <div class="notif-top">
                      <strong>{{ item.title }}</strong>
                      <small>{{ timeAgo(item.createdAt) }}</small>
                    </div>
                    <p>{{ item.message }}</p>
                  </div>
                  <button v-if="!item.read" :disabled="busy" @click="markRead(item)">Mark read</button>
                  <span v-else class="done">Read</span>
                </div>
              </div>
            </article>
          </div>
        </article>

        <aside class="notif-side">
          <article class="panel side-card">
            <div class="side-card__head">
              <span class="side-card__icon">▦</span>
              <div>
                <h2>Personal Calendar</h2>
                <p>{{ hasSyncedCalendar ? `${calendarEvents.length} event${calendarEvents.length === 1 ? '' : 's'} synced` : 'Not synced yet' }}</p>
              </div>
            </div>
            <button class="sync-btn" :disabled="busy" @click="syncCalendar">Sync My Calendar</button>
            <p v-if="syncNotice" class="sync-note">{{ syncNotice }}</p>
          </article>

          <article class="panel side-card">
            <div class="reminders-head">
              <h2>Upcoming Reminders</h2>
              <p>Events within the next 14 days</p>
            </div>
            <div v-if="upcomingReminders.length === 0" class="notif-state notif-state--dashed side-empty">
              Sync your calendar to see reminders.
            </div>
            <div v-else class="reminders-list">
              <article v-for="event in upcomingReminders" :key="event.calendarId" class="reminder-item">
                <strong>{{ event.title }}</strong>
                <p>{{ relativeDay(event.date) }}</p>
                <small>{{ formatDate(event.date) }} · {{ event.startTime || '--' }}</small>
              </article>
            </div>
          </article>
        </aside>
      </div>
    </div>
  </section>
</template>

<style scoped>
.notif-page {
  min-height: 100vh;
  padding: 1rem 1rem 2.5rem;
  background:
    radial-gradient(circle at 0% 0%, rgba(129, 140, 248, 0.14) 0%, transparent 35%),
    radial-gradient(circle at 100% 10%, rgba(191, 219, 254, 0.35) 0%, transparent 38%),
    linear-gradient(180deg, #eef2ff 0%, #f8fafc 46%, #f8fafc 100%);
}

.notif-shell {
  margin: 0 auto;
  max-width: 1040px;
}

.notif-heading {
 max-width: 1120px;
  margin-bottom: 1rem;
  border-radius: 18px;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%);
  color: #f0fdfa;
  padding: 1.2rem;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}

h1 {
  margin: 0;
  font-size: 2rem;
  color: #ffffff;
}

.notif-heading p {
  margin: 0.35rem 0 0;
  color: #f9f9f9;
  font-size: 0.95rem;
}

.notif-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(260px, 0.9fr);
  gap: 0.85rem;
  align-items: start;
}

.panel {
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  box-shadow: 0 10px 30px -28px rgba(15, 23, 42, 0.4);
}

.panel--main {
  padding: 0.85rem;
}

.panel__toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.8rem;
}

.notif-filters {
  display: inline-flex;
  gap: 0.35rem;
  align-items: center;
  background: #f8fafc;
  border-radius: 999px;
  padding: 0.25rem;
  border: 1px solid #e2e8f0;
}

.notif-filters button,
.mark-all,
.notif-item button {
  border: 0;
  cursor: pointer;
  font-weight: 600;
}

.notif-filters button {
  border-radius: 999px;
  background: transparent;
  padding: 0.36rem 0.72rem;
  font-size: 0.78rem;
}

.notif-filters button.active {
  background: #4f46e5;
  color: #fff;
}

.mark-all {
  border-radius: 10px;
  background: #f8fafc;
  color: #64748b;
  border: 1px solid #e2e8f0;
  padding: 0.48rem 0.8rem;
  font-size: 0.78rem;
}

.mark-all:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.notif-error,
.notif-state {
  border-radius: 12px;
  padding: 0.85rem;
  background: #fff;
}

.notif-error {
  color: #dc2626;
  border: 1px solid #fecaca;
  margin-bottom: 0.8rem;
}

.notif-state {
  color: #64748b;
  text-align: center;
}

.notif-state--dashed {
  border: 1px dashed #cbd5e1;
  background: #f8fafc;
}

.notif-list {
  display: grid;
  gap: 1rem;
}

.notif-group {
  background: transparent;
}

.notif-group h2 {
  margin: 0 0 0.8rem;
  color: #334155;
  font-size: 1rem;
}

.notif-items {
  display: grid;
  gap: 0.75rem;
}

.notif-item {
  display: grid;
  grid-template-columns: 34px 1fr auto;
  gap: 0.7rem;
  align-items: start;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 0.75rem;
  background: #fff;
}

.notif-item.unread {
  border-color: #c7d2fe;
  box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.12);
}

.notif-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-weight: 800;
  color: #0f172a;
  background: #e2e8f0;
}

.notif-item.success .notif-icon {
  background: #dcfce7;
  color: #166534;
}

.notif-item.warning .notif-icon {
  background: #fef9c3;
  color: #854d0e;
}

.notif-item.info .notif-icon {
  background: #dbeafe;
  color: #1d4ed8;
}

.notif-content {
  min-width: 0;
}

.notif-top {
  display: flex;
  justify-content: space-between;
  gap: 0.6rem;
}

.notif-top small {
  color: #64748b;
  white-space: nowrap;
}

.notif-content p {
  margin: 0.35rem 0 0;
  color: #475569;
}

.notif-item button {
  border-radius: 8px;
  padding: 0.45rem 0.7rem;
  background: #eef2ff;
  color: #4338ca;
}

.done {
  color: #10b981;
  font-weight: 700;
  padding-top: 0.2rem;
}

.notif-side {
  display: grid;
  gap: 0.75rem;
}

.side-card {
  padding: 0.85rem;
}

.side-card__head {
  display: flex;
  gap: 0.7rem;
  align-items: flex-start;
}

.side-card__icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  background: #eef2ff;
  color: #4f46e5;
  font-size: 0.95rem;
  font-weight: 700;
}

.side-card h2,
.reminders-head h2 {
  margin: 0;
  font-size: 0.98rem;
  color: #0f172a;
}

.side-card p,
.reminders-head p {
  margin: 0.18rem 0 0;
  color: #94a3b8;
  font-size: 0.78rem;
}

.sync-btn {
  width: 100%;
  margin-top: 0.9rem;
  border: 0;
  border-radius: 10px;
  padding: 0.72rem 0.9rem;
  background: linear-gradient(135deg, #4f46e5, #4338ca);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.sync-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.sync-note {
  margin: 0.55rem 0 0;
  color: #475569;
  font-size: 0.78rem;
}

.reminders-head {
  margin-bottom: 0.75rem;
}

.side-empty {
  font-size: 0.84rem;
}

.reminders-list {
  display: grid;
  gap: 0.65rem;
}

.reminder-item {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 0.72rem;
  background: #fff;
}

.reminder-item p,
.reminder-item small {
  display: block;
  margin: 0.28rem 0 0;
  color: #64748b;
}

@media (max-width: 760px) {
  .notif-page {
    padding: 1rem 0.75rem 1.5rem;
  }

  .notif-layout,
  .panel__toolbar {
    grid-template-columns: 1fr;
    flex-direction: column;
    align-items: stretch;
  }

  .notif-layout {
    display: grid;
    grid-template-columns: 1fr;
  }

  .notif-item {
    grid-template-columns: 30px 1fr;
  }

  .notif-item button,
  .done {
    grid-column: 2;
    justify-self: start;
  }
}
</style>
