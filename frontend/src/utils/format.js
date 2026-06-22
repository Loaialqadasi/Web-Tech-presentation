/**
 * Date / time / currency formatting helpers
 * Shared across all modules so dates render consistently.
 */

/** Format a MySQL DATE/DATETIME string as "15 Jul 2026". */
export function formatDate(dateStr, opts = { day: 'numeric', month: 'short', year: 'numeric' }) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-GB', opts)
}

/** Format a MySQL DATE as "15 July 2026, 09:00 AM". */
export function formatDateTime(dateStr, timeStr = '') {
  const date = formatDate(dateStr, { day: 'numeric', month: 'long', year: 'numeric' })
  return timeStr ? `${date}, ${timeStr}` : date
}

/** Returns "Today" / "Tomorrow" / "in 3 days" / "2 days ago" for relative display. */
export function relativeDay(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return ''
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const target = new Date(d)
  target.setHours(0, 0, 0, 0)
  const diffDays = Math.round((target - today) / (1000 * 60 * 60 * 24))

  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return 'Tomorrow'
  if (diffDays === -1) return 'Yesterday'
  if (diffDays > 1 && diffDays <= 7) return `in ${diffDays} days`
  if (diffDays < -1 && diffDays >= -7) return `${Math.abs(diffDays)} days ago`
  return formatDate(dateStr, { day: 'numeric', month: 'short' })
}

/** Format "RM 25" → number 25; "Free" → 0. */
export function parsePrice(priceStr) {
  if (!priceStr) return 0
  if (typeof priceStr === 'number') return priceStr
  const s = String(priceStr)
  if (/free/i.test(s)) return 0
  const m = s.match(/RM\s*(\d+(?:\.\d+)?)/i)
  return m ? parseFloat(m[1]) : 0
}

/** Format a number as Malaysian Ringgit: "RM 25.00". */
export function formatCurrency(amount) {
  const n = Number(amount) || 0
  return `RM ${n.toFixed(2)}`
}

/** Truncate a long string with ellipsis. */
export function truncate(text, n = 120) {
  if (!text) return ''
  return text.length > n ? text.slice(0, n - 1) + '…' : text
}

/** Get initials from a name: "Siti Nur Fathiyyah" → "SN". */
export function initials(name) {
  if (!name) return ''
  return name
    .split(' ')
    .filter((w) => w.length > 0)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

/** Map a category name to its Tailwind color class (for badges / accents). */
const CATEGORY_COLORS = {
  Technology:   { bg: 'bg-blue-100',   text: 'text-blue-700',   dot: 'bg-blue-500'   },
  Career:       { bg: 'bg-amber-100',  text: 'text-amber-700',  dot: 'bg-amber-500'  },
  Academic:     { bg: 'bg-purple-100', text: 'text-purple-700', dot: 'bg-purple-500' },
  Workshop:     { bg: 'bg-sky-100',    text: 'text-sky-700',    dot: 'bg-sky-500'    },
  Seminar:      { bg: 'bg-violet-100', text: 'text-violet-700', dot: 'bg-violet-500' },
  Sports:       { bg: 'bg-emerald-100',text: 'text-emerald-700',dot: 'bg-emerald-500'},
  Cultural:     { bg: 'bg-fuchsia-100',text: 'text-fuchsia-700',dot: 'bg-fuchsia-500'},
  'Community Service': { bg: 'bg-lime-100', text: 'text-lime-700', dot: 'bg-lime-500' },
  Arts:         { bg: 'bg-rose-100',   text: 'text-rose-700',   dot: 'bg-rose-500'   },
  Entertainment:{ bg: 'bg-cyan-100',   text: 'text-cyan-700',   dot: 'bg-cyan-500'   },
  All:          { bg: 'bg-slate-100',  text: 'text-slate-700',  dot: 'bg-slate-500'  },
}

export function categoryColor(category) {
  return CATEGORY_COLORS[category] || CATEGORY_COLORS.All
}

/** Calculate % of seats booked (used for progress bars). */
export function bookingPercent(capacity, availableSeats) {
  if (!capacity || capacity <= 0) return 0
  const booked = capacity - availableSeats
  return Math.min(100, Math.round((booked / capacity) * 100))
}
