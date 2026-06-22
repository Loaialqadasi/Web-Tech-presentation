<script setup>
/**
 * EmptyState — friendly placeholder for empty lists / search results
 * Owner: Loai AlQadasi (UI/UX Lead)
 */
defineProps({
  title: { type: String, default: 'Nothing to show yet' },
  description: { type: String, default: '' },
  icon: { type: String, default: '📭' }, // emoji or short text
})
defineEmits(['action'])
</script>

<template>
  <div class="empty-state">
    <div class="empty-state__icon" aria-hidden="true">{{ icon }}</div>
    <h3 class="empty-state__title">{{ title }}</h3>
    <p v-if="description" class="empty-state__desc">{{ description }}</p>
    <slot name="action" />
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
}
.empty-state__icon { font-size: 48px; line-height: 1; margin-bottom: 12px; }
.empty-state__title { margin: 0 0 6px; font-size: 16px; font-weight: 600; color: #0f172a; }
.empty-state__desc  { margin: 0; font-size: 14px; color: #64748b; max-width: 420px; line-height: 1.5; }
.empty-state__action { margin-top: 16px; }
</style>
