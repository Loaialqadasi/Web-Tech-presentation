<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import LoadingSpinner from '../components/shared/LoadingSpinner.vue'
import EmptyState from '../components/shared/EmptyState.vue'
import BookingStepper from '../components/bookings/BookingStepper.vue'
import { fetchEventById } from '../service/api.js'
import { authState } from '../service/auth.js'
import { formatDate, parsePrice, categoryColor } from '../utils/format.js'
import { bookingStore } from '../service/bookingStore.js'


const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const event = ref(null)
const quantity = ref(1)

const serviceFee = 1.5

const attendees = computed(() => {
  if (!event.value) return 0
  if (typeof event.value.attendees === 'number') return event.value.attendees
  return Number(event.value.capacity || 0) - Number(event.value.availableSeats || 0)
})

const avgRating = computed(() => Number(event.value?.avgRating || 0).toFixed(1))
const reviewCount = computed(() => Number(event.value?.reviewCount || 0))
const maxTickets = computed(() => Math.min(5, Math.max(1, Number(event.value?.availableSeats || 1))))

const ticketPrice = computed(() => parsePrice(event.value?.price))
const subtotal = computed(() => quantity.value * ticketPrice.value)
const total = computed(() => subtotal.value + (ticketPrice.value > 0 ? serviceFee : 0))

const organizerName = computed(() => {
  if (!event.value) return 'Campus Organizer'
  if (event.value.organizerName) return event.value.organizerName
  if (event.value.organizerId) return `Organizer #${event.value.organizerId}`
  return 'Campus Organizer'
})

const categoryStyle = computed(() => categoryColor(event.value?.category || 'All'))

const steps = [
  { id: 1, label: 'Event' },
  { id: 2, label: 'Tickets' },
  { id: 3, label: 'Review' },
  { id: 4, label: 'Payment' },
  { id: 5, label: 'Done' },
]

const activeStep = computed(() => 2)
const isLoggedIn = computed(() => Boolean(authState.user))
const canBook = computed(() => authState.user?.role === 'student')

const heroBackground = computed(() => {
  const raw = event.value?.imageUrl || event.value?.image
  if (raw) return `url('${raw}') center/cover no-repeat`

  const map = {
    Technology: `url('https://picsum.photos/seed/unievent-technology-hero/1400/700') center/cover no-repeat`,
    Career: `url('https://picsum.photos/seed/unievent-career-hero/1400/700') center/cover no-repeat`,
    Academic: `url('https://picsum.photos/seed/unievent-academic-hero/1400/700') center/cover no-repeat`,
    Workshop: `url('https://picsum.photos/seed/unievent-workshop-hero/1400/700') center/cover no-repeat`,
    Seminar: `url('https://picsum.photos/seed/unievent-seminar-hero/1400/700') center/cover no-repeat`,
    Sports: `url('https://picsum.photos/seed/unievent-sports-hero/1400/700') center/cover no-repeat`,
    Cultural: `url('https://picsum.photos/seed/unievent-cultural-hero/1400/700') center/cover no-repeat`,
    'Community Service': `url('https://picsum.photos/seed/unievent-community-hero/1400/700') center/cover no-repeat`,
    Arts: `url('https://picsum.photos/seed/unievent-arts-hero/1400/700') center/cover no-repeat`,
    Entertainment: `url('https://picsum.photos/seed/unievent-entertainment-hero/1400/700') center/cover no-repeat`,
  }

  return map[event.value?.category] || map.Technology
})

