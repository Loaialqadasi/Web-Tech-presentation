<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { authState } from '../service/auth.js'
import { errMsg, fetchCalendarEvents } from '../service/api.js'
import { relativeDay } from '../utils/format.js'
import GalleryCalendarView from '../components/events/GalleryCalendarView.vue'

const loading = ref(true)
const error = ref('')
const viewMode = ref('month')
const events = ref([])
const monthlyUpcomingEvents = ref([])
const highlightedDates = ref([])

const today = new Date()
const currentMonth = ref(new Date(today.getFullYear(), today.getMonth(), 1))
const selectedDate = ref(new Date(today.getFullYear(), today.getMonth(), today.getDate()))
let refreshHandle = null

const userId = computed(() => authState.user?.id || authState.user?.user_id || null)

const monthLabel = computed(() =>
  currentMonth.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
)

const monthGridDays = computed(() => {
  const start = new Date(currentMonth.value)
  const end = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 0)

  const lead = (start.getDay() + 6) % 7
  const days = []

  for (let i = 0; i < lead; i += 1) {
    const d = new Date(start)
    d.setDate(d.getDate() - (lead - i))
    days.push({ date: d, inMonth: false })
  }

  for (let day = 1; day <= end.getDate(); day += 1) {
    days.push({ date: new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), day), inMonth: true })
  }

  while (days.length % 7 !== 0) {
    const d = new Date(days[days.length - 1].date)
    d.setDate(d.getDate() + 1)
    days.push({ date: d, inMonth: false })
  }

  return days
})

const sortedEvents = computed(() => {
  return [...events.value].sort((a, b) => {
    const da = `${a.date} ${a.startTime || ''}`
    const db = `${b.date} ${b.startTime || ''}`
    return new Date(da).getTime() - new Date(db).getTime()
  })
})

const selectedDateEvents = computed(() => {
  const key = formatDate(selectedDate.value)
  return sortedEvents.value.filter((e) => e.date === key)
})

const upcomingEvents = computed(() => {
  const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime()
  return sortedEvents.value
    .filter((event) => new Date(event.date).getTime() >= startOfToday)
    .slice(0, 5)
})

function formatDate(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function isToday(date) {
  return formatDate(date) === formatDate(today)
}

function isSelected(date) {
  return formatDate(date) === formatDate(selectedDate.value)
}

function eventsOnDate(date) {
  const key = formatDate(date)
  return sortedEvents.value.filter((e) => e.date === key)
}

function monthlyEventsOnDate(date) {
  const key = formatDate(date)
  return monthlyUpcomingEvents.value.filter((e) => e.date === key)
}

function isBookedDate(date) {
  const key = formatDate(date)
  return highlightedDates.value.includes(key)
}

function previousMonth() {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1)
}

function nextMonth() {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1)
}

function jumpToToday() {
  currentMonth.value = new Date(today.getFullYear(), today.getMonth(), 1)
  selectedDate.value = new Date(today.getFullYear(), today.getMonth(), today.getDate())
}

function pickDate(day) {
  selectedDate.value = new Date(day.date)
  if (!day.inMonth) {
    currentMonth.value = new Date(day.date.getFullYear(), day.date.getMonth(), 1)
  }
}

