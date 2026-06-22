<script setup>
/**
 * CategoryFilter — pill-style category tabs
 * Owner: Loai AlQadasi (UI/UX Lead)
 *
 * Emits 'update:modelValue' with the selected category.
 */
import { categoryColor } from '../../utils/format.js'

defineProps({
  modelValue: { type: String, default: 'All' },
  categories: { type: Array, default: () => ['All', 'Technology', 'Career', 'Academic', 'Sports', 'Arts', 'Entertainment'] },
})
const emit = defineEmits(['update:modelValue'])

function select(c) { emit('update:modelValue', c) }
</script>

<template>
  <div class="category-filter" role="tablist" aria-label="Event categories">
    <button
      v-for="c in categories"
      :key="c"
      type="button"
      role="tab"
      :aria-selected="c === modelValue"
      class="category-filter__pill"
      :class="{
        'category-filter__pill--active': c === modelValue,
        [categoryColor(c).bg + ' ' + categoryColor(c).text]: c === modelValue,
      }"
      @click="select(c)"
    >
      <span
        class="category-filter__dot"
        :class="categoryColor(c).dot"
        v-if="c !== 'All'"
      ></span>
      {{ c }}
    </button>
  </div>
</template>

<style scoped>
.category-filter {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.category-filter__pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}
.category-filter__pill:hover { border-color: #cbd5e1; color: #0f172a; }
.category-filter__pill--active {
  border-color: transparent;
  color: #0f172a;
  font-weight: 600;
}
.category-filter__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
}
</style>