const agendaItems = computed(() => {
  if (!event.value) return []

  if (Array.isArray(event.value.agenda) && event.value.agenda.length > 0) {
    return [...event.value.agenda]
      .sort((a, b) => String(a.startTime || '').localeCompare(String(b.startTime || '')))
      .map((item) => ({
        time: `${item.startTime || ''}${item.endTime ? ` - ${item.endTime}` : ''}`.trim(),
        title: item.title,
        description: item.description || '',
      }))
  }

  const templateByCategory = {
    Technology: ['Registration & Networking', 'Keynote Session', 'Panel Discussion', 'Q&A and Closing'],
    Career: ['Check-in and Booth Tour', 'Career Talk', 'Recruiter Sessions', 'Closing & Next Steps'],
    Academic: ['Opening Remarks', 'Research Presentations', 'Breakout Session', 'Closing Summary'],
    Workshop: ['Registration', 'Hands-on Session 1', 'Hands-on Session 2', 'Wrap-up'],
    Seminar: ['Registration', 'Main Seminar', 'Audience Q&A', 'Closing'],
    Sports: ['Team Check-in', 'Warm-up', 'Main Competition', 'Prize Ceremony'],
    Cultural: ['Opening Performance', 'Cultural Showcase', 'Interactive Session', 'Closing Performance'],
    'Community Service': ['Volunteer Briefing', 'Community Activity', 'Reflection Session', 'Closing'],
    Arts: ['Registration', 'Main Showcase', 'Artist Talk', 'Closing'],
    Entertainment: ['Gates Open', 'Main Show', 'Featured Segment', 'Finale'],
  }

  const titles = templateByCategory[event.value.category] || ['Check-in', 'Main Session', 'Activity Segment', 'Closing']

  const start = parseToMinutes(event.value.startTime || '09:00')
  const end = parseToMinutes(event.value.endTime || '17:00')
  const slot = Math.max(30, Math.floor((end - start) / titles.length) || 60)

  return titles.map((title, index) => ({
    time: formatMinutes(start + index * slot),
    title,
  }))
})

function parseToMinutes(raw) {
  if (!raw) return 9 * 60

  if (/^\d{2}:\d{2}$/.test(raw)) {
    const [h, m] = raw.split(':').map(Number)
    return h * 60 + m
  }

  const match = String(raw).match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i)
  if (!match) return 9 * 60

  let h = Number(match[1])
  const m = Number(match[2])
  const period = match[3].toUpperCase()

  if (period === 'PM' && h < 12) h += 12
  if (period === 'AM' && h === 12) h = 0

  return h * 60 + m
}

function formatMinutes(total) {
  const hours24 = Math.floor(total / 60)
  const minutes = total % 60
  const period = hours24 >= 12 ? 'PM' : 'AM'
  let hours12 = hours24 % 12
  if (hours12 === 0) hours12 = 12

  return `${String(hours12).padStart(2, '0')}:${String(minutes).padStart(2, '0')} ${period}`
}