async function loadCalendar() {
  if (!userId.value) return
  if (!events.value.length) loading.value = true
  error.value = ''
  try {
    const payload = await fetchCalendarEvents(userId.value)
    events.value = payload.bookedEvents || payload.events || []
    monthlyUpcomingEvents.value = payload.monthlyCalendarData?.allUpcomingEvents || events.value
    highlightedDates.value = payload.monthlyCalendarData?.highlightedDates || []
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

function handleVisibility() {
  if (document.visibilityState === 'visible') {
    loadCalendar()
  }
}

onMounted(() => {
  loadCalendar()
  refreshHandle = window.setInterval(loadCalendar, 30000)
  document.addEventListener('visibilitychange', handleVisibility)
})

onUnmounted(() => {
  if (refreshHandle) window.clearInterval(refreshHandle)
  document.removeEventListener('visibilitychange', handleVisibility)
})
</script>

<template>
  <section class="calendar-page">
    <header class="calendar-head">
      <div>
        <p class="calendar-kicker"><strong>Schedule Hub</strong></p>
        <h1><strong>Event Calendar</strong></h1>
        <p>Track your booked events in monthly and list views.</p>
      </div>
      <div class="calendar-toggle" role="tablist" aria-label="calendar view mode">
        <button :class="{ active: viewMode === 'month' }" @click="viewMode = 'month'">Month</button>
        <button :class="{ active: viewMode === 'list' }" @click="viewMode = 'list'">List</button>
      </div>
    </header>

    <p v-if="error" class="calendar-error">{{ error }}</p>
    <div v-if="loading" class="calendar-state">Loading calendar...</div>

    <div v-else-if="viewMode === 'month'" class="calendar-month-wrap">
      <article class="month-card">
        <div class="month-toolbar">
          <div class="left-tools">
            <button @click="previousMonth" aria-label="previous month">&lt;</button>
            <button @click="nextMonth" aria-label="next month">&gt;</button>
            <strong>{{ monthLabel }}</strong>
          </div>
          <button class="today-btn" @click="jumpToToday">Today</button>
        </div>

        <div class="month-weekdays">
          <span>Mon</span>
          <span>Tue</span>
          <span>Wed</span>
          <span>Thu</span>
          <span>Fri</span>
          <span>Sat</span>
          <span>Sun</span>
        </div>

        <div class="month-grid">
          <button
            v-for="day in monthGridDays"
            :key="day.date.toISOString()"
            class="day-cell"
            :class="{ muted: !day.inMonth, today: isToday(day.date), selected: isSelected(day.date), booked: isBookedDate(day.date) }"
            @click="pickDate(day)"
          >
            <span>{{ day.date.getDate() }}</span>
            <small v-if="monthlyEventsOnDate(day.date).length">
              {{ eventsOnDate(day.date).length }}/{{ monthlyEventsOnDate(day.date).length }} booked
            </small>
          </button>
        </div>
      </article>

      <aside class="day-panel">
        <h2>
          Events on
          {{ selectedDate.toLocaleDateString(undefined, { month: 'long', day: 'numeric' }) }}
        </h2>
        <div v-if="selectedDateEvents.length === 0" class="empty-day">No events on this date.</div>
        <ul v-else>
          <li v-for="ev in selectedDateEvents" :key="ev.calendarId">
            <strong>{{ ev.title }}</strong>
            <p>{{ ev.startTime || '--' }} - {{ ev.endTime || '--' }}</p>
            <small>{{ ev.venue || 'Venue TBA' }}</small>
          </li>
        </ul>

        <div class="upcoming-panel">
          <div class="upcoming-panel__top">
            <h3>Upcoming Events</h3>
            <span>{{ upcomingEvents.length }}</span>
          </div>
          <div v-if="upcomingEvents.length === 0" class="empty-day">No upcoming events.</div>
          <ul v-else class="upcoming-list">
            <li v-for="ev in upcomingEvents" :key="`upcoming-${ev.calendarId}`">
              <strong>{{ ev.title }}</strong>
              <p>{{ relativeDay(ev.date) }} • {{ ev.startTime || '--' }}</p>
            </li>
          </ul>
        </div>
      </aside>
    </div>

    <div v-else class="list-wrap">
      <article v-if="sortedEvents.length === 0" class="calendar-state">No calendar events yet.</article>
      <article v-for="ev in sortedEvents" :key="ev.calendarId" class="list-item">
        <div class="list-date">
          <span>{{ new Date(ev.date).toLocaleDateString(undefined, { month: 'short' }) }}</span>
          <strong>{{ new Date(ev.date).getDate() }}</strong>
        </div>
        <div class="list-content">
          <h3>{{ ev.title }}</h3>
          <p>{{ ev.startTime || '--' }} - {{ ev.endTime || '--' }}</p>
          <small>{{ ev.venue || 'Venue TBA' }}</small>
        </div>
      </article>
    </div>

    <div v-if="!loading" class="gallery-calendar-wrap">
      <GalleryCalendarView :events="sortedEvents" :initial-month="currentMonth" />
    </div>
  </section>
</template>

<style scoped>
.calendar-page {
  min-height: 100vh;
  padding: 1.5rem 1rem 2rem;
  background:
    radial-gradient(circle at 85% 15%, #bfdbfe 0%, transparent 20%),
    radial-gradient(circle at 5% 85%, #bfdbfe 0%, transparent 28%),
    #f8fafc;
}

.calendar-head {
  max-width: 1120px;
  margin: 0 auto;
  border-radius: 18px;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%);
  color: #f0fdfa;
  padding: 1.2rem;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}

.calendar-kicker {
  margin: 0;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  font-size: 0.78rem;
  color: #000000;
}

h1 {
  margin: 0.2rem 0 0;
  font-size: 1.9rem;
}

.calendar-head p {
  margin: 0.35rem 0 0;
}

.calendar-toggle {
  display: inline-flex;
  background: rgba(15, 23, 42, 0.26);
  border-radius: 999px;
  padding: 0.25rem;
}

.calendar-toggle button,
.month-toolbar button {
  border: 0;
  cursor: pointer;
  font-weight: 700;
}

.calendar-toggle button {
  border-radius: 999px;
  padding: 0.5rem 1rem;
  color: #e2e8f0;
  background: transparent;
}

.calendar-toggle button.active {
  background: #f8fafc;
  color: #0f3f76;
}

.calendar-error,
.calendar-state {
  max-width: 1120px;
  margin: 1rem auto;
  padding: 0.9rem;
  border-radius: 12px;
  background: #fff;
}

.calendar-error {
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.calendar-month-wrap {
  max-width: 1120px;
  margin: 1rem auto 0;
  display: grid;
  grid-template-columns: 1.7fr 1fr;
  gap: 1rem;
}

.month-card,
.day-panel,
.list-item {
  border-radius: 16px;
  border: 1px solid #dbeafe;
  background: #fff;
}

.month-card {
  padding: 1rem;
}

.month-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
}

.left-tools {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.left-tools button,
.today-btn {
  border-radius: 8px;
  background: #e0f2fe;
  color: #075985;
  padding: 0.4rem 0.6rem;
}

.left-tools strong {
  margin-left: 0.45rem;
}

.month-weekdays,
.month-grid {
  margin-top: 0.8rem;
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.45rem;
}

.month-weekdays span {
  text-align: center;
  color: #64748b;
  font-size: 0.82rem;
}

.day-cell {
  min-height: 80px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  padding: 0.45rem;
  cursor: pointer;
}

.day-cell span {
  font-weight: 700;
  color: #0f172a;
}

.day-cell small {
  margin-top: 0.3rem;
  display: block;
  color: #0284c7;
  font-size: 0.72rem;
}

.day-cell.muted {
  background: #f8fafc;
}

.day-cell.muted span {
  color: #94a3b8;
}

.day-cell.today {
  box-shadow: inset 0 0 0 2px #2d14b8;
}

.day-cell.selected {
  background: #ecf8ff;
  border-color: #676ef9;
}

.day-cell.booked {
  box-shadow: inset 0 0 0 2px rgba(79, 70, 229, 0.45);
}

.day-panel {
  padding: 1rem;
}

.day-panel h2 {
  margin: 0 0 0.8rem;
  font-size: 1.05rem;
}

.empty-day {
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  padding: 0.8rem;
  color: #64748b;
}

.day-panel ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.7rem;
}

.day-panel li {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.7rem;
}

.upcoming-panel {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

.upcoming-panel__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.upcoming-panel__top h3 {
  margin: 0;
  font-size: 1rem;
}

.upcoming-panel__top span {
  min-width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border-radius: 999px;
  background: #e5ccfb;
  color: #3a115e;
  font-weight: 700;
}

.upcoming-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.6rem;
}

.upcoming-list li {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.65rem;
}

.upcoming-list p {
  margin: 0.3rem 0 0;
  color: #475569;
}

.day-panel p,
.day-panel small {
  margin: 0.35rem 0 0;
  color: #475569;
}

.list-wrap {
  max-width: 1120px;
  margin: 1rem auto 0;
  display: grid;
  gap: 0.75rem;
}

.gallery-calendar-wrap {
  max-width: 1120px;
  margin: 1rem auto 0;
}

.list-item {
  padding: 0.8rem;
  display: grid;
  grid-template-columns: 68px 1fr;
  gap: 0.8rem;
}

.list-date {
  border-radius: 12px;
  background: #ecfeff;
  display: grid;
  place-items: center;
  padding: 0.35rem;
}

.list-date span {
  font-size: 0.75rem;
  color: #155e75;
  text-transform: uppercase;
}

.list-date strong {
  font-size: 1.4rem;
  color: #0f172a;
}

.list-content h3 {
  margin: 0;
}

.list-content p,
.list-content small {
  margin: 0.3rem 0 0;
  color: #475569;
}

@media (max-width: 900px) {
  .calendar-month-wrap {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .calendar-page {
    padding: 1rem 0.75rem 1.5rem;
  }

  .calendar-head {
    flex-direction: column;
    align-items: stretch;
  }

  .month-grid {
    gap: 0.3rem;
  }

  .day-cell {
    min-height: 70px;
    padding: 0.32rem;
  }
}
</style>
