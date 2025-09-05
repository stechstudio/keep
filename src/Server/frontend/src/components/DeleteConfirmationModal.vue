<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="cancel">
      <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
        <div class="flex items-start space-x-3 mb-4">
          <div class="flex-shrink-0 w-10 h-10 rounded-full bg-destructive/10 flex items-center justify-center">
            <svg class="w-5 h-5 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-semibold mb-2">{{ title }}</h3>
            <p class="text-sm text-muted-foreground">{{ message }}</p>
          </div>
        </div>
        
        <div class="flex justify-end space-x-2 mt-6">
          <button
            @click="cancel"
            class="px-4 py-2 border border-border rounded-md hover:bg-muted transition-colors text-sm font-medium"
          >
            Cancel
          </button>
          <button
            @click="confirm"
            class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90 transition-colors text-sm font-medium"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Confirm Deletion'
  },
  message: {
    type: String,
    required: true
  },
  confirmText: {
    type: String,
    default: 'Delete'
  }
})

const emit = defineEmits(['confirm', 'cancel'])

const isOpen = ref(false)

function open() {
  isOpen.value = true
}

function confirm() {
  emit('confirm')
  isOpen.value = false
}

function cancel() {
  emit('cancel')
  isOpen.value = false
}

defineExpose({
  open,
  close: cancel
})
</script>