// ─── Demo data fallback (matches GalleryView DEMO_EVENTS) ──────────────────
const DEMO_EVENTS = [
  { id: 1, title: 'Tech Innovation Summit 2025', category: 'Technology', date: '2025-07-15', time: '09:00 AM', startTime: '09:00', endTime: '17:00', venue: 'UTM Innovation Hall', description: 'A full-day summit featuring keynote speakers, panel discussions, and hands-on workshops on AI, cloud computing, and cybersecurity. Network with industry leaders and fellow students.', price: 'RM 25.00', availableSeats: 120, capacity: 200, attendees: 80, status: 'upcoming', organizerId: 1, avgRating: 4.5, reviewCount: 12 },
  { id: 2, title: 'Career Fair: Future Pathways', category: 'Career', date: '2025-07-20', time: '10:00 AM', startTime: '10:00', endTime: '16:00', venue: 'UTM Main Auditorium', description: 'Meet recruiters from top companies across Malaysia and beyond. Bring your resume and prepare for on-the-spot interviews. Open to all UTM students and alumni.', price: 'Free', availableSeats: 300, capacity: 300, attendees: 0, status: 'upcoming', organizerId: 1, avgRating: 4.2, reviewCount: 8 },
  { id: 3, title: 'Music Festival: Campus Beats', category: 'Entertainment', date: '2025-08-01', time: '06:00 PM', startTime: '18:00', endTime: '22:00', venue: 'UTM Open Stage', description: 'Live performances by student bands and guest DJs. Food trucks, games, and a vibrant atmosphere to kick off the semester. Tickets are limited!', price: 'RM 15.00', availableSeats: 50, capacity: 500, attendees: 450, status: 'upcoming', organizerId: 2, avgRating: 4.8, reviewCount: 25 },
  { id: 4, title: 'Academic Research Symposium', category: 'Academic', date: '2025-08-10', time: '08:30 AM', startTime: '08:30', endTime: '13:00', venue: 'FKT Building, Room 201', description: 'Postgraduate students present their research findings. Open to all faculty members and undergraduate students interested in research opportunities.', price: 'Free', availableSeats: 80, capacity: 100, attendees: 20, status: 'upcoming', organizerId: 2, avgRating: 4.0, reviewCount: 5 },
  { id: 5, title: 'Inter-Faculty Sports Tournament', category: 'Sports', date: '2025-08-15', time: '08:00 AM', startTime: '08:00', endTime: '18:00', venue: 'UTM Sports Complex', description: 'Compete in futsal, badminton, basketball, and more. Form your faculty team and register before the deadline. Prizes for winners!', price: 'RM 10.00', availableSeats: 200, capacity: 200, attendees: 0, status: 'upcoming', organizerId: 1, avgRating: 4.3, reviewCount: 10 },
  { id: 6, title: 'Art & Design Exhibition', category: 'Arts', date: '2025-08-20', time: '10:00 AM', startTime: '10:00', endTime: '17:00', venue: 'UTM Gallery Wing', description: 'Showcasing final-year projects from the Creative Media department. Explore interactive installations, digital art, and traditional media.', price: 'Free', availableSeats: 150, capacity: 150, attendees: 0, status: 'upcoming', organizerId: 2, avgRating: 4.6, reviewCount: 7 },
  { id: 7, title: 'Web Development Workshop', category: 'Workshop', date: '2025-09-05', time: '09:00 AM', startTime: '09:00', endTime: '12:00', venue: 'Computer Lab 3, FC Building', description: 'Hands-on workshop covering Vue.js, REST APIs, and deployment. Bring your laptop and build a full-stack mini project from scratch.', price: 'RM 5.00', availableSeats: 5, capacity: 40, attendees: 35, status: 'upcoming', organizerId: 1, avgRating: 4.7, reviewCount: 18 },
  { id: 8, title: 'Entrepreneurship Seminar', category: 'Seminar', date: '2025-09-12', time: '02:00 PM', startTime: '14:00', endTime: '17:00', venue: 'UTM Business School, Hall A', description: 'Learn from successful entrepreneurs and startup founders. Topics include ideation, funding, and scaling your business in Southeast Asia.', price: 'Free', availableSeats: 0, capacity: 80, attendees: 80, status: 'upcoming', organizerId: 2, avgRating: 4.1, reviewCount: 3 },
]

async function loadEvent() {
  loading.value = true
  error.value = ''

  try {
    const id = Number(route.params.id)
    if (!Number.isInteger(id) || id < 1) {
      throw new Error('Invalid event id.')
    }
    try {
      event.value = await fetchEventById(id)
    } catch (apiErr) {
      // API failed — fall back to demo data if there's a matching event
      const demo = DEMO_EVENTS.find((e) => e.id === id)
      if (demo) {
        console.warn('API fetch failed for event, using demo data:', apiErr.message)
        event.value = demo
      } else {
        throw apiErr
      }
    }
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Failed to load event details.'
    event.value = null
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }
  router.push({ name: 'gallery' })
}

function increaseQty() {
  quantity.value = Math.min(maxTickets.value, quantity.value + 1)
}

function decreaseQty() {
  quantity.value = Math.max(1, quantity.value - 1)
}

function onBookNow() {
  if (!isLoggedIn.value) {
    router.push({ name: 'login', query: { redirect: `/events/${event.value.id}` } })
    return
  }
  bookingStore.setBooking(event.value, quantity.value)
  router.push({ name: 'booking-review' })
}


