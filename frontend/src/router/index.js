import { createRouter, createWebHistory } from 'vue-router'
import { isAuthenticated, isRoleAllowed, authState } from '../service/auth.js'

// ─── Loai's modules (full implementations) ─────────────────────────────────
import GalleryView   from '../views/GalleryView.vue'
import DashboardView from '../views/DashboardView.vue'

// ─── Other team members' modules (placeholders so the SPA runs end-to-end) ─
// These are intentionally minimal — Fatema / Siti / Amir will swap them out
// with their own implementations following the same patterns Loai established.
import LoginView         from '../views/LoginView.vue'
import RegisterView      from '../views/RegisterView.vue'
import EventDetailsView  from '../views/EventDetailsView.vue'
import BookingHistoryView from '../views/BookingHistoryView.vue'
import BookingReviewView from '../views/BookingReviewView.vue'
import PaymentView       from '../views/PaymentView.vue'
import BookingSuccessView from '../views/BookingSuccessView.vue'
import ForumView         from '../views/ForumView.vue'
import ForumDetailView   from '../views/ForumDetailView.vue'
import FeedbackView      from '../views/FeedbackView.vue'
import CalendarView      from '../views/CalendarView.vue'
import NotificationsView from '../views/NotificationsView.vue'
import EventManagementView from '../views/EventManagementView.vue'

const routes = [
  // ── Public ──
  { path: '/', redirect: '/gallery' },
  {
    path: '/gallery',
    name: 'gallery',
    component: GalleryView,
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
  },
  {
    path: '/register',
    name: 'register',
    component: RegisterView,
  },

  // ── Authenticated: student / organizer ──
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true, roles: ['student', 'organizer', 'admin'] },
  },
  {
    path: '/events/:id',
    name: 'event-details',
    component: EventDetailsView,
    // Public — anyone can view event details; booking requires auth (handled in-component)
  },
  {
    path: '/bookings',
    name: 'booking-history',
    component: BookingHistoryView,
    meta: { requiresAuth: true, roles: ['student'] },
  },
  {
    path: '/booking/review',
    name: 'booking-review',
    component: BookingReviewView,
    meta: { requiresAuth: true, roles: ['student'] },
  },
  {
    path: '/checkout',
    name: 'checkout',
    component: PaymentView,
    meta: { requiresAuth: true, roles: ['student'] },
  },
  {
    path: '/booking/success',
    name: 'booking-success',
    component: BookingSuccessView,
    meta: { requiresAuth: true, roles: ['student'] },
  },
  {
    path: '/forum',
    name: 'forum',
    component: ForumView,
    meta: { requiresAuth: true },
  },
  {
    path: '/forum/:id',
    name: 'forum-detail',
    component: ForumDetailView,
    meta: { requiresAuth: true },
  },
  {
    path: '/feedback',
    name: 'feedback',
    component: FeedbackView,
    meta: { requiresAuth: true },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: CalendarView,
    meta: { requiresAuth: true, roles: ['student'] },
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: NotificationsView,
    meta: { requiresAuth: true, roles: ['student'] },
  },

  // ── Organizer / Admin ──
  {
    path: '/manage-events',
    name: 'manage-events',
    component: EventManagementView,
    meta: { requiresAuth: true, roles: ['organizer', 'admin'] },
  },

  // ── Legacy aliases ──
  { path: '/events', redirect: '/gallery' },
  { path: '/manage-events/:id', redirect: (to) => `/events/${to.params.id}` },

  // ── 404 catch-all ──
  { path: '/:pathMatch(.*)*', redirect: '/gallery' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

// ─── Navigation guard ────────────────────────────────────────────────────────
router.beforeEach((to) => {
  // Already logged in? Skip login/register page.
  if ((to.name === 'login' || to.name === 'register') && isAuthenticated()) {
    const role = authState.user?.role
    return { name: role === 'organizer' || role === 'admin' ? 'manage-events' : 'dashboard' }
  }

  // Requires auth?
  if (to.meta?.requiresAuth && !isAuthenticated()) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Role check?
  if (to.meta?.roles && !isRoleAllowed(to.meta.roles)) {
    return { name: 'gallery' }
  }

  return true
})

export default router
