<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { bookingStore } from '../service/bookingStore.js'
import { createPayment, errMsg } from '../service/api.js'
import BookingStepper from '../components/bookings/BookingStepper.vue'
import { validateSchema, isValid, paymentSchema } from '../utils/validators.js'

const router = useRouter()
const loading = ref(false)
const error = ref('')

const event = computed(() => bookingStore.event)
const quantity = computed(() => bookingStore.quantity)
const bookingId = computed(() => bookingStore.bookingId)
const amount = computed(() => bookingStore.amount)

// Form Fields
const paymentMethod = ref('card')
const cardName = ref('')
const cardNumber = ref('')
const cardExpiry = ref('')
const cardCvv = ref('')
const formErrors = ref({})

onMounted(() => {
  if (!bookingId.value || !event.value) {
    router.push({ name: 'gallery' })
  }
})

function validateForm() {
  const errors = validateSchema({
    cardName: cardName.value,
    cardNumber: cardNumber.value,
    cardExpiry: cardExpiry.value,
    cardCvv: cardCvv.value,
  }, paymentSchema)
  formErrors.value = errors
  return isValid(errors)
}

async function onSubmit() {
  if (!validateForm()) return

  error.value = ''
  loading.value = true

  try {
    const res = await createPayment(bookingId.value, paymentMethod.value)
    
    // Update store state with simulated outcome (successful / failed)
    bookingStore.setConfirmedBooking(bookingId.value, amount.value, res.paymentStatus)
    
    // Redirect to confirmation screen
    router.push({ name: 'booking-success' })
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

function onCancel() {
  bookingStore.clear()
  router.push({ name: 'gallery' })
}
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 py-8">
    <BookingStepper :activeStep="4" />

    <div v-if="!bookingId" class="text-center py-12 bg-white rounded-xl shadow-sm border border-slate-100">
      <p class="text-slate-500 mb-4">No booking session found.</p>
      <button class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold" @click="onCancel">
        Go Back
      </button>
    </div>

    <div v-else class="space-y-6">
      <header>
        <h1 class="text-2xl font-bold text-slate-800">Secure Payment</h1>
        <p class="text-slate-500 text-sm">Simulate payment transaction for your tickets.</p>
      </header>

      <!-- Order Summary Banner -->
      <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6 flex justify-between items-center">
        <div>
          <h3 class="font-bold text-indigo-950">{{ event.title }}</h3>
          <p class="text-indigo-700 text-sm">{{ quantity }} Ticket(s)</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-indigo-700 font-semibold uppercase tracking-wider">Amount Due</p>
          <p class="text-2xl font-black text-indigo-900">RM {{ Number(amount).toFixed(2) }}</p>
        </div>
      </div>

      <!-- Payment details card -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-6">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <h3 class="text-base font-bold text-slate-800">Card Information</h3>
          <span class="text-xs text-slate-400 font-mono">Simulate Payment</span>
        </div>

        <form @submit.prevent="onSubmit" class="space-y-4">
          <!-- Cardholder Name -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="cardName">Cardholder Name</label>
            <input 
              id="cardName"
              v-model="cardName"
              type="text"
              placeholder="e.g. Siti Nur"
              class="px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-red-400': formErrors.cardName }"
            />
            <p v-if="formErrors.cardName" class="text-xs text-red-500">{{ formErrors.cardName }}</p>
          </div>

          <!-- Card Number -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="cardNumber">Card Number</label>
            <input 
              id="cardNumber"
              v-model="cardNumber"
              type="text"
              placeholder="1234 5678 1234 5678"
              class="px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-red-400': formErrors.cardNumber }"
            />
            <p v-if="formErrors.cardNumber" class="text-xs text-red-500">{{ formErrors.cardNumber }}</p>
          </div>

          <!-- Expiry & CVV -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="cardExpiry">Expiration Date</label>
              <input 
                id="cardExpiry"
                v-model="cardExpiry"
                type="text"
                placeholder="MM/YY"
                class="px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                :class="{ 'border-red-400': formErrors.cardExpiry }"
              />
              <p v-if="formErrors.cardExpiry" class="text-xs text-red-500">{{ formErrors.cardExpiry }}</p>
            </div>
            
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="cardCvv">CVV / CVC</label>
              <input 
                id="cardCvv"
                v-model="cardCvv"
                type="password"
                placeholder="3 digits"
                maxlength="3"
                class="px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                :class="{ 'border-red-400': formErrors.cardCvv }"
              />
              <p v-if="formErrors.cardCvv" class="text-xs text-red-500">{{ formErrors.cardCvv }}</p>
            </div>
          </div>

          <div class="p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs text-slate-500">
            ℹ️ Payment processing has a simulated 10% failure rate to demonstrate error flows.
          </div>

          <!-- Server Error -->
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg text-sm">
            {{ error }}
          </div>

          <!-- Buttons -->
          <div class="flex justify-between items-center pt-4">
            <button 
              type="button"
              class="px-6 py-2.5 border border-slate-200 rounded-lg text-slate-600 font-semibold hover:bg-slate-50 transition" 
              @click="onCancel"
              :disabled="loading"
            >
              Cancel Order
            </button>
            
            <button 
              type="submit"
              class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md transition flex items-center gap-2 disabled:opacity-50"
              :disabled="loading"
            >
              <span v-if="loading">Processing...</span>
              <span v-else>Pay RM {{ Number(amount).toFixed(2) }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
