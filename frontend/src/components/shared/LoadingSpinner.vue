<script setup>
/**
 * LoadingSpinner — accessible loading indicator
 * Owner: Loai AlQadasi (UI/UX Lead)
 */
defineProps({
  size: { type: [String, Number], default: 40 },
  label: { type: String, default: 'Loading…' },
  inline: { type: Boolean, default: false },
})
</script>

<template>
  <div class="loading" :class="{ 'loading--inline': inline }" role="status" :aria-label="label">
    <svg
      class="loading__spinner"
      :width="size"
      :height="size"
      viewBox="0 0 50 50"
      aria-hidden="true"
    >
      <circle
        cx="25" cy="25" r="20"
        fill="none"
        stroke="#e2e8f0"
        stroke-width="4"
      />
      <circle
        cx="25" cy="25" r="20"
        fill="none"
        stroke="#4f46e5"
        stroke-width="4"
        stroke-linecap="round"
        stroke-dasharray="60 130"
      >
        <animateTransform
          attributeName="transform"
          type="rotate"
          from="0 25 25"
          to="360 25 25"
          dur="0.9s"
          repeatCount="indefinite"
        />
      </circle>
    </svg>
    <span v-if="label && !inline" class="loading__label">{{ label }}</span>
  </div>
</template>

<style scoped>
.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 32px;
}
.loading--inline { padding: 8px; flex-direction: row; }
.loading__label { font-size: 14px; color: #64748b; }
.loading__spinner { display: block; }
</style>
