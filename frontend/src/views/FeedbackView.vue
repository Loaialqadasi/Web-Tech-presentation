<script setup>
import { onMounted, ref, computed } from 'vue'
import { fetchFeedback, submitFeedback, deleteFeedback, updateFeedback, fetchEvents, errMsg } from '../service/api.js'
import { authState } from '../service/auth.js'
import { formatDate } from '../utils/format.js'
import { validateSchema, isValid, feedbackSchema } from '../utils/validators.js'
import LoadingSpinner from '../components/shared/LoadingSpinner.vue'
import EmptyState from '../components/shared/EmptyState.vue'

const loading = ref(true)
const feedBackList = ref([])
const events = ref([])
const error = ref('')
const submitError = ref('')
const submitSuccess = ref('')
const actionLoading = ref(false)

// Filters & Search
const searchQuery = ref('')
const filterRating = ref('')
const filterEventId = ref('')

const filteredFeedback = computed(() => {
  return feedBackList.value.filter(fb => {
    const matchesSearch = fb.review.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          (fb.user && fb.user.toLowerCase().includes(searchQuery.value.toLowerCase()))
    const matchesRating = !filterRating.value || fb.rating === Number(filterRating.value)
    const matchesEvent = !filterEventId.value || fb.eventId === Number(filterEventId.value)
    return matchesSearch && matchesRating && matchesEvent
  })
})

// Edit Modal State
const isEditModalOpen = ref(false)
const editingFeedbackId = ref(null)
const editRating = ref(5)
const editReview = ref('')
const editError = ref('')

function openEditModal(fb) {
  editingFeedbackId.value = fb.feedbackId
  editRating.value = fb.rating
  editReview.value = fb.review
  editError.value = ''
  isEditModalOpen.value = true
}

