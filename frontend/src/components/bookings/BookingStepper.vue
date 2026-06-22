<script setup>
import { computed } from 'vue'

const props = defineProps({
  activeStep: {
    type: Number,
    required: true, // 1 to 5
  }
})

const steps = [
  { id: 1, label: 'Event' },
  { id: 2, label: 'Tickets' },
  { id: 3, label: 'Review' },
  { id: 4, label: 'Payment' },
  { id: 5, label: 'Done' },
]
</script>

<template>
  <nav class="stepper" aria-label="Booking progress">
    <div
      v-for="step in steps"
      :key="step.id"
      class="stepper__item"
      :class="{ 'is-active': step.id === activeStep, 'is-done': step.id < activeStep }"
    >
      <span class="stepper__dot">{{ step.id }}</span>
      <span class="stepper__label">{{ step.label }}</span>
      <span v-if="step.id < steps.length" class="stepper__line"></span>
    </div>
  </nav>
</template>

<style scoped>
.stepper {
  margin: 0 0 20px;
  display: flex;
  align-items: center;
  gap: 0;
  overflow-x: auto;
  padding-bottom: 4px;
}

.stepper__item {
  display: inline-flex;
  align-items: center;
  color: #94a3b8;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}

.stepper__dot {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 6px;
  font-size: 11px;
}

.stepper__line {
  width: 48px;
  height: 2px;
  background: #cbd5e1;
  margin: 0 12px;
}

.stepper__item.is-done {
  color: #4f46e5;
}

.stepper__item.is-done .stepper__dot {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
}

.stepper__item.is-done .stepper__line {
  background: #6366f1;
}

.stepper__item.is-active {
  color: #4338ca;
}

.stepper__item.is-active .stepper__dot {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

@media (max-width: 640px) {
  .stepper__line {
    width: 24px;
    margin: 0 6px;
  }
}
</style>
