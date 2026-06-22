<script setup>
/**
 * GalleryView — University Event Gallery (Loai's primary deliverable)
 * Owner: Loai AlQadasi (UI/UX Lead) — A23EC9010
 *
 * Public page that lets any visitor (no login required) browse, search, filter,
 * and sort upcoming university events. Clicking an event emits navigation.
 *
 * Design notes:
 *   - Uses the shared EventCard component so this Gallery looks identical to
 *     events shown elsewhere (Dashboard, Forum, Bookings).
 *   - Filters + search are debounced via the shared SearchBar component.
 *   - Responsive grid: 1 / 2 / 3 / 4 columns at sm / md / lg / xl breakpoints.
 *   - Empty state and loading state are first-class.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'

import EventCard      from '../components/shared/EventCard.vue'
import EmptyState      from '../components/shared/EmptyState.vue'
import LoadingSpinner  from '../components/shared/LoadingSpinner.vue'
import SearchBar       from '../components/shared/SearchBar.vue'
import CategoryFilter  from '../components/shared/CategoryFilter.vue'

import { fetchEvents, fetchEventCategories } from '../service/api.js'

const router = useRouter()

// ─── Demo data fallback (used when API is unreachable — e.g. DB not configured) ──
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

// ─── State ────────────────────────────────────────────────────────────────────
const events       = ref([])
const categories   = ref(['All', 'Technology', 'Career', 'Academic', 'Sports', 'Arts', 'Entertainment'])
const loading      = ref(true)
const error        = ref('')
const activeCat    = ref('All')
const searchQuery  = ref('')
const sortBy       = ref('date')  // 'date' | 'popular' | 'price_low' | 'price_high'
const hideFull     = ref(false)   // when true, fully-booked events are hidden
const usingDemoData = ref(false)  // tracks whether we fell back to demo data

// ─── Data Fetching ───────────────────────────────────────────────────────────
async function loadEvents() {
  loading.value = true
  error.value = ''
  usingDemoData.value = false
  try {
    const data = await fetchEvents()
    if (Array.isArray(data) && data.length > 0) {
      events.value = data
    } else {
      // API returned empty — use demo data so the gallery is never blank
      events.value = DEMO_EVENTS
      usingDemoData.value = true
    }
  } catch (e) {
    // API unreachable (e.g. DB not configured) — fall back to demo data
    console.warn('API fetch failed, using demo data:', e.message)
    events.value = DEMO_EVENTS
    usingDemoData.value = true
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  try {
    const list = await fetchEventCategories()
    if (Array.isArray(list) && list.length) categories.value = list
  } catch { /* fall back to defaults */ }
}

onMounted(() => {
  loadEvents()
  loadCategories()
})

// ─── Derived State (filtered + sorted) ───────────────────────────────────────
const filteredEvents = computed(() => {
  let list = [...events.value]

  // Category filter
  if (activeCat.value !== 'All') {
    list = list.filter((e) => e.category === activeCat.value)
  }

  // Search filter (title / venue / description)
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter((e) =>
      (e.title || '').toLowerCase().includes(q) ||
      (e.venue || '').toLowerCase().includes(q) ||
      (e.description || '').toLowerCase().includes(q)
    )
  }

  // "Hide full events" toggle — drops events with zero seats left
  if (hideFull.value) {
    list = list.filter((e) => (e.availableSeats ?? 0) > 0)
  }

  // Sorting
  switch (sortBy.value) {
    case 'popular':
      list.sort((a, b) => (b.attendees || 0) - (a.attendees || 0))
      break
    case 'price_low':
      list.sort((a, b) => priceNumber(a) - priceNumber(b))
      break
    case 'price_high':
      list.sort((a, b) => priceNumber(b) - priceNumber(a))
      break
    case 'date':
    default:
      list.sort((a, b) => new Date(a.date) - new Date(b.date))
  }

  return list
})

