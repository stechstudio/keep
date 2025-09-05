<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
      <h2 class="text-lg font-semibold mb-4">
        {{ secret ? 'Edit Secret' : 'Add Secret' }}
      </h2>
      
      <form @submit.prevent="save">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Key</label>
            <input
              v-model="form.key"
              type="text"
              :disabled="!!secret"
              :class="[
                'w-full px-3 py-2 bg-input border rounded-md text-sm focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed',
                keyError ? 'border-red-500 focus:ring-red-500' : 'border-border focus:ring-ring'
              ]"
              placeholder="SECRET_KEY"
              required
            />
            <p v-if="keyError" class="mt-1 text-xs text-red-500">{{ keyError }}</p>
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Value</label>
            <textarea
              v-model="form.value"
              rows="4"
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring font-mono"
              placeholder="Secret value..."
              required
            />
          </div>
          
          <div class="text-xs text-muted-foreground">
            Vault: <span class="font-medium">{{ vault }}</span> • 
            Stage: <span class="font-medium">{{ stage }}</span>
          </div>
          
          <div v-if="saveError" class="p-3 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 rounded-md">
            <p class="text-sm text-red-600 dark:text-red-400">{{ saveError }}</p>
          </div>
        </div>
        
        <div class="flex justify-end space-x-3 mt-6">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium border border-border rounded-md hover:bg-muted transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!validateKey || loading"
            class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ loading ? 'Saving...' : (secret ? 'Save' : 'Add') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch, ref, computed } from 'vue'
import { useSecrets } from '../composables/useSecrets'
import { useToast } from '../composables/useToast'

const props = defineProps({
  secret: Object,
  vault: String,
  stage: String,
  initialKey: String
})

const emit = defineEmits(['success', 'close'])

const toast = useToast()
const { createSecret, updateSecret } = useSecrets()

const form = reactive({
  key: '',
  value: ''
})

const keyError = ref('')
const saveError = ref('')
const loading = ref(false)

// Validate key on input
const validateKey = computed(() => {
  if (!form.key) {
    keyError.value = ''
    return true
  }
  
  // Check for spaces
  if (form.key.includes(' ')) {
    keyError.value = 'No spaces allowed'
    return false
  }
  
  // Check for other invalid characters (basic validation)
  if (!/^[A-Za-z0-9_\-/.]+$/.test(form.key)) {
    keyError.value = 'Only letters, numbers, _, -, /, . allowed'
    return false
  }
  
  keyError.value = ''
  return true
})

watch(() => props.secret, (newSecret) => {
  if (newSecret) {
    form.key = newSecret.key
    form.value = newSecret.value
  } else {
    form.key = props.initialKey || ''
    form.value = ''
  }
}, { immediate: true })

watch(() => props.initialKey, (newKey) => {
  if (!props.secret && newKey) {
    form.key = newKey
  }
}, { immediate: true })

// Trigger validation as user types
watch(() => form.key, () => {
  validateKey.value
})

async function save() {
  if (!validateKey.value || loading.value) {
    return
  }
  
  saveError.value = ''
  loading.value = true
  
  try {
    if (props.secret) {
      await updateSecret(form.key, form.value, props.vault, props.stage)
      toast.success('Secret updated', `Secret '${form.key}' has been updated successfully`)
    } else {
      await createSecret(form.key, form.value, props.vault, props.stage)
      toast.success('Secret created', `Secret '${form.key}' has been created successfully`)
    }
    emit('success')
    emit('close')
  } catch (error) {
    // Display error in the modal
    saveError.value = error.message || 'Failed to save secret'
    console.error('Failed to save secret:', error)
  } finally {
    loading.value = false
  }
}
</script>