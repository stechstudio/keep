<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-card border border-border rounded-lg p-6 w-96 max-w-full">
      <h2 class="text-lg font-semibold mb-4">Copy Secret to Stage</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Secret Key</label>
        <input
          :value="secretKey"
          disabled
          class="w-full px-3 py-2 bg-muted border border-border rounded-md text-sm opacity-60"
        />
      </div>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Current Stage</label>
        <input
          :value="currentStage"
          disabled
          class="w-full px-3 py-2 bg-muted border border-border rounded-md text-sm opacity-60"
        />
      </div>
      
      <div class="mb-6">
        <label class="block text-sm font-medium mb-2">Target Stage</label>
        <select
          v-model="targetStage"
          class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        >
          <option value="">Select a stage</option>
          <option v-for="stage in availableStages" :key="stage" :value="stage">
            {{ stage }}
          </option>
        </select>
      </div>
      
      <div class="flex justify-end space-x-3">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm border border-border rounded-md hover:bg-accent transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleCopy"
          :disabled="!targetStage"
          class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Copy to Stage
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  secretKey: {
    type: String,
    required: true
  },
  currentStage: {
    type: String,
    required: true
  },
  stages: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['close', 'copy'])

const targetStage = ref('')

const availableStages = computed(() => {
  return props.stages.filter(stage => stage !== props.currentStage)
})

function handleCopy() {
  if (targetStage.value) {
    emit('copy', targetStage.value)
  }
}
</script>