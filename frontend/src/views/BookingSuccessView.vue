<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { bookingStore } from '../service/bookingStore.js'
import BookingStepper from '../components/bookings/BookingStepper.vue'
import { formatDate } from '../utils/format.js'

const router = useRouter()

const event = computed(() => bookingStore.event)
const quantity = computed(() => bookingStore.quantity)
const bookingId = computed(() => bookingStore.bookingId)
const amount = computed(() => bookingStore.amount)
const status = computed(() => bookingStore.bookingStatus)

const isSuccess = computed(() => status.value === 'confirmed' || status.value === 'successful')

onMounted(() => {
  if (!bookingId.value || !event.value) {
    router.push({ name: 'gallery' })
  }
})

function finish() {
  bookingStore.clear()
  router.push({ name: 'dashboard' })
}

function viewHistory() {
  bookingStore.clear()
  router.push({ name: 'booking-history' })
}
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 py-8">
    <BookingStepper :activeStep="5" />

    <div v-if="!bookingId" class="text-center py-12 bg-white rounded-xl shadow-sm border border-slate-100">
      <p class="text-slate-500 mb-4">No booking details to display.</p>
      <button class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold" @click="finish">
        Go to Dashboard
      </button>
    </div>

    <div v-else class="space-y-6">
      <!-- Status Card -->
      <div 
        class="rounded-xl p-8 text-center space-y-4 border shadow-sm"
        :class="isSuccess ? 'bg-emerald-50 border-emerald-100' : 'bg-red-50 border-red-100'"
      >
        <div 
          class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-3xl shadow-sm"
          :class="isSuccess ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'"
        >
          {{ isSuccess ? '✓' : '×' }}
        </div>
        <div>
          <h1 class="text-2xl font-black" :class="isSuccess ? 'text-emerald-950' : 'text-red-950'">
            {{ isSuccess ? 'Booking Confirmed!' : 'Payment Failed' }}
          </h1>
          <p class="text-sm mt-1" :class="isSuccess ? 'text-emerald-700' : 'text-red-700'">
            {{ isSuccess ? 'Your tickets are locked and registered. Have a great time!' : 'We could not process your transaction. You can try again from your history.' }}
          </p>
        </div>
      </div>

      <!-- Details Summary -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
        <h2 class="text-base font-bold text-slate-800 pb-2 border-b border-slate-100">Transaction Summary</h2>
        
        <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-sm text-slate-600">
          <div>Booking ID</div>
          <div class="font-bold text-slate-800 text-right">#{{ bookingId }}</div>

          <div>Event Title</div>
          <div class="font-semibold text-slate-800 text-right">{{ event.title }}</div>

          <div>Date & Time</div>
          <div class="font-semibold text-slate-800 text-right">{{ formatDate(event.date) }} • {{ event.startTime }}</div>

          <div>Venue</div>
          <div class="font-semibold text-slate-800 text-right">{{ event.venue }}</div>

          <div>Ticket Quantity</div>
          <div class="font-semibold text-slate-800 text-right">{{ quantity }} Ticket(s)</div>

          <div class="pt-2 border-t border-slate-100">
            {{ isSuccess ? 'Total Paid' : 'Total Amount' }}
          </div>
          <div class="pt-2 border-t border-slate-100 font-extrabold text-indigo-600 text-right">
            RM {{ Number(amount).toFixed(2) }}
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row gap-3 justify-center items-center pt-2">
        <button 
          class="w-full sm:w-auto px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-sm transition"
          @click="finish"
        >
          Go to Dashboard
        </button>
        <button 
          class="w-full sm:w-auto px-6 py-2.5 border border-slate-200 text-slate-600 hover:bg-slate-50 font-semibold rounded-lg transition"
          @click="viewHistory"
        >
          View Bookings
        </button>
      </div>
    </div>
  </div>
</template>