function priceNumber(e) {
  const p = String(e.price || 'Free')
  if (/free/i.test(p)) return 0
  const m = p.match(/RM\s*(\d+(?:\.\d+)?)/i)
  return m ? parseFloat(m[1]) : 0
}

// ─── Stats summary ──────────────────────────────────────────────────────────
const stats = computed(() => ({
  total:      events.value.length,
  free:       events.value.filter((e) => /free/i.test(e.price || '')).length,
  categories: new Set(events.value.map((e) => e.category)).size,
  upcoming:   events.value.filter((e) => new Date(e.date) >= new Date()).length,
}))

// ─── Event handlers ─────────────────────────────────────────────────────────
function openEvent(event) {
  router.push({ name: 'event-details', params: { id: event.id } })
}

function resetFilters() {
  activeCat.value = 'All'
  searchQuery.value = ''
  sortBy.value = 'date'
  hideFull.value = false
}

// Re-fetch on mount only — we don't auto-reload on filter change because the
// filtering is client-side. (Server supports filters but for a campus app with
// a small dataset, client-side filtering gives instant UX.)
</script>

<template>
  <div class="gallery">
    <!-- ─── Hero / page header ───────────────────────────────────────────── -->
    <header class="gallery__hero">
      <div class="gallery__hero-inner">
        <p class="gallery__eyebrow">University Event Management &amp; Ticketing</p>
        <h1 class="gallery__title">Discover Campus Events</h1>
        <p class="gallery__subtitle">
          Browse workshops, fairs, exhibitions and more happening across UTM.
          Book your seat in seconds — no page reloads, no fuss.
        </p>

        <div class="gallery__stats" v-if="!loading">
          <div class="gallery__stat">
            <span class="gallery__stat-value">{{ stats.total }}</span>
            <span class="gallery__stat-label">Total events</span>
          </div>
          <div class="gallery__stat">
            <span class="gallery__stat-value">{{ stats.upcoming }}</span>
            <span class="gallery__stat-label">Upcoming</span>
          </div>
          <div class="gallery__stat">
            <span class="gallery__stat-value">{{ stats.free }}</span>
            <span class="gallery__stat-label">Free entry</span>
          </div>
          <div class="gallery__stat">
            <span class="gallery__stat-value">{{ stats.categories }}</span>
            <span class="gallery__stat-label">Categories</span>
          </div>
        </div>
      </div>
    </header>

    <!-- ─── Toolbar: search + sort + filters ─────────────────────────────── -->
    <section class="gallery__toolbar">
      <div class="gallery__toolbar-inner">
        <div class="gallery__search">
          <SearchBar
            v-model="searchQuery"
            placeholder="Search events, venues, descriptions…"
            autofocus
          />
        </div>

        <div class="gallery__sort">
          <label for="sort" class="gallery__sort-label">Sort by</label>
          <select id="sort" v-model="sortBy" class="gallery__sort-select">
            <option value="date">Date (soonest)</option>
            <option value="popular">Most popular</option>
            <option value="price_low">Price (low to high)</option>
            <option value="price_high">Price (high to low)</option>
          </select>
        </div>

        <label class="gallery__toggle">
          <input type="checkbox" v-model="hideFull" />
          <span>Hide full events</span>
        </label>
      </div>

      <div class="gallery__toolbar-categories">
        <CategoryFilter v-model="activeCat" :categories="categories" />
      </div>
    </section>

    <!-- ─── Main content: loading / error / empty / grid ─────────────────── -->
    <section class="gallery__content">
      <div class="gallery__content-inner">

        <!-- Loading -->
        <LoadingSpinner v-if="loading" label="Loading events…" />

        <!-- Error -->
        <EmptyState
          v-else-if="error"
          icon="⚠️"
          title="Something went wrong"
          :description="error"
        >
          <template #action>
            <button class="btn btn--primary" @click="loadEvents">Retry</button>
          </template>
        </EmptyState>

        <!-- Empty -->
        <EmptyState
          v-else-if="filteredEvents.length === 0"
          icon="🔍"
          title="No events match your filters"
          description="Try widening your search or clearing the category filter."
        >
          <template #action>
            <button class="btn btn--ghost" @click="resetFilters">Clear filters</button>
          </template>
        </EmptyState>

        <!-- Grid -->
        <div v-if="usingDemoData" class="gallery__demo-banner">
          Showing demo data — connect a database to the backend to see live events.
        </div>
        <div class="gallery__grid">
          <EventCard
            v-for="event in filteredEvents"
            :key="event.id"
            :event="event"
            @select="openEvent"
          />
        </div>

        <p v-if="!loading && !error && filteredEvents.length > 0" class="gallery__count">
          Showing {{ filteredEvents.length }} of {{ events.length }} events
        </p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.gallery { display: flex; flex-direction: column; }