onMounted(loadEvent)

watch(
  () => route.params.id,
  () => {
    loadEvent()
  }
)
</script>

<template>
  <div class="event-details">
    <section class="event-details__body">
      <LoadingSpinner v-if="loading" label="Loading event details..." />

      <EmptyState
        v-else-if="error"
        icon="⚠️"
        title="Unable to load event"
        :description="error"
      >
        <template #action>
          <button class="btn btn-primary" @click="loadEvent">Retry</button>
        </template>
      </EmptyState>

      <template v-else-if="event">
        <div class="event-details__topbar">
          <button class="back-link" @click="goBack">← Back to Events</button>
        </div>

        <BookingStepper :activeStep="2" />

        <section
          class="event-details__hero"
          :style="{ background: heroBackground }"
        >
          <span class="event-details__category" :class="[categoryStyle.bg, categoryStyle.text]">{{ event.category }}</span>
          <h1>{{ event.title }}</h1>
        </section>

        <div class="event-details__layout">
          <div class="event-details__left">
            <article class="card">
              <h2>Event Details</h2>
              <div class="detail-grid">
                <p><strong>Date & Time</strong><br>{{ formatDate(event.date) }}<br>{{ event.startTime }} - {{ event.endTime }}</p>
                <p><strong>Location</strong><br>{{ event.venue }}</p>
                <p><strong>Attendees</strong><br>{{ attendees }} registered</p>
                <p><strong>Organizer</strong><br>{{ organizerName }}</p>
                <p><strong>Rating</strong><br>{{ avgRating }} / 5 ({{ reviewCount }} reviews)</p>
                <p><strong>Status</strong><br>{{ event.status }}</p>
              </div>
            </article>

            <article class="card">
              <h2>About This Event</h2>
              <p>{{ event.description }}</p>
            </article>

            <article class="card">
              <h2>Event Agenda</h2>
              <ul class="agenda-list">
                <li v-for="item in agendaItems" :key="`${item.time}-${item.title}`">
                  <span class="agenda-time">{{ item.time }}</span>
                  <span class="agenda-title">
                    {{ item.title }}
                    <small v-if="item.description">{{ item.description }}</small>
                  </span>
                </li>
              </ul>
            </article>
          </div>

          <aside class="payment-card">
            <h2>{{ event.price || 'Free' }}</h2>
            <p class="muted">per ticket</p>
            <p class="availability">{{ event.availableSeats }} tickets available</p>

            <div class="qty-row">
              <span>Quantity</span>
              <div class="qty-control">
                <button @click="decreaseQty" :disabled="quantity <= 1">-</button>
                <span>{{ quantity }}</span>
                <button @click="increaseQty" :disabled="quantity >= maxTickets">+</button>
              </div>
            </div>

            <p class="muted">Maximum {{ maxTickets }} tickets per booking</p>

            <div class="price-lines">
              <p><span>{{ quantity }} x Ticket</span><strong>RM {{ subtotal.toFixed(2) }}</strong></p>
              <p><span>Service Fee</span><strong>RM {{ (ticketPrice > 0 ? serviceFee : 0).toFixed(2) }}</strong></p>
              <p class="total"><span>Total</span><strong>RM {{ total.toFixed(2) }}</strong></p>
            </div>

            <button v-if="canBook" class="btn btn-primary btn-block" @click="onBookNow">Book Now</button>
            <button v-else-if="isLoggedIn" class="btn btn-primary btn-block" disabled>Booking available for students only</button>
            <button v-else class="btn btn-primary btn-block" @click="onBookNow">Sign in to Book</button>
          </aside>
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
.event-details {
  max-width: 1120px;
  margin: 0 auto;
  padding: 18px 16px 40px;
}

.event-details__body {
  margin-top: 8px;
}

.event-details__topbar {
  margin-bottom: 12px;
}

