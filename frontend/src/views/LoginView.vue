<script setup>
/**
 * LoginView — minimal auth gate
 * Owner: Siti Nur Fathiyyah (Team Lead) — placeholder, can be enhanced.
 *
 * Uses shared validation utility for consistency.
 */
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { loginWithPassword, getDemoAccounts, authState } from '../service/auth.js'
import { validateSchema, isValid, loginSchema } from '../utils/validators.js'

const router = useRouter()
const route = useRoute()

const email    = ref('')
const password = ref('')
const errors   = ref({})
const serverError = ref('')
const loading  = ref(false)

const demoAccounts = getDemoAccounts()

async function onSubmit() {
  serverError.value = ''
  errors.value = validateSchema({ email: email.value, password: password.value }, loginSchema)
  if (!isValid(errors.value)) return

  loading.value = true
  try {
    const user = await loginWithPassword(email.value, password.value)
    const redirect = route.query.redirect
    if (redirect && typeof redirect === 'string') {
      router.push(redirect)
    } else {
      router.push({ name: user.role === 'organizer' || user.role === 'admin' ? 'manage-events' : 'dashboard' })
    }
  } catch (e) {
    serverError.value = e.message || 'Invalid email or password.'
  } finally {
    loading.value = false
  }
}

function fillDemo(acc) {
  email.value = acc.email
  password.value = acc.password
}

// If already logged in, redirect away
if (authState.user) {
  router.replace({ name: authState.user.role === 'organizer' ? 'manage-events' : 'dashboard' })
}
</script>

<template>
  <div class="login">
    <div class="login__card">
      <h1 class="login__title">Sign in to UniEvents</h1>
      <p class="login__subtitle">Welcome back — enter your credentials to continue.</p>

      <form @submit.prevent="onSubmit" class="login__form">
        <div class="field">
          <label for="email" class="field__label">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="field__input"
            :class="{ 'field__input--error': errors.email }"
            autocomplete="email"
            placeholder="you@example.com"
          />
          <p v-if="errors.email" class="field__error">{{ errors.email }}</p>
        </div>

        <div class="field">
          <label for="password" class="field__label">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="field__input"
            :class="{ 'field__input--error': errors.password }"
            autocomplete="current-password"
            placeholder="At least 6 characters"
          />
          <p v-if="errors.password" class="field__error">{{ errors.password }}</p>
        </div>

        <p v-if="serverError" class="login__server-error">{{ serverError }}</p>

        <button type="submit" class="btn btn--primary btn--block" :disabled="loading">
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <div class="login__demo">
        <p class="login__demo-title">Demo accounts (click to fill):</p>
        <ul class="login__demo-list">
          <li v-for="acc in demoAccounts" :key="acc.email">
            <button type="button" class="login__demo-btn" @click="fillDemo(acc)">
              <span class="login__demo-role">{{ acc.role }}</span>
              <span class="login__demo-email">{{ acc.email }}</span>
            </button>
          </li>
        </ul>
      </div>

      <p class="login__footer">
        Don't have an account?
        <router-link :to="{ name: 'register' }" class="login__link">Sign up here</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.login {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}
.login__card {
  background: #fff;
  border-radius: 16px;
  padding: 36px 32px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25);
}
.login__title { margin: 0 0 6px; font-size: 24px; font-weight: 700; color: #0f172a; }
.login__subtitle { margin: 0 0 24px; font-size: 14px; color: #64748b; }
.login__form { display: flex; flex-direction: column; gap: 16px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 13px; font-weight: 600; color: #334155; }
.field__input {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  color: #0f172a;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.field__input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18); }
.field__input--error { border-color: #ef4444; }
.field__error { margin: 0; font-size: 12px; color: #ef4444; }
.login__server-error {
  margin: 0;
  padding: 10px 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 13px;
  color: #b91c1c;
}
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 11px 18px;
  border-radius: 8px;
  border: 0;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}
.btn--primary { background: #4f46e5; color: #fff; }
.btn--primary:hover:not(:disabled) { background: #4338ca; }
.btn--primary:disabled { background: #c7d2fe; cursor: not-allowed; }
.btn--block { width: 100%; }
.login__demo { margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
.login__demo-title { margin: 0 0 8px; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.login__demo-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.login__demo-btn {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f8fafc;
  cursor: pointer;
  font-size: 12px;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.login__demo-btn:hover { border-color: #c7d2fe; background: #eef2ff; }
.login__demo-role { font-weight: 700; color: #4f46e5; text-transform: capitalize; }
.login__demo-email { color: #64748b; font-family: ui-monospace, SFMono-Regular, monospace; }
.login__footer { margin: 20px 0 0; text-align: center; font-size: 13px; color: #64748b; }
.login__link { color: #4f46e5; font-weight: 600; }
.login__link:hover { text-decoration: underline; }
</style>