async function handleEditSubmit() {
  editError.value = ''
  const editErrors = validateSchema({
    eventId: 1, // already set, just validate rating+review
    rating: editRating.value,
    review: editReview.value,
  }, feedbackSchema)
  if (!isValid(editErrors)) {
    editError.value = Object.values(editErrors)[0]
    return
  }

  actionLoading.value = true
  try {
    await updateFeedback(editingFeedbackId.value, {
      rating: editRating.value,
      review: editReview.value.trim(),
    })
    isEditModalOpen.value = false
    
    // Reload feedback list
    const fb = await fetchFeedback()
    feedBackList.value = fb
  } catch (e) {
    editError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

// Form Fields
const selectedEventId = ref('')
const selectedRating = ref(5)
const reviewText = ref('')

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [fb, evs] = await Promise.all([
      fetchFeedback(),
      fetchEvents(),
    ])
    feedBackList.value = fb
    events.value = evs.filter(e => e.status !== 'cancelled')
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

function isEventOrganizer(eventId) {
  if (!authState.user) return false
  const e = events.value.find(item => item.id === eventId)
  return e ? (e.organizerId === authState.user.id) : false
}

onMounted(loadData)

async function handleSubmit() {
  submitError.value = ''
  submitSuccess.value = ''

  const errors = validateSchema({
    eventId: selectedEventId.value || '',
    rating: selectedRating.value,
    review: reviewText.value,
  }, feedbackSchema)
  if (!isValid(errors)) {
    submitError.value = Object.values(errors)[0]
    return
  }

  actionLoading.value = true
  try {
    await submitFeedback({
      eventId: Number(selectedEventId.value),
      rating: selectedRating.value,
      review: reviewText.value.trim(),
    })
    
    submitSuccess.value = 'Feedback submitted successfully!'
    selectedEventId.value = ''
    selectedRating.value = 5
    reviewText.value = ''
    
    // Reload feedback list
    const fb = await fetchFeedback()
    feedBackList.value = fb
  } catch (e) {
    submitError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete(fbId) {
  if (!window.confirm('Are you sure you want to delete this feedback?')) return

  actionLoading.value = true
  try {
    await deleteFeedback(fbId)
    feedBackList.value = feedBackList.value.filter(item => item.feedbackId !== fbId)
  } catch (e) {
    alert(errMsg(e))
  } finally {
    actionLoading.value = false
  }
}

function getEventTitle(eventId) {
  const e = events.value.find(item => item.id === eventId)
  return e ? e.title : `Event #${eventId}`
}

function renderStars(rating) {
  return '★'.repeat(rating) + '☆'.repeat(5 - rating)
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">
    <header>
      <h1 class="text-2xl font-bold text-slate-800">Community Feedback</h1>
      <p class="text-slate-500 text-sm">Read or submit reviews and ratings for university events.</p>
    </header>

    <div v-if="loading" class="py-12">
      <LoadingSpinner label="Loading feedback details..." />
    </div>

    <div v-else-if="error" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8">
      <EmptyState icon="⚠️" title="Error Loading Feedback" :description="error">
        <template #action>
          <button class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-lg" @click="loadData">
            Try Again
          </button>
        </template>
      </EmptyState>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <!-- Left Column: Submit Feedback Form (Authenticated only) -->
      <section class="md:col-span-1 space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
          <h2 class="text-base font-bold text-slate-800 pb-2 border-b border-slate-100">Submit a Review</h2>
          
          <div v-if="!authState.user" class="text-xs text-slate-500 leading-relaxed py-2">
            🔑 Please <router-link :to="{ name: 'login' }" class="text-indigo-600 font-bold hover:underline">sign in</router-link> to submit a review for an event you attended.
          </div>
          
          <form v-else @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Event Dropdown -->
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="eventSelect">Select Event</label>
              <select 
                id="eventSelect"
                v-model="selectedEventId"
                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500"
              >
                <option value="" disabled>Choose an event</option>
                <option v-for="e in events" :key="e.id" :value="e.id">
                  {{ e.title }}
                </option>
              </select>
            </div>

            <!-- Star Rating Dropdown -->
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="ratingSelect">Rating</label>
              <select 
                id="ratingSelect"
                v-model="selectedRating"
                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500"
              >
                <option :value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                <option :value="4">⭐⭐⭐⭐ (4/5)</option>
                <option :value="3">⭐⭐⭐ (3/5)</option>
                <option :value="2">⭐⭐ (2/5)</option>
                <option :value="1">⭐ (1/5)</option>
              </select>
            </div>

            <!-- Review Textbox -->
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="reviewText">Your Review</label>
              <textarea 
                id="reviewText"
                v-model="reviewText"
                rows="4"
                placeholder="Write your review here (min 10 characters)..."
                class="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"
              ></textarea>
            </div>

            <p v-if="submitError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded border border-red-100">{{ submitError }}</p>
            <p v-if="submitSuccess" class="text-xs text-emerald-600 bg-emerald-50 p-2.5 rounded border border-emerald-100">{{ submitSuccess }}</p>

            <button 
              type="submit" 
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-lg transition disabled:opacity-50"
              :disabled="actionLoading"
            >
              Submit Review
            </button>
          </form>
        </div>
      </section>

      <!-- Right Column: Feedback Listings -->
      <section class="md:col-span-2 space-y-4">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pb-2 border-b border-slate-50">
          <h2 class="text-lg font-bold text-slate-800">What Students Say</h2>
          <!-- Search & Filter Controls -->
          <div class="flex flex-wrap gap-2">
            <input 
              v-model="searchQuery"
              type="text"
              placeholder="Search reviews..."
              class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-indigo-500 w-full sm:w-36 lg:w-44"
            />
            <select 
              v-model="filterEventId"
              class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-indigo-500 w-full sm:w-auto max-w-[120px]"
            >
              <option value="">All Events</option>
              <option v-for="e in events" :key="e.id" :value="e.id">
                {{ e.title }}
              </option>
            </select>
            <select 
              v-model="filterRating"
              class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-indigo-500 w-full sm:w-auto"
            >
              <option value="">All Ratings</option>
              <option :value="5">5 Stars</option>
              <option :value="4">4 Stars</option>
              <option :value="3">3 Stars</option>
              <option :value="2">2 Stars</option>
              <option :value="1">1 Star</option>
            </select>
          </div>
        </div>

        <div v-if="filteredFeedback.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8 text-center text-slate-400">
          No feedback matches your filters. Be the first to review!
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="fb in filteredFeedback" 
            :key="fb.feedbackId" 
            class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 space-y-3 hover:shadow-md transition duration-150"
          >
            <div class="flex justify-between items-start gap-4">
              <div>
                <span class="text-xs font-bold text-indigo-600 tracking-wide block mb-0.5">
                  {{ getEventTitle(fb.eventId) }}
                </span>
                <span class="text-amber-500 font-bold text-sm tracking-wide">
                  {{ renderStars(fb.rating) }}
                </span>
              </div>
              <div class="text-right">
                <span class="font-bold text-slate-700 text-xs block">
                  {{ fb.user || 'Anonymous Student' }}
                </span>
                <span class="text-slate-400 text-[10px]">
                  {{ formatDate(fb.createdAt) }}
                </span>
              </div>
            </div>

            <p class="text-slate-600 text-xs leading-relaxed italic">
              "{{ fb.review }}"
            </p>

            <!-- Actions (Only author or admin or event organizer) -->
            <div v-if="authState.user && (fb.userId === authState.user.id || authState.user.role === 'admin' || isEventOrganizer(fb.eventId))" class="flex justify-end gap-3 pt-1">
              <button 
                v-if="fb.userId === authState.user.id"
                class="text-indigo-600 hover:text-indigo-800 text-xs font-bold transition"
                @click="openEditModal(fb)"
                :disabled="actionLoading"
              >
                Edit Review
              </button>
              <button 
                class="text-red-500 hover:text-red-700 text-xs font-bold transition"
                @click="handleDelete(fb.feedbackId)"
                :disabled="actionLoading"
              >
                Delete Review
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Edit Feedback Modal -->
    <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl border border-slate-100 max-w-md w-full p-6 space-y-4">
        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
          <h3 class="text-base font-bold text-slate-800">Edit Your Review</h3>
          <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        
        <form @submit.prevent="handleEditSubmit" class="space-y-4">
          <!-- Edit Star Rating -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="editRatingSelect">Rating</label>
            <select 
              id="editRatingSelect"
              v-model="editRating"
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500"
            >
              <option :value="5">⭐⭐⭐⭐⭐ (5/5)</option>
              <option :value="4">⭐⭐⭐⭐ (4/5)</option>
              <option :value="3">⭐⭐⭐ (3/5)</option>
              <option :value="2">⭐⭐ (2/5)</option>
              <option :value="1">⭐ (1/5)</option>
            </select>
          </div>

          <!-- Edit Review Text -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="editReviewText">Your Review</label>
            <textarea 
              id="editReviewText"
              v-model="editReview"
              rows="4"
              class="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"
            ></textarea>
          </div>

          <p v-if="editError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded border border-red-100">{{ editError }}</p>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button 
              type="button"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition"
              @click="isEditModalOpen = false"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition disabled:opacity-50"
              :disabled="actionLoading"
            >
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
