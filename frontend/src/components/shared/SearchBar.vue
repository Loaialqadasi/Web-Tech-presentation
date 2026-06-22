<script setup>
/**
 * SearchBar — accessible search input with debounce
 * Owner: Loai AlQadasi (UI/UX Lead)
 *
 * Emits 'update:modelValue' (debounced 250ms) and 'submit'.
 */
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search…' },
  debounce: { type: Number, default: 250 },
  autofocus: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const inner = ref(props.modelValue)
let timer = null

watch(() => props.modelValue, (v) => { inner.value = v })

watch(inner, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => emit('update:modelValue', v), props.debounce)
})

function onSubmit(e) {
  e.preventDefault()
  clearTimeout(timer)
  emit('update:modelValue', inner.value)
  emit('submit', inner.value)
}

function clear() {
  inner.value = ''
  emit('update:modelValue', '')
}
</script>

<template>
  <form class="search-bar" role="search" @submit="onSubmit">
    <svg class="search-bar__icon" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M10 2a8 8 0 1 0 4.9 14.32l5.39 5.39 1.4-1.4-5.39-5.39A8 8 0 0 0 10 2zm0 2a6 6 0 1 1 0 12 6 6 0 0 1 0-12z" fill="currentColor"/>
    </svg>
    <input
      v-model="inner"
      type="search"
      class="search-bar__input"
      :placeholder="placeholder"
      :autofocus="autofocus"
      aria-label="Search"
    />
    <button
      v-if="inner"
      type="button"
      class="search-bar__clear"
      aria-label="Clear search"
      @click="clear"
    >×</button>
  </form>
</template>

<style scoped>
.search-bar {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}
.search-bar__icon {
  position: absolute;
  left: 12px;
  width: 18px;
  height: 18px;
  color: #94a3b8;
  pointer-events: none;
}
.search-bar__input {
  width: 100%;
  padding: 10px 36px 10px 38px;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  font-size: 14px;
  background: #fff;
  color: #0f172a;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.search-bar__input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}
.search-bar__clear {
  position: absolute;
  right: 8px;
  width: 24px;
  height: 24px;
  border: 0;
  background: #f1f5f9;
  color: #64748b;
  border-radius: 6px;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.search-bar__clear:hover { background: #e2e8f0; color: #0f172a; }
</style>
