/**
 * API client — Axios instance with JWT interceptor
 *
 * Talks to the PHP Slim 4 backend. During local dev, Vite proxies /api/* to
 * http://localhost:8080 (see vite.config.js). In production, set
 * VITE_API_BASE_URL to the deployed backend URL.
 */
import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_BASE_URL || ''

const api = axios.create({
  baseURL: API_BASE,
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
})

// ─── Request: attach JWT ─────────────────────────────────────────────────────
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('freshdev-auth-token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// ─── Response: auto-logout on 401 ────────────────────────────────────────────
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('freshdev-auth-token')
      localStorage.removeItem('freshdev-auth-user')
      // Use router for SPA navigation instead of full page reload
      // Lazy import avoids circular dependency at module init time
      import('../router/index.js').then(({ default: router }) => {
        if (router.currentRoute.value.name !== 'login') {
          router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
        }
      })
    }
    return Promise.reject(error)
  }
)

function errMsg(error) {
  return error.response?.data?.message || error.message || 'An unexpected error occurred.'
}

// ─── Categories ──────────────────────────────────────────────────────────────
const DEFAULT_CATEGORIES = [
  'All',
  'Technology',
  'Career',
  'Academic',
  'Workshop',
  'Seminar',
  'Sports',
  'Cultural',
  'Community Service',
  'Arts',
  'Entertainment',
]

export async function fetchEventCategories() {
  try {
    const res = await api.get('/api/events/categories/list')
    return res.data.categories || DEFAULT_CATEGORIES
  } catch {
    return DEFAULT_CATEGORIES
  }
}

// ─── Events ──────────────────────────────────────────────────────────────────
export async function fetchEvents(filters = {}) {
  const params = {}
  if (filters.category && filters.category !== 'All') params.category = filters.category
  if (filters.search) params.search = filters.search
  if (filters.status) params.status = filters.status
  const res = await api.get('/api/events', { params })
  return res.data.events || []
}

export async function fetchEventById(id) {
  const res = await api.get(`/api/events/${id}`)
  return res.data.event
}

export async function createEvent(payload) {
  const res = await api.post('/api/events', payload)
  return res.data
}

export async function updateEvent(id, payload) {
  const res = await api.put(`/api/events/${id}`, payload)
  return res.data
}

export async function deleteEvent(id) {
  const res = await api.delete(`/api/events/${id}`)
  return res.data
}

export async function fetchEventAgenda(eventId) {
  const res = await api.get(`/api/events/${eventId}/agenda`)
  return res.data.agendaItems || []
}

export async function createEventAgendaItem(eventId, payload) {
  const res = await api.post(`/api/events/${eventId}/agenda`, payload)
  return res.data
}

export async function updateEventAgendaItem(eventId, agendaId, payload) {
  const res = await api.put(`/api/events/${eventId}/agenda/${agendaId}`, payload)
  return res.data
}

export async function deleteEventAgendaItem(eventId, agendaId) {
  const res = await api.delete(`/api/events/${eventId}/agenda/${agendaId}`)
  return res.data
}

// ─── Bookings ────────────────────────────────────────────────────────────────
export async function fetchBookedEvents(userId) {
  const res = await api.get(`/api/bookings/user/${userId}`)
  return res.data.bookings || []
}

export async function bookEvent(eventId, ticketQuantity = 1) {
  const res = await api.post('/api/bookings', { eventId, ticketQuantity })
  return res.data
}

export async function updateBooking(id, data) {
  const res = await api.put(`/api/bookings/${id}`, data)
  return res.data
}

export async function cancelBooking(id) {
  const res = await api.delete(`/api/bookings/${id}`)
  return res.data
}

// ─── Payments ────────────────────────────────────────────────────────────────
export async function createPayment(bookingId, paymentMethod = 'card') {
  const res = await api.post('/api/payments', { bookingId, paymentMethod })
  return res.data
}

export async function fetchPayments(userId) {
  const res = await api.get(`/api/payments/user/${userId}`)
  return res.data.payments || []
}

// ─── Notifications ───────────────────────────────────────────────────────────
export async function fetchNotifications(userId) {
  const res = await api.get(`/api/notifications/user/${userId}`)
  return res.data
}

export async function markNotificationAsRead(id) {
  const res = await api.put(`/api/notifications/${id}/read`)
  return res.data
}

export async function markAllNotificationsRead(userId) {
  const res = await api.put(`/api/notifications/read-all/${userId}`)
  return res.data
}

// ─── Calendar ────────────────────────────────────────────────────────────────
export async function fetchCalendarEvents(userId) {
  const res = await api.get(`/api/calendar/user/${userId}`)
  return res.data
}

export async function syncCalendar() {
  const res = await api.post('/api/calendar/sync')
  return res.data
}

// ─── Forum ───────────────────────────────────────────────────────────────────
export async function fetchForumPosts(eventId) {
  const params = eventId ? { eventId } : {}
  const res = await api.get('/api/forum/posts', { params })
  return res.data.posts || []
}

export async function fetchForumPost(id) {
  const res = await api.get(`/api/forum/posts/${id}`)
  return res.data.post
}

export async function createForumPost(payload) {
  const res = await api.post('/api/forum/posts', payload)
  return res.data
}

export async function deleteForumPost(id) {
  const res = await api.delete(`/api/forum/posts/${id}`)
  return res.data
}

export async function updateForumPost(id, payload) {
  const res = await api.put(`/api/forum/posts/${id}`, payload)
  return res.data
}

export async function fetchComments(postId) {
  const res = await api.get(`/api/forum/posts/${postId}/comments`)
  return res.data.comments || []
}

export async function createComment(payload) {
  const res = await api.post('/api/forum/comments', payload)
  return res.data
}

export async function deleteComment(id) {
  const res = await api.delete(`/api/forum/comments/${id}`)
  return res.data
}

export async function updateComment(id, payload) {
  const res = await api.put(`/api/forum/comments/${id}`, payload)
  return res.data
}

// ─── Feedback ────────────────────────────────────────────────────────────────
export async function fetchFeedback(eventId) {
  const url = eventId ? `/api/feedback/event/${eventId}` : '/api/feedback'
  const res = await api.get(url)
  return res.data.feedback || []
}

export async function submitFeedback(payload) {
  const res = await api.post('/api/feedback', payload)
  return res.data
}

export async function deleteFeedback(id) {
  const res = await api.delete(`/api/feedback/${id}`)
  return res.data
}

export async function updateFeedback(id, payload) {
  const res = await api.put(`/api/feedback/${id}`, payload)
  return res.data
}

// ─── Dashboard ───────────────────────────────────────────────────────────────
export async function fetchDashboard(userId) {
  const res = await api.get(`/api/dashboard/${userId}`)
  return res.data.dashboard
}

// ─── Auth ────────────────────────────────────────────────────────────────────
export async function loginRequest(email, password) {
  const res = await api.post('/api/auth/login', { email, password })
  return res.data
}

export async function registerRequest(name, email, password, role = 'student', phone = '', bio = '', studentId = '', department = '') {
  const res = await api.post('/api/auth/register', { name, email, password, role, phone, bio, studentId, department })
  return res.data
}

export async function fetchProfile() {
  const res = await api.get('/api/auth/profile')
  return res.data.user
}

export async function updateProfile(updates) {
  const res = await api.put('/api/auth/profile', updates)
  return res.data
}

export async function changePassword(currentPassword, newPassword) {
  const res = await api.post('/api/auth/change-password', { currentPassword, newPassword })
  return res.data
}

export { errMsg }
export default api
