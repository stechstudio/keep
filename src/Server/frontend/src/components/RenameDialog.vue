<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-card border border-border rounded-lg p-6 w-96 max-w-full">
      <h2 class="text-lg font-semibold mb-4">Rename Secret</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Current Key</label>
        <input
          :value="currentKey"
          disabled
          class="w-full px-3 py-2 bg-muted border border-border rounded-md text-sm opacity-60"
        />
      </div>
      
      <div class="mb-6">
        <label class="block text-sm font-medium mb-2">New Key</label>
        <input
          v-model="newKey"
          @keyup.enter="handleRename"
          type="text"
          placeholder="Enter new key name"
          class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>
      
      <div class="flex justify-end space-x-3">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm border border-border rounded-md hover:bg-accent transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleRename"
          :disabled="!isValid"
          class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Rename
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  currentKey: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'rename'])

const newKey = ref(props.currentKey)

const isValid = computed(() => {
  return newKey.value.trim() && newKey.value !== props.currentKey
})

function handleRename() {
  if (isValid.value) {
    emit('rename', newKey.value.trim())
  }
}
</script>