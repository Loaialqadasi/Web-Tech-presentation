<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { fetchBookedEvents, cancelBooking, errMsg } from '../service/api.js'
import { authState } from '../service/auth.js'
import { bookingStore } from '../service/bookingStore.js'
import { formatDate } from '../utils/format.js'
import LoadingSpinner from '../components/shared/LoadingSpinner.vue'
import EmptyState from '../components/shared/EmptyState.vue'

const router = useRouter()
const loading = ref(true)
const bookings = ref([])
const error = ref('')
const actionLoading = ref(false)

async function loadBookings() {
  if (!authState.user) {
    error.value = 'Please log in to view your bookings.'
    loading.value = false
    return
  }

  loading.value = true
  error.value = ''
  try {
    bookings.value = await fetchBookedEvents(authState.user.id)
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadBookings)

async function handleCancel(booking) {
  const confirmText = `Are you sure you want to cancel your booking for "${booking.event.title}"? This will restore ${booking.ticketQuantity} available seats.`
  if (!window.confirm(confirmText)) return

  actionLoading.value = true
  try {
    await cancelBooking(booking.bookingId)
    await loadBookings()
  } catch (e) {
    alert(errMsg(e))
  } finally {
    actionLoading.value = false
  }
}

function handleRetryPayment(booking) {
  // Setup store details so /checkout route can retrieve it
  bookingStore.setBooking(booking.event, booking.ticketQuantity)
  bookingStore.setConfirmedBooking(booking.bookingId, booking.amount, 'pending_payment')
  router.push({ name: 'checkout' })
}

function viewEvent(eventId) {
  router.push({ name: 'event-details', params: { id: eventId } })
}

function statusBadgeClass(status) {
  switch (status) {
    case 'confirmed':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'pending_payment':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'cancelled':
      return 'bg-slate-100 text-slate-600 border-slate-200'
    case 'payment_failed':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    default:
      return 'bg-slate-100 text-slate-600 border-slate-200'
  }
}

function statusLabel(status) {
  switch (status) {
    case 'confirmed': return 'Confirmed'
    case 'pending_payment': return 'Pending Payment'
    case 'cancelled': return 'Cancelled'
    case 'payment_failed': return 'Payment Failed'
    default: return status
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <header class="mb-6 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">My Booking History</h1>
        <p class="text-slate-500 text-sm">View, manage, or cancel your event registrations.</p>
      </div>
      <button 
        class="px-4 py-2 border border-slate-200 text-sm rounded-lg hover:bg-slate-50 transition text-slate-600 font-semibold"
        @click="loadBookings"
        :disabled="loading || actionLoading"
      >
        🔄 Refresh
      </button>
    </header>

    <div v-if="loading" class="py-12">
      <LoadingSpinner label="Loading bookings..." />
    </div>

    <div v-else-if="error" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8">
      <EmptyState icon="⚠️" title="Error Loading Bookings" :description="error">
        <template #action>
          <button class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-lg" @click="loadBookings">
            Try Again
          </button>
        </template>
      </EmptyState>
    </div>

    <div v-else-if="bookings.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8">
      <EmptyState 
        icon="🎟️" 
        title="No Bookings Yet" 
        description="You have not booked any events. Explore the event gallery to find interesting sessions."
      >
        <template #action>
          <button class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-lg" @click="router.push({ name: 'gallery' })">
            Browse Events
          </button>
        </template>
      </EmptyState>
    </div>

    <!-- Bookings List -->
    <div v-else class="space-y-4">
      <div 
        v-for="b in bookings" 
        :key="b.bookingId" 
        class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col md:flex-row gap-5 items-start md:items-center justify-between hover:shadow-md transition duration-200"
      >
        <div class="flex gap-4 items-start md:items-center flex-1">
          <!-- Thumbnail Fallback -->
          <div class="w-16 h-16 rounded-lg bg-indigo-50 flex-shrink-0 overflow-hidden border border-indigo-100">
            <img v-if="b.event.image" :src="b.event.image" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-xl text-indigo-400">
              📅
            </div>
          </div>
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase tracking-wide">
                {{ b.event.category }}
              </span>
              <span 
                class="text-xs font-bold px-2.5 py-0.5 rounded-full border" 
                :class="statusBadgeClass(b.bookingStatus)"
              >
                {{ statusLabel(b.bookingStatus) }}
              </span>
              <span 
                v-if="b.amount > 0"
                class="text-xs font-bold px-2.5 py-0.5 rounded-full border" 
                :class="b.bookingStatus === 'confirmed' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-red-100 text-red-800 border-red-200'"
              >
                {{ b.bookingStatus === 'confirmed' ? 'Paid' : 'Unpaid' }}
              </span>
              <span 
                v-else
                class="text-xs font-bold px-2.5 py-0.5 rounded-full border bg-slate-100 text-slate-700 border-slate-200"
              >
                Free
              </span>
            </div>
            <h3 
              class="font-bold text-slate-800 text-base hover:text-indigo-600 cursor-pointer"
              @click="viewEvent(b.eventId)"
            >
              {{ b.event.title }}
            </h3>
            <p class="text-xs text-slate-500">
              📅 {{ formatDate(b.event.date) }} • {{ b.event.startTime || b.event.time || '' }} | 📍 {{ b.event.venue }}
            </p>
          </div>
        </div>

        <div class="flex flex-col md:items-end gap-2.5 w-full md:w-auto pt-4 md:pt-0 border-t md:border-t-0 border-slate-100">
          <div class="text-sm text-slate-600 flex justify-between md:block w-full md:w-auto">
            <span>{{ b.ticketQuantity }} Ticket(s)</span>
            <span class="md:ml-2 font-bold text-slate-800">RM {{ Number(b.amount).toFixed(2) }}</span>
          </div>

          <div class="flex gap-2 w-full md:w-auto">
            <button 
              v-if="b.bookingStatus === 'pending_payment'"
              class="flex-1 md:flex-initial px-4 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition"
              @click="handleRetryPayment(b)"
              :disabled="actionLoading"
            >
              Pay Now
            </button>
            <button 
              v-if="['confirmed', 'pending_payment'].includes(b.bookingStatus)"
              class="flex-1 md:flex-initial px-4 py-1.5 border border-red-200 text-red-600 hover:bg-red-50 text-xs font-bold rounded-lg transition"
              @click="handleCancel(b)"
              :disabled="actionLoading"
            >
              Cancel Booking
            </button>
            <button 
              class="flex-1 md:flex-initial px-4 py-1.5 border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold rounded-lg transition"
              @click="viewEvent(b.eventId)"
              :disabled="actionLoading"
            >
              View Info
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