.stepper {
  margin: 0 0 14px;
  display: flex;
  align-items: center;
  gap: 0;
  overflow-x: auto;
  padding-bottom: 2px;
}

.stepper__item {
  display: inline-flex;
  align-items: center;
  color: #94a3b8;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.stepper__dot {
  width: 20px;
  height: 20px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 6px;
}

.stepper__line {
  width: 36px;
  height: 2px;
  background: #dbe4ee;
  margin: 0 10px;
}

.stepper__item.is-done {
  color: #4f46e5;
}

.stepper__item.is-done .stepper__dot {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
}

.stepper__item.is-done .stepper__line {
  background: #6366f1;
}

.stepper__item.is-active {
  color: #4338ca;
}

.stepper__item.is-active .stepper__dot {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
}

.back-link {
  background: none;
  border: 0;
  color: #475569;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.event-details__hero {
  height: 190px;
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  color: #fff;
  border: 1px solid #dbe4ee;
  background: linear-gradient(130deg, #0f172a 0%, #312e81 100%);
  background-size: cover;
  background-position: center;
  position: relative;
  overflow: hidden;
}

.event-details__hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(0deg, rgba(2, 6, 23, 0.7), rgba(2, 6, 23, 0.1));
}

.event-details__hero > * {
  position: relative;
  z-index: 1;
}

.event-details__category {
  display: inline-block;
  align-self: flex-start;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 8px;
}

.event-details__hero h1 {
  margin: 0;
  font-size: clamp(28px, 4vw, 40px);
  letter-spacing: -0.02em;
}

.event-details__layout {
  margin-top: 16px;
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 14px;
  align-items: start;
}

.event-details__left {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.card,
.payment-card {
  background: #fff;
  border: 1px solid #dbe4ee;
  border-radius: 12px;
  padding: 16px;
}

.card h2,
.payment-card h2 {
  margin: 0 0 10px;
  color: #0f172a;
  font-size: 24px;
}

.card p,
.detail-grid p,
.payment-card p {
  margin: 0;
  color: #475569;
  line-height: 1.6;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.detail-grid strong {
  color: #334155;
}

.agenda-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.agenda-list li {
  display: flex;
  gap: 10px;
  align-items: center;
}

.agenda-time {
  background: #eef2ff;
  color: #4338ca;
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 12px;
  font-weight: 700;
  min-width: 78px;
  text-align: center;
}

.agenda-title {
  color: #334155;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.agenda-title small {
  font-size: 12px;
  color: #64748b;
}

.muted {
  color: #64748b;
  font-size: 12px;
}

.availability {
  margin-top: 4px;
  font-size: 13px;
  color: #334155;
  font-weight: 600;
}

.qty-row {
  margin-top: 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #334155;
  font-weight: 600;
}

.qty-control {
  display: inline-flex;
  align-items: center;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  overflow: hidden;
}

.qty-control button,
.qty-control span {
  width: 32px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  background: #fff;
}

.qty-control button {
  cursor: pointer;
  color: #475569;
}

.qty-control button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.price-lines {
  margin-top: 12px;
  border-top: 1px solid #e2e8f0;
  padding-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.price-lines p {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.price-lines .total {
  font-size: 18px;
  color: #0f172a;
  font-weight: 700;
  border-top: 1px solid #e2e8f0;
  padding-top: 8px;
}

.btn {
  border: 0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  background: #4f46e5;
  color: #fff;
}

.btn-primary:hover {
  background: #4338ca;
}

.btn-block {
  margin-top: 14px;
  width: 100%;
}

@media (max-width: 980px) {
  .event-details__layout {
    grid-template-columns: 1fr;
  }

  .payment-card {
    order: -1;
  }
}

@media (max-width: 640px) {
  .event-details {
    padding: 12px 10px 24px;
  }

  .event-details__hero {
    height: 170px;
  }

  .event-details__hero h1 {
    font-size: 32px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .stepper__line {
    width: 24px;
    margin: 0 6px;
  }
}
</style>
