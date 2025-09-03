<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
      <div class="flex items-start space-x-3">
        <div class="flex-shrink-0">
          <div class="h-10 w-10 rounded-full bg-destructive/10 flex items-center justify-center">
            <svg class="h-6 w-6 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold">Delete Secret</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            Are you sure you want to delete this secret?
          </p>
          <div class="mt-3 p-3 bg-muted/50 rounded-md">
            <div class="text-sm">
              <div class="font-medium">{{ secretKey }}</div>
              <div class="text-xs text-muted-foreground mt-1">
                {{ vault }} / {{ stage }}
              </div>
            </div>
          </div>
          <p class="mt-3 text-sm text-muted-foreground">
            This action cannot be undone.
          </p>
          <div v-if="error" class="mt-3 p-3 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 rounded-md">
            <p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
          </div>
        </div>
      </div>
      
      <div class="flex justify-end space-x-3 mt-6">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium border border-border rounded-md hover:bg-muted transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleDelete"
          :disabled="loading"
          class="px-4 py-2 text-sm font-medium bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Deleting...' : 'Delete Secret' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useSecrets } from '../composables/useSecrets'
import { useToast } from '../composables/useToast'

const props = defineProps({
  secretKey: {
    type: String,
    required: true
  },
  vault: {
    type: String,
    required: true
  },
  stage: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'success'])

const toast = useToast()
const { deleteSecret } = useSecrets()

const error = ref('')
const loading = ref(false)

async function handleDelete() {
  if (loading.value) return
  
  error.value = ''
  loading.value = true
  
  try {
    await deleteSecret(props.secretKey, props.vault, props.stage)
    toast.success('Secret deleted', `Secret '${props.secretKey}' has been deleted successfully`)
    emit('success')
    emit('close')
  } catch (err) {
    // Display error in the modal
    error.value = err.message || 'Failed to delete secret'
    console.error('Failed to delete secret:', err)
  } finally {
    loading.value = false
  }
}
</script>