<script setup>
/**
 * EventCard — single reusable event display card
 * Owner: Loai AlQadasi (UI/UX Lead)
 *
 * Used by: GalleryView, DashboardView, BookingReviewView, ForumView, ...
 * Any module that needs to show an event should use this component to keep
 * the visual language consistent across the app.
 *
 * Variants:
 *   - variant="default" : full card with image, title, date, venue, price, seats
 *   - variant="compact"  : single-row card for lists / sidebars
 */
import { computed } from 'vue'
import {
  formatDate,
  truncate,
  categoryColor,
  bookingPercent,
} from '../../utils/format.js'

const props = defineProps({
  event: { type: Object, required: true },
  variant: { type: String, default: 'default' }, // 'default' | 'compact'
  showCategory: { type: Boolean, default: true },
  showStats: { type: Boolean, default: true },
  clickable: { type: Boolean, default: true },
})

const emit = defineEmits(['select'])

const dateDay = computed(() => {
  if (!props.event.date) return ''
  const d = new Date(props.event.date)
  if (Number.isNaN(d.getTime())) return ''
  return { day: String(d.getDate()), month: d.toLocaleDateString('en-GB', { month: 'short' }) }
})

const cat = computed(() => categoryColor(props.event.category))

const seatsLeft = computed(() => props.event.availableSeats ?? 0)
const seatsTotal = computed(() => props.event.capacity ?? 0)
const seatsBookedPct = computed(() => bookingPercent(seatsTotal.value, seatsLeft.value))

const isAlmostFull = computed(() => seatsLeft.value > 0 && seatsLeft.value <= Math.ceil(seatsTotal.value * 0.1))
const isFull = computed(() => seatsLeft.value === 0)

const isPaid = computed(() => {
  const priceStr = String(props.event.price || '').trim()
  if (!priceStr || priceStr.toLowerCase() === 'free' || priceStr.toLowerCase() === 'free entry') {
    return false
  }
  return true
})


const displayImage = computed(() => toBackground(props.event.image || props.event.imageUrl) || fallbackForCategory(props.event.category))

function toBackground(value) {
  if (!value) return ''
  const s = String(value).trim()
  if (!s) return ''
  if (s.startsWith('linear-gradient(') || s.startsWith('url(')) return s
  return `url('${s}') center/cover no-repeat`
}

function fallbackForCategory(cat) {
  // Deterministic photo fallback so each event card always has an image.
  const map = {
    Technology: `url('https://picsum.photos/seed/unievent-technology/900/520') center/cover no-repeat`,
    Career: `url('https://picsum.photos/seed/unievent-career/900/520') center/cover no-repeat`,
    Academic: `url('https://picsum.photos/seed/unievent-academic/900/520') center/cover no-repeat`,
    Workshop: `url('https://picsum.photos/seed/unievent-workshop/900/520') center/cover no-repeat`,
    Seminar: `url('https://picsum.photos/seed/unievent-seminar/900/520') center/cover no-repeat`,
    Sports: `url('https://picsum.photos/seed/unievent-sports/900/520') center/cover no-repeat`,
    Cultural: `url('https://picsum.photos/seed/unievent-cultural/900/520') center/cover no-repeat`,
    'Community Service': `url('https://picsum.photos/seed/unievent-community/900/520') center/cover no-repeat`,
    Arts: `url('https://picsum.photos/seed/unievent-arts/900/520') center/cover no-repeat`,
    Entertainment: `url('https://picsum.photos/seed/unievent-entertainment/900/520') center/cover no-repeat`,
  }
  return map[cat] || map.Technology
}

function onClick() {
  if (props.clickable) emit('select', props.event)
}
</script>

