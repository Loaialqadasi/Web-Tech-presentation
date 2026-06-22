<script setup>
import { computed } from 'vue'
import { formatDate, formatDateTime } from '../../utils/format.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  event: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const attendees = computed(() => {
  if (!props.event) return 0
  return Number(props.event.capacity || 0) - Number(props.event.availableSeats || 0)
})

const rating = computed(() => Number(props.event?.avgRating || 0).toFixed(1))
const reviewCount = computed(() => Number(props.event?.reviewCount || 0))

function close() {
  emit('update:modelValue', false)
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue && event" class="event-detail-overlay" @click.self="close">
      <div class="event-detail-dialog" role="dialog" aria-modal="true" aria-label="Event details">
        <header class="event-detail-header">
          <div>
            <p class="event-detail-category">{{ event.category }}</p>
            <h2>{{ event.title }}</h2>
          </div>
          <button type="button" class="icon-btn" @click="close">x</button>
        </header>

        <section class="event-detail-body">
          <p class="event-detail-description">{{ event.description }}</p>

          <div class="detail-grid">
            <article class="detail-card">
              <h3>Schedule</h3>
              <p>{{ formatDate(event.date) }}</p>
              <p>{{ event.startTime }} - {{ event.endTime }}</p>
              <p>{{ formatDateTime(event.date, event.startTime) }}</p>
            </article>

            <article class="detail-card">
              <h3>Venue</h3>
              <p>{{ event.venue }}</p>
              <p>Status: <strong>{{ event.status }}</strong></p>
              <p>Price: <strong>{{ event.price || 'Free' }}</strong></p>
            </article>

            <article class="detail-card">
              <h3>Attendance</h3>
              <p>Capacity: {{ event.capacity }}</p>
              <p>Seats left: {{ event.availableSeats }}</p>
              <p>Attendees: <strong>{{ attendees }}</strong></p>
            </article>

            <article class="detail-card">
              <h3>Feedback</h3>
              <p>Average rating: <strong>{{ rating }}</strong> / 5</p>
              <p>Reviews: <strong>{{ reviewCount }}</strong></p>
            </article>
          </div>
        </section>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.event-detail-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  z-index: 120;
}

.event-detail-dialog {
  width: min(760px, 100%);
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 24px 40px rgba(15, 23, 42, 0.24);
}

.event-detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 20px;
  border-bottom: 1px solid #e2e8f0;
}

.event-detail-header h2 {
  margin: 0;
  font-size: 22px;
  color: #0f172a;
}

.event-detail-category {
  margin: 0 0 6px;
  color: #0284c7;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 11px;
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

.event-detail-body {
  padding: 20px;
}

.event-detail-description {
  margin: 0 0 16px;
  color: #334155;
  line-height: 1.6;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.detail-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px;
  background: #f8fafc;
}

.detail-card h3 {
  margin: 0 0 8px;
  font-size: 14px;
  color: #0f172a;
}

.detail-card p {
  margin: 0;
  font-size: 13px;
  color: #475569;
  line-height: 1.5;
}

@media (max-width: 760px) {
  .event-detail-overlay {
    padding: 12px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
