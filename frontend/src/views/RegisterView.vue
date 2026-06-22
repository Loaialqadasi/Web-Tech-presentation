<script setup>
/**
 * RegisterView — sign up form for new users
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { signUp, authState } from '../service/auth.js'
import { validateSchema, isValid, registerSchema } from '../utils/validators.js'

const router = useRouter()

const name        = ref('')
const email       = ref('')
const password    = ref('')
const role        = ref('student')
const phone       = ref('')
const bio         = ref('')
const studentId   = ref('')
const department  = ref('')
const errors      = ref({})
const serverError = ref('')
const loading     = ref(false)

async function onSubmit() {
  serverError.value = ''
  
  // Validate name, email, password using the shared schema
  errors.value = validateSchema({ name: name.value, email: email.value, password: password.value }, registerSchema)
  if (!isValid(errors.value)) return

  loading.value = true
  try {
    // If not a student, clear student ID to keep database clean
    const actualStudentId = role.value === 'student' ? studentId.value : ''
    
    const res = await signUp(
      name.value, 
      email.value, 
      password.value, 
      role.value, 
      phone.value, 
      bio.value, 
      actualStudentId, 
      department.value
    )
    
    // Redirect based on user role
    const userRole = res.user.role
    if (userRole === 'organizer' || userRole === 'admin') {
      router.push({ name: 'manage-events' })
    } else {
      router.push({ name: 'dashboard' })
    }
  } catch (e) {
    serverError.value = e.message || 'Failed to create account.'
  } finally {
    loading.value = false
  }
}

// If already logged in, redirect away
if (authState.user) {
  router.replace({ name: authState.user.role === 'organizer' ? 'manage-events' : 'dashboard' })
}
</script>

<template>
  <div class="register">
    <div class="register__card">
      <h1 class="register__title">Create an Account</h1>
      <p class="register__subtitle">Join UniEvents to discover and organize events.</p>

      <form @submit.prevent="onSubmit" class="register__form">
        <!-- 2 Column Grid for Basic Fields -->
        <div class="form-grid">
          <!-- Name Field -->
          <div class="field">
            <label for="name" class="field__label">Full Name <span class="required">*</span></label>
            <input
              id="name"
              v-model="name"
              type="text"
              class="field__input"
              :class="{ 'field__input--error': errors.name }"
              autocomplete="name"
              placeholder="e.g. Siti Nur"
            />
            <p v-if="errors.name" class="field__error">{{ errors.name }}</p>
          </div>

          <!-- Email Field -->
          <div class="field">
            <label for="email" class="field__label">Email Address <span class="required">*</span></label>
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

          <!-- Password Field -->
          <div class="field">
            <label for="password" class="field__label">Password <span class="required">*</span></label>
            <input
              id="password"
              v-model="password"
              type="password"
              class="field__input"
              :class="{ 'field__input--error': errors.password }"
              autocomplete="new-password"
              placeholder="At least 6 characters"
            />
            <p v-if="errors.password" class="field__error">{{ errors.password }}</p>
          </div>

          <!-- Role Selector -->
          <div class="field">
            <label for="role" class="field__label">Register as</label>
            <select id="role" v-model="role" class="field__input">
              <option value="student">Student (Browse & Book)</option>
              <option value="organizer">Event Organizer</option>
            </select>
          </div>
        </div>

        <div class="divider">Optional Profile Details</div>

        <!-- 2 Column Grid for Optional Fields -->
        <div class="form-grid">
          <!-- Phone Field -->
          <div class="field">
            <label for="phone" class="field__label">Phone Number</label>
            <input
              id="phone"
              v-model="phone"
              type="tel"
              class="field__input"
              placeholder="e.g. +60123456789"
            />
          </div>

          <!-- Department Field -->
          <div class="field">
            <label for="department" class="field__label">Department</label>
            <input
              id="department"
              v-model="department"
              type="text"
              class="field__input"
              placeholder="e.g. Computer Science"
            />
          </div>

          <!-- Student ID (shown only for students) -->
          <div v-if="role === 'student'" class="field field--full-width-mobile">
            <label for="studentId" class="field__label">Student ID</label>
            <input
              id="studentId"
              v-model="studentId"
              type="text"
              class="field__input"
              placeholder="e.g. A23CS0001"
            />
          </div>
        </div>

        <!-- Bio Field -->
        <div class="field">
          <label for="bio" class="field__label">Biography</label>
          <textarea
            id="bio"
            v-model="bio"
            rows="3"
            class="field__input field__textarea"
            placeholder="Tell us a bit about yourself..."
          ></textarea>
        </div>

        <!-- Server Error Alert -->
        <p v-if="serverError" class="register__server-error">{{ serverError }}</p>

        <!-- Submit Button -->
        <button type="submit" class="btn btn--primary btn--block" :disabled="loading">
          {{ loading ? 'Creating account…' : 'Sign up' }}
        </button>
      </form>

      <!-- Footer Link -->
      <p class="register__footer">
        Already have an account?
        <router-link :to="{ name: 'login' }" class="register__link">Sign in instead</router-link>
      </p>
    </div>
  </div>
</template>

<style scoped>
.register {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}
.register__card {
  background: #fff;
  border-radius: 16px;
  padding: 36px 32px;
  width: 100%;
  max-width: 560px; /* Slightly wider to support 2-column input layouts */
  box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25);
}
.register__title { margin: 0 0 6px; font-size: 24px; font-weight: 700; color: #0f172a; }
.register__subtitle { margin: 0 0 24px; font-size: 14px; color: #64748b; }
.register__form { display: flex; flex-direction: column; gap: 16px; }

/* Grid Layout for Forms */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 480px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.divider {
  margin: 8px 0;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  color: #94a3b8;
  letter-spacing: 0.05em;
  display: flex;
  align-items: center;
  gap: 8px;
}
.divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: #e2e8f0;
}

.field { display: flex; flex-direction: column; gap: 6px; }
.field__label { font-size: 13px; font-weight: 600; color: #334155; }
.required { color: #ef4444; }
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
.field__textarea { resize: vertical; font-family: inherit; }

.register__server-error {
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
.register__footer { margin: 20px 0 0; text-align: center; font-size: 13px; color: #64748b; }
.register__link { color: #4f46e5; font-weight: 600; }
.register__link:hover { text-decoration: underline; }
</style>
