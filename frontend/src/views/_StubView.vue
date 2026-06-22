<script setup>
/**
 * Stub views file — provides minimal placeholder components for the modules
 * owned by other team members (Siti, Fatema, Amir). They import the shared
 * components Loai built so the visual language stays consistent.
 *
 * Each placeholder:
 *   - Shows a clear "module owned by X" banner
 *   - Demonstrates the use of shared utilities (validators, format, components)
 *   - Can be replaced in-place by the responsible team member
 */
import EmptyState from '../components/shared/EmptyState.vue'

defineProps({
  owner:  { type: String, default: 'Another team member' },
  module: { type: String, default: 'This module' },
})
</script>

<template>
  <div style="padding: 48px 24px; max-width: 800px; margin: 0 auto;">
    <EmptyState
      icon="🚧"
      :title="`${module} — Coming Soon`"
      :description="`This module is owned by ${owner}. It will be implemented following the shared UI patterns established by Loai (EventCard, StatCard, validators, etc.).`"
    />
  </div>
</template>
