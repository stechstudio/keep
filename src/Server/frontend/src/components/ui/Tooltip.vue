<template>
  <div class="relative inline-block">
    <div
      @mouseenter="showTooltip"
      @mouseleave="hideTooltip"
      @focus="showTooltip"
      @blur="hideTooltip"
    >
      <slot />
    </div>
    
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isVisible"
          ref="tooltipRef"
          :style="tooltipStyle"
          class="absolute z-50 px-2 py-1 text-xs font-medium text-popover-foreground bg-popover border border-border rounded-md shadow-md pointer-events-none whitespace-nowrap"
        >
          {{ content }}
          <div
            class="absolute w-2 h-2 bg-popover border-l border-b border-border transform rotate-45"
            :class="arrowClass"
          />
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'

const props = defineProps({
  content: {
    type: String,
    required: true
  },
  delayDuration: {
    type: Number,
    default: 200
  },
  side: {
    type: String,
    default: 'top',
    validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value)
  },
  sideOffset: {
    type: Number,
    default: 8
  }
})

const isVisible = ref(false)
const tooltipRef = ref(null)
const tooltipStyle = ref({})
const triggerElement = ref(null)
let showTimeout = null
let hideTimeout = null

const arrowClass = computed(() => {
  const classes = {
    top: 'bottom-[-5px] left-1/2 -translate-x-1/2',
    bottom: 'top-[-5px] left-1/2 -translate-x-1/2 rotate-180',
    left: 'right-[-5px] top-1/2 -translate-y-1/2 -rotate-45',
    right: 'left-[-5px] top-1/2 -translate-y-1/2 rotate-[135deg]'
  }
  return classes[props.side] || classes.top
})

function showTooltip(event) {
  clearTimeout(hideTimeout)
  triggerElement.value = event.currentTarget
  
  showTimeout = setTimeout(async () => {
    isVisible.value = true
    await nextTick()
    positionTooltip()
  }, props.delayDuration)
}

function hideTooltip() {
  clearTimeout(showTimeout)
  hideTimeout = setTimeout(() => {
    isVisible.value = false
  }, 100)
}

function positionTooltip() {
  if (!tooltipRef.value || !triggerElement.value) return
  
  const trigger = triggerElement.value.getBoundingClientRect()
  const tooltip = tooltipRef.value.getBoundingClientRect()
  
  let top = 0
  let left = 0
  
  switch (props.side) {
    case 'top':
      top = trigger.top - tooltip.height - props.sideOffset
      left = trigger.left + (trigger.width - tooltip.width) / 2
      break
    case 'bottom':
      top = trigger.bottom + props.sideOffset
      left = trigger.left + (trigger.width - tooltip.width) / 2
      break
    case 'left':
      top = trigger.top + (trigger.height - tooltip.height) / 2
      left = trigger.left - tooltip.width - props.sideOffset
      break
    case 'right':
      top = trigger.top + (trigger.height - tooltip.height) / 2
      left = trigger.right + props.sideOffset
      break
  }
  
  // Ensure tooltip stays within viewport
  const margin = 8
  left = Math.max(margin, Math.min(left, window.innerWidth - tooltip.width - margin))
  top = Math.max(margin, Math.min(top, window.innerHeight - tooltip.height - margin))
  
  tooltipStyle.value = {
    top: `${top}px`,
    left: `${left}px`
  }
}
</script>