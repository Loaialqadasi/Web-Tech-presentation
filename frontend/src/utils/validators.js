/**
 * Form validation utilities
 * Loai AlQadasi — UI/UX & Testing Lead
 *
 * Shared validation patterns used across all modules (auth, bookings, forum,
 * feedback, etc.) so every form behaves consistently. Each rule returns an
 * empty string on success or an error message on failure.
 */

// ─── Field Rules ──────────────────────────────────────────────────────────────

export const required = (value, label = 'This field') => {
  if (value === null || value === undefined) return `${label} is required.`
  if (typeof value === 'string' && value.trim() === '') return `${label} is required.`
  if (Array.isArray(value) && value.length === 0) return `${label} is required.`
  return ''
}

export const minLength = (value, n, label = 'This field') => {
  if (!value) return ''
  if (String(value).trim().length < n) return `${label} must be at least ${n} characters.`
  return ''
}

export const maxLength = (value, n, label = 'This field') => {
  if (!value) return ''
  if (String(value).trim().length > n) return `${label} must be at most ${n} characters.`
  return ''
}

export const email = (value) => {
  if (!value) return ''
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!re.test(String(value).trim())) return 'Please enter a valid email address.'
  return ''
}

export const password = (value) => {
  if (!value) return ''
  if (value.length < 6) return 'Password must be at least 6 characters.'
  return ''
}

export const integerRange = (value, min, max, label = 'Value') => {
  const n = Number(value)
  if (Number.isNaN(n)) return `${label} must be a number.`
  if (!Number.isInteger(n)) return `${label} must be a whole number.`
  if (n < min || n > max) return `${label} must be between ${min} and ${max}.`
  return ''
}

export const rating = (value) => {
  const n = Number(value)
  if (Number.isNaN(n) || n < 1 || n > 5) return 'Rating must be between 1 and 5.'
  return ''
}

export const futureDate = (value, label = 'Date') => {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return `${label} is not valid.`
  if (d.getTime() < new Date().setHours(0, 0, 0, 0)) return `${label} cannot be in the past.`
  return ''
}

// ─── Composite Validators ────────────────────────────────────────────────────

/**
 * Run a list of rules against a single value.
 * Returns the first non-empty error message (or '' if all pass).
 */
export const runRules = (value, rules = []) => {
  for (const rule of rules) {
    const msg = typeof rule === 'function' ? rule(value) : rule
    if (msg) return msg
  }
  return ''
}

/**
 * Validate an object of fields against a schema.
 * Schema shape: { fieldName: [rule1, rule2, ...], ... }
 * Returns: { fieldName: 'errorMessage', ... } (only invalid fields)
 */
export const validateSchema = (values, schema) => {
  const errors = {}
  for (const [field, rules] of Object.entries(schema)) {
    const msg = runRules(values[field], rules)
    if (msg) errors[field] = msg
  }
  return errors
}

/**
 * True if errors object has no keys.
 */
export const isValid = (errors) => Object.keys(errors).length === 0

// ─── Pre-built Schemas (reusable across modules) ──────────────────────────────

export const loginSchema = {
  email:    [required, email],
  password: [required, password],
}

export const registerSchema = {
  name:     [(v) => required(v, 'Name'), (v) => minLength(v, 2, 'Name')],
  email:    [required, email],
  password: [required, password],
}

export const eventSchema = {
  title:       [(v) => required(v, 'Title'), (v) => minLength(v, 5, 'Title')],
  description: [(v) => required(v, 'Description'), (v) => minLength(v, 20, 'Description')],
  category:    [(v) => required(v, 'Category')],
  date:        [(v) => required(v, 'Date'), (v) => futureDate(v, 'Date')],
  venue:       [(v) => required(v, 'Venue')],
  capacity:    [(v) => required(v, 'Capacity'), (v) => integerRange(v, 1, 10000, 'Capacity')],
}

export const feedbackSchema = {
  eventId: [(v) => required(v, 'Event')],
  rating:  [rating],
  review:  [(v) => required(v, 'Review'), (v) => minLength(v, 10, 'Review')],
}

export const forumPostSchema = {
  title:   [(v) => required(v, 'Title'), (v) => minLength(v, 5, 'Title')],
  content: [(v) => required(v, 'Content'), (v) => minLength(v, 20, 'Content')],
}

export const bookingSchema = {
  eventId:        [(v) => required(v, 'Event')],
  ticketQuantity: [(v) => required(v, 'Ticket quantity'), (v) => integerRange(v, 1, 10, 'Ticket quantity')],
}

// ─── Payment card validation rules ──────────────────────────────────────────
export const cardNameRequired = (value) => {
  if (!value || !value.trim()) return 'Cardholder name is required.'
  return ''
}

export const cardNumber16 = (value) => {
  if (!value) return 'Card number is required.'
  const clean = String(value).replace(/\s+/g, '')
  if (!/^\d{16}$/.test(clean)) return 'Card number must be 16 digits.'
  return ''
}

export const expiryFormat = (value) => {
  if (!value) return 'Expiry date is required.'
  if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(value)) return 'Expiry must be in MM/YY format.'
  return ''
}

export const cvvFormat = (value) => {
  if (!value) return 'CVV is required.'
  if (!/^\d{3}$/.test(value)) return 'CVV must be 3 digits.'
  return ''
}

export const paymentSchema = {
  cardName:   [cardNameRequired],
  cardNumber: [cardNumber16],
  cardExpiry: [expiryFormat],
  cardCvv:    [cvvFormat],
}
