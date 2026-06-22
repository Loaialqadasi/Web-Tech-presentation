/**
 * Auth service — JWT-based authentication state for the SPA
 *
 * Stores the JWT in localStorage and the decoded user object alongside it.
 * Provides reactive `authState` so components can react to login/logout.
 */
import { reactive } from 'vue'
import {
  loginRequest,
  registerRequest,
  updateProfile as apiUpdateProfile,
  changePassword as apiChangePassword,
} from './api.js'

const STORAGE_KEY = 'freshdev-auth-user'
const TOKEN_KEY   = 'freshdev-auth-token'

function readStoredUser() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export const authState = reactive({
  user: readStoredUser(),
})

// ─── Demo accounts (shown on login page) ─────────────────────────────────────
export function getDemoAccounts() {
  return [
    { name: 'Campus Organizer', email: 'organizer@unievents.test', password: 'organizer123', role: 'organizer' },
    { name: 'Demo Student',     email: 'student@unievents.test',  password: 'student123',    role: 'student'    },
    { name: 'Loai AlQadasi',    email: 'loai@unievents.test',     password: 'loai123',       role: 'student'    },
    { name: 'Admin User',       email: 'admin@unievents.test',    password: 'admin123',      role: 'admin'      },
  ]
}

// ─── Login / Logout ──────────────────────────────────────────────────────────
export async function loginWithPassword(email, password) {
  const data = await loginRequest(email, password)
  localStorage.setItem(TOKEN_KEY, data.token)
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data.user))
  authState.user = data.user
  return data.user
}

export function logout() {
  authState.user = null
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(STORAGE_KEY)
}

export function isAuthenticated() {
  return Boolean(authState.user) && Boolean(localStorage.getItem(TOKEN_KEY))
}

export function isRoleAllowed(roles = []) {
  if (!roles.length) return true
  if (!authState.user) return false
  return roles.includes(authState.user.role)
}

// ─── Register ────────────────────────────────────────────────────────────────
export async function signUp(name, email, password, role = 'student', phone = '', bio = '', studentId = '', department = '') {
  const data = await registerRequest(name, email, password, role, phone, bio, studentId, department)
  localStorage.setItem(TOKEN_KEY, data.token)
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data.user))
  authState.user = data.user
  return { success: true, message: 'Account created successfully!', user: data.user }
}

// ─── Profile / Password ──────────────────────────────────────────────────────
export async function updateProfile(updates) {
  const data = await apiUpdateProfile(updates)
  if (data.token) localStorage.setItem(TOKEN_KEY, data.token)
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data.user))
  authState.user = data.user
  return { success: true, message: 'Profile updated successfully!', user: data.user }
}

export async function changePassword(currentPassword, newPassword) {
  await apiChangePassword(currentPassword, newPassword)
  return { success: true, message: 'Password updated successfully!' }
}

export function getCurrentUserId() {
  return authState.user?.id || null
}
