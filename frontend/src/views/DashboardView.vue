<script setup>
/**
 * DashboardView — User Dashboard (Loai's primary deliverable)
 * Owner: Loai AlQadasi (UI/UX Lead) — A23EC9010
 *
 * Authenticated page that summarizes the user's activity across the platform.
 * Combines: profile snapshot, KPI stats, upcoming events, recent notifications,
 * and quick action cards linking to other modules.
 *
 * Design notes:
 *   - Reuses EventCard (compact variant) for upcoming events so the visual
 *     language matches the Gallery.
 *   - StatCard component is the only KPI tile in the entire app — ensures the
 *     same metrics display identically anywhere stats appear.
 *   - All data is fetched via the API service, never directly from localStorage
 *     (localStorage is only a cache for the current user's name/avatar).
 *   - Empty state for "no upcoming events" guides the user to the Gallery.
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

import StatCard         from '../components/shared/StatCard.vue'
import EventCard        from '../components/shared/EventCard.vue'
import EmptyState       from '../components/shared/EmptyState.vue'
import LoadingSpinner   from '../components/shared/LoadingSpinner.vue'

import { fetchDashboard, fetchBookedEvents, markAllNotificationsRead } from '../service/api.js'
import { authState } from '../service/auth.js'
import { relativeDay, formatDateTime } from '../utils/format.js'

const router = useRouter()

// ─── State ────────────────────────────────────────────────────────────────────
const loading      = ref(true)
const error        = ref('')
const dashboard    = ref(null)
const bookedEvents = ref([])

// ─── Data Fetching ───────────────────────────────────────────────────────────
async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    if (!authState.user) throw new Error('Please log in to view your dashboard.')
    const [dash, bookings] = await Promise.all([
      fetchDashboard(authState.user.id),
      fetchBookedEvents(authState.user.id).catch(() => []),
    ])
    dashboard.value = dash
    bookedEvents.value = bookings
  } catch (e) {
    error.value = e.message || 'Failed to load dashboard.'
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

// ─── Derived ─────────────────────────────────────────────────────────────────
const user = computed(() => dashboard.value?.user || authState.user || {})
const stats = computed(() => dashboard.value?.stats || {})
const recentNotifications = computed(() => dashboard.value?.recentNotifications || [])

// Filter upcoming bookings (confirmed/active, in the future)
const upcomingBookings = computed(() => {
  const now = new Date()
  now.setHours(0, 0, 0, 0)
  return bookedEvents.value
    .filter((b) => ['confirmed', 'active'].includes(b.bookingStatus) && new Date(b.event?.date) >= now)
    .sort((a, b) => new Date(a.event?.date) - new Date(b.event?.date))
    .slice(0, 4)
})

const pendingPayments = computed(() =>
  bookedEvents.value.filter((b) => b.bookingStatus === 'pending_payment')
)

// ─── Notifications ──────────────────────────────────────────────────────────
const notifIcon = (type) => {
  switch (type) {
    case 'success': return '✓'
    case 'warning': return '!'
    case 'error':   return '×'
    default:        return 'i'
  }
}

const notifClass = (type) => `notif-item__icon--${type || 'info'}`

async function markAllRead() {
  if (!authState.user) return
  try {
    await markAllNotificationsRead(authState.user.id)
    await loadAll()
  } catch (e) {
    console.error('markAllRead failed', e)
  }
}

// ─── Navigation ─────────────────────────────────────────────────────────────
function goToGallery()     { router.push({ name: 'gallery' }) }
function goToBookings()    { router.push({ name: 'booking-history' }) }
function goToNotifications(){ router.push({ name: 'notifications' }) }
function goToCalendar()    { router.push({ name: 'calendar' }) }
function goToForum()       { router.push({ name: 'forum' }) }
function goToFeedback()    { router.push({ name: 'feedback' }) }
function goToEvent(id)     { router.push({ name: 'event-details', params: { id } }) }
function goToPay(bookingId){ router.push({ name: 'checkout', query: { bookingId } }) }
</script>

<template>
  <div class="dashboard">
    <!-- ─── Header strip with greeting ──────────────────────────────────── -->
    <header class="dashboard__header">
      <div class="dashboard__header-inner">
        <div class="dashboard__avatar" :class="user.avatarColor || 'bg-indigo-500'">
          {{ user.avatar || (user.name ? user.name[0] : '?') }}
        </div>
        <div class="dashboard__header-text">
          <p class="dashboard__greeting">Welcome back,</p>
          <h1 class="dashboard__name">{{ user.name || 'Student' }}</h1>
          <p class="dashboard__role">
            <span class="dashboard__role-badge">{{ user.role || 'student' }}</span>
            <span v-if="user.department" class="dashboard__dept">{{ user.department }}</span>
          </p>
        </div>
        <div class="dashboard__header-actions">
          <button class="btn btn--primary" @click="goToGallery">Browse Events</button>
          <button class="btn btn--ghost" @click="goToBookings">My Bookings</button>
        </div>
      </div>
    </header>

    <main class="dashboard__body">
      <!-- ─── Loading / Error ─────────────────────────────────────────── -->
      <LoadingSpinner v-if="loading" label="Loading your dashboard…" />

      <EmptyState
        v-else-if="error"
        icon="⚠️"
        title="Couldn't load your dashboard"
        :description="error"
      >
        <template #action>
          <button class="btn btn--primary" @click="loadAll">Retry</button>
        </template>
      </EmptyState>

      <template v-else>
        <!-- ─── KPI Stats ─────────────────────────────────────────────── -->
        <section class="dashboard__section">
          <div class="dashboard__stat-grid">
            <StatCard
              label="Total Bookings"
              :value="stats.totalBookings ?? 0"
              :hint="`${stats.confirmedBookings ?? 0} confirmed`"
              color="indigo"
              icon="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V9h14v11z"
            />
            <StatCard
              label="Pending Payments"
              :value="pendingPayments.length"
              :hint="pendingPayments.length ? 'Action required' : 'All settled'"
              color="amber"
              icon="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 5h-2v6h2V7zm0 8h-2v2h2v-2z"
            />
            <StatCard
              label="Unread Notifications"
              :value="stats.unreadNotifications ?? 0"
              :hint="stats.unreadNotifications ? 'New updates' : 'You are all caught up'"
              color="rose"
              icon="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"
            />
            <StatCard
              label="Calendar Events"
              :value="stats.calendarEvents ?? 0"
              :hint="stats.feedbackGiven ? `${stats.feedbackGiven} reviews given` : 'No reviews yet'"
              color="cyan"
              icon="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14v10z"
            />
          </div>
        </section>

        <!-- ─── Pending payment alert ──────────────────────────────────── -->
        <section v-if="pendingPayments.length" class="dashboard__section">
          <div class="alert alert--warning">
            <div class="alert__icon">!</div>
            <div class="alert__body">
              <strong>{{ pendingPayments.length }} booking{{ pendingPayments.length > 1 ? 's' : '' }} awaiting payment.</strong>
              <p>Complete payment to secure your seats.</p>
            </div>
            <button class="btn btn--warning" @click="goToPay(pendingPayments[0].bookingId)">
              Pay Now
            </button>
          </div>
        </section>

        <!-- ─── Two-column layout: upcoming + notifications ─────────────── -->
        <section class="dashboard__section dashboard__grid-2">
          <!-- Upcoming events -->
          <div class="panel">
            <div class="panel__header">
              <h2 class="panel__title">Upcoming Events</h2>
              <button class="panel__link" @click="goToCalendar">View calendar →</button>
            </div>

            <div class="panel__body">
              <EmptyState
                v-if="upcomingBookings.length === 0"
                icon="📅"
                title="No upcoming events"
                description="Browse the event gallery and book your first event."
              >
                <template #action>
                  <button class="btn btn--primary" @click="goToGallery">Browse Events</button>
                </template>
              </EmptyState>

              <ul v-else class="upcoming-list">
                <li v-for="b in upcomingBookings" :key="b.bookingId" class="upcoming-list__item">
                  <EventCard
                    :event="{ ...b.event, image: b.event.image }"
                    variant="compact"
                    @select="() => goToEvent(b.eventId)"
                  />
                  <div class="upcoming-list__meta">
                    <span class="upcoming-list__date">{{ relativeDay(b.event.date) }}</span>
                    <span class="upcoming-list__qty">{{ b.ticketQuantity }} ticket{{ b.ticketQuantity > 1 ? 's' : '' }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <!-- Recent notifications -->
          <div class="panel">
            <div class="panel__header">
              <h2 class="panel__title">Recent Notifications</h2>
              <button
                v-if="stats.unreadNotifications > 0"
                class="panel__link"
                @click="markAllRead"
              >Mark all read</button>
            </div>

            <div class="panel__body">
              <EmptyState
                v-if="recentNotifications.length === 0"
                icon="🔔"
                title="No notifications"
                description="Booking confirmations and event reminders will appear here."
              />

              <ul v-else class="notif-list">
                <li
                  v-for="n in recentNotifications"
                  :key="n.id"
                  class="notif-item"
                  :class="{ 'notif-item--unread': !n.read }"
                >
                  <span class="notif-item__icon" :class="notifClass(n.type)">{{ notifIcon(n.type) }}</span>
                  <div class="notif-item__body">
                    <p class="notif-item__title">{{ n.title }}</p>
                    <p class="notif-item__msg">{{ n.message }}</p>
                    <p class="notif-item__time">{{ formatDateTime(n.createdAt?.split('T')[0], n.createdAt?.split('T')[1]?.split('.')[0] || '') }}</p>
                  </div>
                </li>
              </ul>

              <button v-if="recentNotifications.length > 0" class="btn btn--ghost btn--block" @click="goToNotifications">
                View all notifications
              </button>
            </div>
          </div>
        </section>

        <!-- ─── Quick actions ──────────────────────────────────────────── -->
        <section class="dashboard__section">
          <h2 class="dashboard__section-title">Quick Actions</h2>
          <div class="quick-actions">
            <button class="quick-action" @click="goToGallery">
              <span class="quick-action__icon quick-action__icon--indigo">🔍</span>
              <span class="quick-action__label">Browse Events</span>
            </button>
            <button class="quick-action" @click="goToBookings">
              <span class="quick-action__icon quick-action__icon--emerald">🎟️</span>
              <span class="quick-action__label">My Bookings</span>
            </button>
            <button class="quick-action" @click="goToCalendar">
              <span class="quick-action__icon quick-action__icon--cyan">📅</span>
              <span class="quick-action__label">Calendar</span>
            </button>
            <button class="quick-action" @click="goToForum">
              <span class="quick-action__icon quick-action__icon--purple">💬</span>
              <span class="quick-action__label">Forum</span>
            </button>
            <button class="quick-action" @click="goToFeedback">
              <span class="quick-action__icon quick-action__icon--amber">⭐</span>
              <span class="quick-action__label">Feedback</span>
            </button>
            <button class="quick-action" @click="goToNotifications">
              <span class="quick-action__icon quick-action__icon--rose">🔔</span>
              <span class="quick-action__label">Notifications</span>
            </button>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<style scoped>
.dashboard { display: flex; flex-direction: column; min-height: 100vh; background: #f8fafc; }

/* ─── Header ─── */
.dashboard__header {
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
  color: #fff;
  padding: 32px 24px;
}
.dashboard__header-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  gap: 16px;
  align-items: center;
  flex-wrap: wrap;
}
.dashboard__avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.dashboard__header-text { flex: 1; min-width: 200px; }
.dashboard__greeting { margin: 0; font-size: 13px; opacity: 0.9; }
.dashboard__name { margin: 0; font-size: 26px; font-weight: 700; }
.dashboard__role { margin: 4px 0 0; display: flex; gap: 8px; align-items: center; font-size: 13px; }
.dashboard__role-badge {
  background: rgba(255,255,255,0.18);
  padding: 2px 10px;
  border-radius: 999px;
  text-transform: uppercase;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.dashboard__dept { opacity: 0.85; }
.dashboard__header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ─── Body ─── */
.dashboard__body { padding: 24px; max-width: 1200px; margin: 0 auto; width: 100%; }
.dashboard__section { margin-bottom: 28px; }
.dashboard__section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }

