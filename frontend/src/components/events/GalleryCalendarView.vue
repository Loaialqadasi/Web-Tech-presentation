<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { formatDate, relativeDay } from '../../utils/format.js'

const props = defineProps({
  events: { type: Array, default: () => [] },
  initialMonth: { type: Date, default: () => new Date() },
})

const router = useRouter()
const activeMonth = ref(new Date(props.initialMonth.getFullYear(), props.initialMonth.getMonth(), 1))

watch(() => props.initialMonth, (value) => {
  activeMonth.value = new Date(value.getFullYear(), value.getMonth(), 1)
})

const monthLabel = computed(() =>
  activeMonth.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
)

const monthEvents = computed(() => {
  const year = activeMonth.value.getFullYear()
  const month = activeMonth.value.getMonth()
  return props.events
    .filter((event) => {
      const d = new Date(event.date)
      return d.getFullYear() === year && d.getMonth() === month
    })
    .sort((a, b) => new Date(`${a.date} ${a.startTime || ''}`) - new Date(`${b.date} ${b.startTime || ''}`))
})

function previousMonth() {
  activeMonth.value = new Date(activeMonth.value.getFullYear(), activeMonth.value.getMonth() - 1, 1)
}

function nextMonth() {
  activeMonth.value = new Date(activeMonth.value.getFullYear(), activeMonth.value.getMonth() + 1, 1)
}

function openEvent(event) {
  if (!event.eventId) return
  router.push({ name: 'event-details', params: { id: event.eventId } })
}

function cardBackground(index) {
  const palettes = [
    'linear-gradient(135deg, #0f766e, #14b8a6)',
    'linear-gradient(135deg, #1d4ed8, #38bdf8)',
    'linear-gradient(135deg, #9333ea, #f472b6)',
    'linear-gradient(135deg, #b45309, #f59e0b)',
  ]
  return palettes[index % palettes.length]
}
</script>

<template>
  <section class="gallery-calendar">
    <div class="gallery-calendar__head">
      <div>
        <p class="gallery-calendar__kicker">Gallery Calendar</p>
        <h2>{{ monthLabel }}</h2>
      </div>
      <div class="gallery-calendar__nav">
        <button @click="previousMonth" aria-label="previous gallery month">&lt;</button>
        <button @click="nextMonth" aria-label="next gallery month">&gt;</button>
      </div>
    </div>

    <p v-if="monthEvents.length === 0" class="gallery-calendar__empty">
      No booked events in this month.
    </p>

    <div v-else class="gallery-calendar__grid">
      <article
        v-for="(event, index) in monthEvents"
        :key="event.calendarId"
        class="gallery-calendar__card"
        @click="openEvent(event)"
      >
        <div class="gallery-calendar__banner" :style="{ background: cardBackground(index) }">
          <span>{{ relativeDay(event.date) }}</span>
        </div>
        <div class="gallery-calendar__body">
          <h3>{{ event.title }}</h3>
          <p>{{ formatDate(event.date, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}</p>
          <p>{{ event.startTime || '--' }} - {{ event.endTime || '--' }}</p>
          <small>{{ event.venue || 'Venue TBA' }}</small>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.gallery-calendar {
  border-radius: 18px;
  border: 1px solid #dbeafe;
  background: #fff;
  padding: 1rem;
}

.gallery-calendar__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.9rem;
}

.gallery-calendar__kicker {
  margin: 0;
  color: #0f766e;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 0.76rem;
  font-weight: 700;
}

.gallery-calendar__head h2 {
  margin: 0.2rem 0 0;
  font-size: 1.3rem;
}

.gallery-calendar__nav {
  display: inline-flex;
  gap: 0.45rem;
}

.gallery-calendar__nav button {
  border: 0;
  cursor: pointer;
  border-radius: 10px;
  background: #e0f2fe;
  color: #075985;
  padding: 0.45rem 0.7rem;
  font-weight: 700;
}

.gallery-calendar__empty {
  margin: 0;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  padding: 0.85rem;
  color: #64748b;
}

.gallery-calendar__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.8rem;
}

.gallery-calendar__card {
  overflow: hidden;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.gallery-calendar__card:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 26px -18px rgba(15, 23, 42, 0.55);
}

.gallery-calendar__banner {
  min-height: 84px;
  padding: 0.85rem;
  color: #f8fafc;
  display: flex;
  align-items: flex-end;
  font-weight: 700;
}

.gallery-calendar__body {
  padding: 0.9rem;
}

.gallery-calendar__body h3 {
  margin: 0;
  font-size: 1rem;
}

.gallery-calendar__body p,
.gallery-calendar__body small {
  display: block;
  margin: 0.35rem 0 0;
  color: #475569;
}
</style>