import { reactive, watch } from 'vue'

const STORAGE_KEY = 'freshdev-booking-store'

function readStoredState() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

const saved = readStoredState()

export const bookingStore = reactive({
  event: saved?.event || null,
  quantity: saved?.quantity ?? 1,
  bookingId: saved?.bookingId || null,
  amount: saved?.amount ?? 0.0,
  bookingStatus: saved?.bookingStatus || null,

  setBooking(event, quantity) {
    this.event = event
    this.quantity = quantity
    this.bookingId = null
    this.amount = 0.0
    this.bookingStatus = null
  },

  setConfirmedBooking(bookingId, amount, status) {
    this.bookingId = bookingId
    this.amount = amount
    this.bookingStatus = status
  },

  clear() {
    this.event = null
    this.quantity = 1
    this.bookingId = null
    this.amount = 0.0
    this.bookingStatus = null
  }
})

// Auto-persist to sessionStorage on every change
watch(
  () => ({
    event: bookingStore.event,
    quantity: bookingStore.quantity,
    bookingId: bookingStore.bookingId,
    amount: bookingStore.amount,
    bookingStatus: bookingStore.bookingStatus,
  }),
  (state) => {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state))
    } catch {
      // sessionStorage full or unavailable — ignore silently
    }
  },
  { deep: true }
)
