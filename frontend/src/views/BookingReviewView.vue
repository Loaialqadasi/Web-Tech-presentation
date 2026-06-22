<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { bookingStore } from '../service/bookingStore.js'
import { bookEvent, errMsg } from '../service/api.js'
import BookingStepper from '../components/bookings/BookingStepper.vue'
import { formatDate, parsePrice } from '../utils/format.js'
import { authState } from '../service/auth.js'

const router = useRouter()
const loading = ref(false)
const error = ref('')

const event = computed(() => bookingStore.event)
const quantity = computed(() => bookingStore.quantity)

// Pricing helpers
const ticketPrice = computed(() => parsePrice(event.value?.price))
const serviceFee = 1.50
const subtotal = computed(() => quantity.value * ticketPrice.value)
const total = computed(() => subtotal.value + (ticketPrice.value > 0 ? serviceFee : 0))

onMounted(() => {
  if (!event.value) {
    router.push({ name: 'gallery' })
  }
})

function goBack() {
  if (event.value) {
    router.push({ name: 'event-details', params: { id: event.value.id } })
  } else {
    router.push({ name: 'gallery' })
  }
}

async function onConfirm() {
  if (!authState.user) {
    // If not logged in, redirect to login
    router.push({ name: 'login', query: { redirect: `/booking/review` } })
    return
  }

  error.value = ''
  loading.value = true

  try {
    const res = await bookEvent(event.value.id, quantity.value)
    
    // Set confirmed state in store
    bookingStore.setConfirmedBooking(res.bookingId, res.amount, res.bookingStatus)

    if (res.bookingStatus === 'confirmed') {
      // Free event bypasses payment and goes straight to success
      router.push({ name: 'booking-success' })
    } else {
      // Paid event goes to checkout payment simulation
      router.push({ name: 'checkout' })
    }
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <BookingStepper :activeStep="3" />

    <div v-if="!event" class="text-center py-12 bg-white rounded-xl shadow-sm border border-slate-100">
      <p class="text-slate-500 mb-4">No event selected for booking.</p>
      <button class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold" @click="goBack">
        Browse Events
      </button>
    </div>

    <div v-else class="space-y-6">
      <header>
        <h1 class="text-2xl font-bold text-slate-800">Review Your Booking</h1>
        <p class="text-slate-500 text-sm">Please verify your ticket quantity and total before proceeding.</p>
      </header>

      <!-- Event Summary Card -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col md:flex-row gap-6">
        <div v-if="event.image" class="w-full md:w-1/3 h-32 rounded-lg overflow-hidden bg-slate-100">
          <img :src="event.image" class="w-full h-full object-cover" :alt="event.title" />
        </div>
        <div class="flex-1 space-y-2">
          <span class="inline-block text-xs font-semibold px-2.5 py-1 rounded bg-indigo-50 text-indigo-600">
            {{ event.category }}
          </span>
          <h2 class="text-lg font-bold text-slate-800">{{ event.title }}</h2>
          <p class="text-slate-600 text-sm flex items-center gap-1.5">
            <span>📅</span> {{ formatDate(event.date) }} • {{ event.startTime || event.time || '' }}
          </p>
          <p class="text-slate-600 text-sm flex items-center gap-1.5">
            <span>📍</span> {{ event.venue }}
          </p>
        </div>
      </div>

      <!-- Ticket Selection Details -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
        <h3 class="text-base font-bold text-slate-800 pb-2 border-b border-slate-100">Price Breakdown</h3>
        
        <div class="space-y-2.5 text-sm text-slate-600">
          <div class="flex justify-between">
            <span>{{ quantity }} x Ticket ({{ event.price }})</span>
            <span class="font-semibold text-slate-800">RM {{ subtotal.toFixed(2) }}</span>
          </div>
          <div v-if="ticketPrice > 0" class="flex justify-between">
            <span>Service Fee</span>
            <span class="font-semibold text-slate-800">RM {{ serviceFee.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between pt-3 border-t border-slate-100 text-base font-bold text-slate-800">
            <span>Total amount</span>
            <span class="text-indigo-600">RM {{ total.toFixed(2) }}</span>
          </div>
        </div>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg text-sm">
        {{ error }}
      </div>

      <!-- Navigation buttons -->
      <div class="flex justify-between items-center pt-2">
        <button 
          class="px-6 py-2.5 border border-slate-200 rounded-lg text-slate-600 font-semibold hover:bg-slate-50 transition" 
          @click="goBack"
          :disabled="loading"
        >
          Modify Tickets
        </button>
        
        <button 
          class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md transition flex items-center gap-2 disabled:opacity-50"
          @click="onConfirm"
          :disabled="loading"
        >
          <span v-if="loading">Processing...</span>
          <span v-else>{{ total > 0 ? 'Proceed to Payment' : 'Confirm Free Booking' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
