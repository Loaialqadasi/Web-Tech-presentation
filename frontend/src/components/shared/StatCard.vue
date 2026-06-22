<script setup>
/**
 * StatCard — small KPI tile for the User Dashboard
 * Owner: Loai AlQadasi (UI/UX Lead)
 *
 * Props:
 *   - label: short metric name (e.g. "Total Bookings")
 *   - value: number or string
 *   - icon:  inline SVG path data (single <path d="...">)
 *   - color: 'indigo' | 'emerald' | 'amber' | 'rose' | 'cyan'
 */
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon:  { type: String, default: '' },
  color: { type: String, default: 'indigo' },
  hint:  { type: String, default: '' },
})

const colorMap = {
  indigo:  { bg: 'bg-indigo-50',  fg: 'text-indigo-600',  ring: 'ring-indigo-100'  },
  emerald: { bg: 'bg-emerald-50', fg: 'text-emerald-600', ring: 'ring-emerald-100' },
  amber:   { bg: 'bg-amber-50',   fg: 'text-amber-600',   ring: 'ring-amber-100'   },
  rose:    { bg: 'bg-rose-50',    fg: 'text-rose-600',    ring: 'ring-rose-100'    },
  cyan:    { bg: 'bg-cyan-50',    fg: 'text-cyan-600',    ring: 'ring-cyan-100'    },
}
</script>

<template>
  <div class="stat-card" :class="colorMap[color]?.ring">
    <div class="stat-card__icon" :class="[colorMap[color]?.bg, colorMap[color]?.fg]">
      <svg v-if="icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
        <path :d="icon" fill="currentColor" />
      </svg>
    </div>
    <div class="stat-card__body">
      <div class="stat-card__value">{{ value }}</div>
      <div class="stat-card__label">{{ label }}</div>
      <div v-if="hint" class="stat-card__hint">{{ hint }}</div>
    </div>
  </div>
</template>

<style scoped>
.stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.stat-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}
.stat-card__icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-card__body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.stat-card__value { font-size: 24px; font-weight: 700; color: #0f172a; line-height: 1.1; }
.stat-card__label { font-size: 13px; color: #64748b; font-weight: 500; }
.stat-card__hint  { font-size: 11px; color: #94a3b8; }
</style>
