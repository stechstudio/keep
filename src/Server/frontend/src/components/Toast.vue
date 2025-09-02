<template>
  <div
    :class="[
      'pointer-events-auto relative flex w-full items-center justify-between rounded-lg border p-4 shadow-lg transition-all',
      variantClasses
    ]"
  >
    <div class="flex items-start space-x-3">
      <div v-if="icon" class="flex-shrink-0 mt-0.5">
        <component :is="icon" class="h-5 w-5" />
      </div>
      <div class="flex-1">
        <div v-if="toast.title" class="font-semibold text-sm">
          {{ toast.title }}
        </div>
        <div v-if="toast.description" class="text-sm mt-1 opacity-90">
          {{ toast.description }}
        </div>
      </div>
    </div>
    <button
      @click="$emit('dismiss', toast.id)"
      class="flex-shrink-0 ml-4 inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors hover:bg-accent"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  toast: {
    type: Object,
    required: true
  }
})

defineEmits(['dismiss'])

const variantClasses = computed(() => {
  const variants = {
    default: 'bg-card border-border text-card-foreground',
    success: 'bg-green-950 border-green-900 text-green-100',
    destructive: 'bg-red-950 border-red-900 text-red-100',
  }
  return variants[props.toast.variant] || variants.default
})

const icon = computed(() => {
  if (props.toast.variant === 'success') {
    return {
      template: `
        <svg class="text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      `
    }
  }
  if (props.toast.variant === 'destructive') {
    return {
      template: `
        <svg class="text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      `
    }
  }
  return null
})
</script>