/* ─── Stats grid ─── */
.dashboard__stat-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}
@media (min-width: 768px) {
  .dashboard__stat-grid { grid-template-columns: repeat(4, 1fr); }
}

/* ─── Two-column layout ─── */
.dashboard__grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}
@media (min-width: 900px) {
  .dashboard__grid-2 { grid-template-columns: 1.5fr 1fr; }
}

/* ─── Panel (card container) ─── */
.panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
}
.panel__header {
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.panel__title { margin: 0; font-size: 15px; font-weight: 600; color: #0f172a; }
.panel__link {
  background: none;
  border: 0;
  color: #4f46e5;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}
.panel__link:hover { color: #4338ca; text-decoration: underline; }
.panel__body { padding: 16px 20px; }

/* ─── Upcoming list ─── */
.upcoming-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
.upcoming-list__item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}
.upcoming-list__item:last-child { border-bottom: 0; padding-bottom: 0; }
.upcoming-list__meta {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #64748b;
  padding-left: 68px;
}
.upcoming-list__date { font-weight: 600; color: #4f46e5; }

/* ─── Notifications ─── */
.notif-list { list-style: none; margin: 0 0 12px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.notif-item {
  display: flex;
  gap: 12px;
  padding: 10px;
  border-radius: 8px;
  background: #f8fafc;
}
.notif-item--unread { background: #eef2ff; }
.notif-item__icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
  color: #fff;
}
.notif-item__icon--success { background: #10b981; }
.notif-item__icon--warning { background: #f59e0b; }
.notif-item__icon--error   { background: #ef4444; }
.notif-item__icon--info    { background: #3b82f6; }
.notif-item__body { flex: 1; min-width: 0; }
.notif-item__title { margin: 0 0 2px; font-size: 13px; font-weight: 600; color: #0f172a; }
.notif-item__msg { margin: 0 0 4px; font-size: 12px; color: #64748b; line-height: 1.4; }
.notif-item__time { margin: 0; font-size: 11px; color: #94a3b8; }

/* ─── Alert (pending payments) ─── */
.alert {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 14px 18px;
  border-radius: 12px;
  background: #fef3c7;
  border: 1px solid #fcd34d;
}
.alert--warning { background: #fef3c7; border-color: #fcd34d; }
.alert__icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #f59e0b;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex-shrink: 0;
}
.alert__body { flex: 1; }
.alert__body strong { font-size: 14px; color: #92400e; display: block; }
.alert__body p { margin: 2px 0 0; font-size: 13px; color: #92400e; }

/* ─── Quick actions ─── */
.quick-actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
@media (min-width: 768px) {
  .quick-actions { grid-template-columns: repeat(6, 1fr); }
}
.quick-action {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.quick-action:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
  transform: translateY(-2px);
}
.quick-action__icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}
.quick-action__icon--indigo  { background: #eef2ff; }
.quick-action__icon--emerald { background: #ecfdf5; }
.quick-action__icon--cyan    { background: #ecfeff; }
.quick-action__icon--purple  { background: #f5f3ff; }
.quick-action__icon--amber   { background: #fffbeb; }
.quick-action__icon--rose    { background: #fff1f2; }
.quick-action__label { font-size: 12px; font-weight: 500; color: #475569; text-align: center; }

/* ─── Buttons ─── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 8px;
  border: 0;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
  text-decoration: none;
}
.btn--primary { background: #4f46e5; color: #fff; }
.btn--primary:hover { background: #4338ca; }
.btn--ghost { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
.btn--ghost:hover { background: rgba(255,255,255,0.25); }
.btn--warning { background: #f59e0b; color: #fff; }
.btn--warning:hover { background: #d97706; }
.btn--block { width: 100%; }

/* Override ghost button for use in panels (light bg) */
.panel__body .btn--ghost {
  background: #fff;
  color: #475569;
  border: 1px solid #d1d5db;
}
.panel__body .btn--ghost:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

/* ─── Responsive ─── */
@media (max-width: 640px) {
  .dashboard__header-inner { flex-direction: column; align-items: flex-start; }
  .dashboard__header-actions { width: 100%; }
  .dashboard__header-actions .btn { flex: 1; }
}
</style>