/* ─── Hero ─── */
.gallery__hero {
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
  color: #fff;
  padding: 56px 24px 64px;
}
.gallery__hero-inner { max-width: 1200px; margin: 0 auto; }
.gallery__eyebrow {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  font-weight: 700;
  opacity: 0.9;
  margin: 0 0 8px;
}
.gallery__title {
  font-size: clamp(32px, 4vw, 48px);
  font-weight: 800;
  margin: 0 0 12px;
  letter-spacing: -0.02em;
}
.gallery__subtitle {
  font-size: 17px;
  max-width: 640px;
  line-height: 1.55;
  margin: 0 0 28px;
  opacity: 0.95;
}
.gallery__stats {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
}
.gallery__stat { display: flex; flex-direction: column; gap: 2px; }
.gallery__stat-value { font-size: 28px; font-weight: 800; line-height: 1.1; }
.gallery__stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.85; }

/* ─── Toolbar ─── */
.gallery__toolbar {
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  padding: 16px 24px;
  position: sticky;
  top: 0;
  z-index: 10;
}
.gallery__toolbar-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 16px;
  align-items: center;
}
.gallery__search { min-width: 280px; }
.gallery__sort { display: flex; gap: 6px; align-items: center; }
.gallery__sort-label { font-size: 13px; color: #64748b; font-weight: 500; }
.gallery__sort-select {
  padding: 9px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  font-size: 14px;
  color: #0f172a;
  cursor: pointer;
}
.gallery__sort-select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18); }

.gallery__toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #475569;
  cursor: pointer;
  user-select: none;
}
.gallery__toggle input { width: 16px; height: 16px; accent-color: #4f46e5; cursor: pointer; }

.gallery__toolbar-categories {
  max-width: 1200px;
  margin: 12px auto 0;
}

/* ─── Content ─── */
.gallery__content { padding: 32px 24px 64px; }
.gallery__content-inner { max-width: 1200px; margin: 0 auto; }

.gallery__grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 20px;
}
@media (min-width: 640px) {
  .gallery__grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 900px) {
  .gallery__grid { grid-template-columns: repeat(3, 1fr); }
}
@media (min-width: 1200px) {
  .gallery__grid { grid-template-columns: repeat(4, 1fr); }
}

.gallery__count {
  text-align: center;
  margin-top: 32px;
  font-size: 13px;
  color: #94a3b8;
}

.gallery__demo-banner {
  background: #fef3c7;
  border: 1px solid #fcd34d;
  color: #92400e;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 16px;
}

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
}
.btn--primary { background: #4f46e5; color: #fff; }
.btn--primary:hover { background: #4338ca; }
.btn--ghost { background: #fff; color: #475569; border: 1px solid #d1d5db; }
.btn--ghost:hover { background: #f8fafc; border-color: #94a3b8; }

/* ─── Responsive ─── */
@media (max-width: 640px) {
  .gallery__toolbar-inner {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  .gallery__sort { justify-content: space-between; }
  .gallery__stats { gap: 16px; }
  .gallery__stat-value { font-size: 22px; }
}
</style>
