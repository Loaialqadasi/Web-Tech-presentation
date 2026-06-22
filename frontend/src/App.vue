<script setup>
/**
 * App.vue — Root component
 * Provides the public Navbar + Footer chrome around all routes.
 * Authenticated routes (dashboard, bookings, etc.) live inside this same
 * shell so navigation stays consistent across the SPA.
 */
import { computed, ref, provide } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { authState, logout } from './service/auth.js'

const route = useRoute()
const router = useRouter()
const showMobileMenu = ref(false)

const isAuthed = computed(() => Boolean(authState.user))
const userName = computed(() => authState.user?.name || '')
const userAvatar = computed(() => authState.user?.avatar || (userName.value ? userName.value[0] : '?'))
const userAvatarColor = computed(() => authState.user?.avatarColor || 'bg-indigo-500')
const userRole = computed(() => authState.user?.role || 'student')

// Provide a sign-out handler to child components
provide('signOut', () => {
  logout()
  router.push({ name: 'gallery' })
})

function navigate(name) {
  showMobileMenu.value = false
  router.push({ name })
}

function handleSignOut() {
  logout()
  showMobileMenu.value = false
  router.push({ name: 'gallery' })
}

const navItems = computed(() => {
  if (!isAuthed.value) {
    return [
      { name: 'gallery', label: 'Browse Events' },
    ]
  }
  const items = [
    { name: 'gallery', label: 'Events' },
  ]
  if (userRole.value === 'student') {
    items.push({ name: 'dashboard', label: 'Dashboard' })
    items.push({ name: 'forum', label: 'Forum' })
    items.push({ name: 'feedback', label: 'Feedback' })
    items.push({ name: 'calendar', label: 'Calendar' })
    items.push({ name: 'notifications', label: 'Notifications' })
    items.push({ name: 'booking-history', label: 'My Bookings' })
  }
  if (userRole.value === 'organizer' || userRole.value === 'admin') {
    items.push({ name: 'manage-events', label: 'Manage Events' })
    items.push({ name: 'forum', label: 'Forum' })
    items.push({ name: 'feedback', label: 'Feedback' })
  }
  return items
})
</script>

<template>
  <div class="app-shell">
    <!-- ─── Top Navigation ────────────────────────────────────────────── -->
    <header class="navbar">
      <div class="navbar__inner">
        <!-- Logo -->
        <router-link :to="{ name: 'gallery' }" class="navbar__brand" @click="showMobileMenu = false">
          <span class="navbar__logo">UE</span>
          <span class="navbar__brand-text">UniEvents</span>
        </router-link>

        <!-- Desktop nav -->
        <nav class="navbar__nav" aria-label="Primary">
          <router-link
            v-for="item in navItems"
            :key="item.name"
            :to="{ name: item.name }"
            class="navbar__link"
            :class="{ 'navbar__link--active': route.name === item.name }"
          >
            {{ item.label }}
          </router-link>
        </nav>

        <!-- Right side -->
        <div class="navbar__actions">
          <template v-if="isAuthed">
            <div class="navbar__user">
              <span class="navbar__avatar" :class="userAvatarColor">{{ userAvatar }}</span>
              <span class="navbar__user-name">{{ userName }}</span>
            </div>
            <button class="btn btn--ghost btn--sm" @click="handleSignOut">Sign out</button>
          </template>
          <template v-else>
            <router-link :to="{ name: 'login' }" class="btn btn--primary btn--sm">Sign in</router-link>
          </template>

          <!-- Mobile menu toggle -->
          <button
            class="navbar__toggle"
            :aria-expanded="showMobileMenu"
            aria-label="Toggle menu"
            @click="showMobileMenu = !showMobileMenu"
          >
            <span></span><span></span><span></span>
          </button>
        </div>
      </div>

      <!-- Mobile menu -->
      <nav v-if="showMobileMenu" class="navbar__mobile">
        <router-link
          v-for="item in navItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="navbar__mobile-link"
          @click="showMobileMenu = false"
        >{{ item.label }}</router-link>
      </nav>
    </header>

    <!-- ─── Main Content ──────────────────────────────────────────────── -->
    <main class="app-shell__main">
      <RouterView />
    </main>

    <!-- ─── Footer ────────────────────────────────────────────────────── -->
    <footer class="footer">
      <div class="footer__inner">
        <p class="footer__brand">UniEvents &middot; FreshDev Team 9</p>
        <p class="footer__meta">SECJ3483 Web Technology &middot; Phase 3 Demo</p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
.app-shell__main { flex: 1; }

/* ─── Navbar ─── */
.navbar {
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 50;
}
.navbar__inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  gap: 24px;
}
.navbar__brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  color: #0f172a;
  font-size: 18px;
}
.navbar__logo {
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: #fff;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
}
.navbar__brand-text { letter-spacing: -0.02em; }

.navbar__nav { display: flex; gap: 4px; flex: 1; justify-content: center; }
.navbar__link {
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  transition: background 0.15s ease, color 0.15s ease;
}
.navbar__link:hover { background: #f1f5f9; color: #0f172a; }
.navbar__link--active {
  background: #eef2ff;
  color: #4f46e5;
  font-weight: 600;
}

.navbar__actions { display: flex; align-items: center; gap: 12px; }
.navbar__user { display: flex; align-items: center; gap: 8px; }
.navbar__avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 13px;
  font-weight: 700;
}
.navbar__user-name { font-size: 14px; font-weight: 500; color: #0f172a; }

.navbar__toggle {
  display: none;
  background: none;
  border: 0;
  flex-direction: column;
  gap: 4px;
  padding: 6px;
  cursor: pointer;
}
.navbar__toggle span {
  display: block;
  width: 22px;
  height: 2px;
  background: #0f172a;
  border-radius: 1px;
}

.navbar__mobile {
  display: none;
  flex-direction: column;
  padding: 8px 24px 16px;
  border-top: 1px solid #f1f5f9;
}
.navbar__mobile-link {
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 15px;
  color: #475569;
}
.navbar__mobile-link:hover { background: #f1f5f9; color: #0f172a; }

/* ─── Buttons ─── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 8px;
  border: 0;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
  text-decoration: none;
}
.btn--sm { padding: 7px 14px; font-size: 13px; }
.btn--primary { background: #4f46e5; color: #fff; }
.btn--primary:hover { background: #4338ca; }
.btn--ghost { background: #fff; color: #475569; border: 1px solid #d1d5db; }
.btn--ghost:hover { background: #f8fafc; border-color: #94a3b8; }

/* ─── Footer ─── */
.footer {
  background: #0f172a;
  color: #cbd5e1;
  padding: 24px;
  margin-top: auto;
}
.footer__inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.footer__brand { margin: 0; font-size: 14px; font-weight: 600; color: #fff; }
.footer__meta { margin: 0; font-size: 13px; color: #94a3b8; }

/* ─── Mobile ─── */
@media (max-width: 768px) {
  .navbar__nav { display: none; }
  .navbar__user-name { display: none; }
  .navbar__toggle { display: flex; }
  .navbar__mobile { display: flex; }
}
</style>