<template>
  <!-- ─── Compact variant: single-row card ─── -->
  <article
    v-if="variant === 'compact'"
    class="event-card event-card--compact"
    :class="{ 'event-card--clickable': clickable }"
    @click="onClick"
  >
    <div
      class="event-card__thumb-compact"
      :style="{ background: displayImage }"
    >
      <span class="event-card__thumb-cat" :class="[cat.bg, cat.text]">{{ event.category }}</span>
    </div>
    <div class="event-card__body-compact">
      <h3 class="event-card__title-compact" :title="event.title">{{ truncate(event.title, 60) }}</h3>
      <p class="event-card__meta-compact">
        <span>{{ formatDate(event.date) }}</span>
        <span aria-hidden="true">•</span>
        <span>{{ event.venue }}</span>
      </p>
      <p v-if="showStats" class="event-card__price-compact">
        <strong>{{ event.price || 'Free' }}</strong>
      </p>
    </div>
  </article>

  <!-- ─── Default variant: full card ─── -->
  <article
    v-else
    class="event-card"
    :class="{ 'event-card--clickable': clickable, 'event-card--full': isFull }"
    @click="onClick"
  >
    <!-- Header / image area -->
    <div
      class="event-card__thumb"
      :style="{ background: displayImage }"
    >
      <div class="event-card__badges-group">
        <span
          v-if="showCategory"
          class="event-card__cat-badge"
          :class="[cat.bg, cat.text]"
        >{{ event.category }}</span>
        <span
          class="event-card__price-badge"
          :class="isPaid ? 'event-card__price-badge--paid' : 'event-card__price-badge--free'"
        >
          {{ isPaid ? 'Paid' : 'Free' }}
        </span>
      </div>

      <span v-if="isFull" class="event-card__badge event-card__badge--full">Full</span>
      <span v-else-if="isAlmostFull" class="event-card__badge event-card__badge--warn">Almost Full</span>

      <!-- Date chip overlay -->
      <div v-if="dateDay" class="event-card__date-chip">
        <span class="event-card__date-day">{{ dateDay.day }}</span>
        <span class="event-card__date-month">{{ dateDay.month }}</span>
      </div>
    </div>

    <!-- Body -->
    <div class="event-card__body">
      <h3 class="event-card__title" :title="event.title">{{ event.title }}</h3>
      <p class="event-card__desc">{{ truncate(event.description, 110) }}</p>

      <div class="event-card__meta">
        <div class="event-card__meta-row">
          <svg viewBox="0 0 24 24" class="event-card__icon" aria-hidden="true">
            <path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z" fill="currentColor"/>
          </svg>
          <span>{{ event.venue }}</span>
        </div>
        <div class="event-card__meta-row">
          <svg viewBox="0 0 24 24" class="event-card__icon" aria-hidden="true">
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 10V6h-2v7l5 3 1-1.7-4-2.3z" fill="currentColor"/>
          </svg>
          <span>{{ event.time }}</span>
        </div>
      </div>

      <!-- Stats / progress -->
      <div v-if="showStats" class="event-card__stats">
        <div class="event-card__stats-bar">
          <div
            class="event-card__stats-fill"
            :class="{ 'event-card__stats-fill--warn': isAlmostFull, 'event-card__stats-fill--full': isFull }"
            :style="{ width: seatsBookedPct + '%' }"
          ></div>
        </div>
        <div class="event-card__stats-text">
          <span v-if="isFull">Fully booked</span>
          <span v-else>{{ seatsLeft }} of {{ seatsTotal }} seats left</span>
          <span class="event-card__price">{{ event.price || 'Free' }}</span>
        </div>
      </div>

      <!-- Rating -->
      <div v-if="event.reviewCount > 0" class="event-card__rating">
        <span class="event-card__stars">★</span>
        <strong>{{ (Number(event.avgRating) || 0).toFixed(1) }}</strong>
        <span class="event-card__rating-count">({{ event.reviewCount }})</span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.event-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.event-card--clickable { cursor: pointer; }
.event-card--clickable:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.18);
  border-color: #c7d2fe;
}
.event-card--full { opacity: 0.85; }

.event-card__thumb {
  position: relative;
  height: 160px;
  background: linear-gradient(135deg,#6366f1 0%,#1e3a8a 100%);
}
.event-card__badges-group {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  z-index: 10;
}
.event-card__cat-badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.event-card__price-badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.event-card__price-badge--paid {
  background: #f59e0b;
  color: #fff;
}
.event-card__price-badge--free {
  background: #10b981;
  color: #fff;
}
.event-card__badge {
  position: absolute;
  top: 12px;
  right: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #fff;
}
.event-card__badge--full { background: #ef4444; }
.event-card__badge--warn { background: #f59e0b; }

.event-card__date-chip {
  position: absolute;
  bottom: 12px;
  right: 12px;
  background: rgba(255,255,255,0.95);
  border-radius: 10px;
  padding: 6px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.1;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.event-card__date-day { font-size: 18px; font-weight: 700; color: #0f172a; }
.event-card__date-month { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; }

.event-card__body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}
.event-card__title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.event-card__desc {
  margin: 0;
  font-size: 13px;
  color: #475569;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.event-card__meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: #64748b;
}
.event-card__meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
}
.event-card__icon {
  width: 14px;
  height: 14px;
  color: #94a3b8;
  flex-shrink: 0;
}
.event-card__stats {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.event-card__stats-bar {
  height: 5px;
  background: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
}
.event-card__stats-fill {
  height: 100%;
  background: #10b981;
  border-radius: 999px;
  transition: width 0.3s ease;
}
.event-card__stats-fill--warn { background: #f59e0b; }
.event-card__stats-fill--full { background: #ef4444; }
.event-card__stats-text {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #64748b;
}
.event-card__price {
  font-weight: 700;
  color: #0f172a;
}
.event-card__rating {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #475569;
  padding-top: 6px;
  border-top: 1px solid #f1f5f9;
}
.event-card__stars { color: #f59e0b; font-size: 14px; }
.event-card__rating-count { color: #94a3b8; }

/* ─── Compact variant ─── */
.event-card--compact {
  display: flex;
  gap: 12px;
  padding: 12px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.event-card--clickable.event-card--compact:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}
.event-card__thumb-compact {
  width: 56px;
  height: 56px;
  border-radius: 10px;
  flex-shrink: 0;
  position: relative;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 4px;
}
.event-card__thumb-cat {
  font-size: 9px;
  font-weight: 700;
  padding: 2px 5px;
  border-radius: 4px;
  background: rgba(255,255,255,0.95);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.event-card__body-compact {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 2px;
  min-width: 0;
}
.event-card__title-compact {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.event-card__meta-compact {
  margin: 0;
  font-size: 12px;
  color: #64748b;
  display: flex;
  gap: 6px;
  align-items: center;
}
.event-card__price-compact {
  margin: 0;
  font-size: 12px;
  color: #0f172a;
}
.event-card__price-compact strong { color: #4f46e5; }
</style>
