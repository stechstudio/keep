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
          :class="[
            'w-full px-3 py-2 bg-input border rounded-md text-sm focus:outline-none focus:ring-2',
            (keyError || error) ? 'border-red-500 focus:ring-red-500' : 'border-border focus:ring-ring'
          ]"
        />
        <p v-if="keyError" class="mt-1 text-xs text-red-500">{{ keyError }}</p>
        <p v-if="error && !keyError" class="mt-2 text-sm text-red-500">{{ error }}</p>
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
          :disabled="!isValid || loading"
          class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Renaming...' : 'Rename' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useSecrets } from '../composables/useSecrets'
import { useToast } from '../composables/useToast'

const props = defineProps({
  currentKey: {
    type: String,
    required: true
  },
  vault: {
    type: String,
    required: true
  },
  env: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'success'])

const toast = useToast()
const { renameSecret } = useSecrets()

const newKey = ref(props.currentKey)
const keyError = ref('')
const error = ref('')
const loading = ref(false)

// Validate key on input
const validateKey = computed(() => {
  const key = newKey.value.trim()
  
  if (!key) {
    keyError.value = ''
    return false
  }
  
  // Check if unchanged
  if (key === props.currentKey) {
    keyError.value = ''
    return false
  }
  
  // Check for spaces
  if (key.includes(' ')) {
    keyError.value = 'No spaces allowed'
    return false
  }
  
  // Check for other invalid characters (same validation as SecretDialog)
  if (!/^[A-Za-z0-9_\-/.]+$/.test(key)) {
    keyError.value = 'Only letters, numbers, _, -, /, . allowed'
    return false
  }
  
  keyError.value = ''
  return true
})

const isValid = computed(() => {
  return validateKey.value
})

// Trigger validation as user types
watch(() => newKey.value, () => {
  validateKey.value
})

async function handleRename() {
  if (!isValid.value || loading.value) return
  
  error.value = ''
  loading.value = true
  
  try {
    await renameSecret(props.currentKey, newKey.value.trim(), props.vault, props.env)
    toast.success('Secret renamed', `Secret renamed from '${props.currentKey}' to '${newKey.value.trim()}'`)
    emit('success')
    emit('close')
  } catch (err) {
    // Display error in the modal
    error.value = err.message || 'Failed to rename secret'
    console.error('Failed to rename secret:', err)
  } finally {
    loading.value = false
  }
}
</script>