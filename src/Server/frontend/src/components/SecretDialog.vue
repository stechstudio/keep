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
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed"
              placeholder="SECRET_KEY"
              required
            />
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
            class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
          >
            {{ secret ? 'Save' : 'Add' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
  secret: Object,
  vault: String,
  stage: String
})

const emit = defineEmits(['save', 'close'])

const form = reactive({
  key: '',
  value: ''
})

watch(() => props.secret, (newSecret) => {
  if (newSecret) {
    form.key = newSecret.key
    form.value = newSecret.value
  } else {
    form.key = ''
    form.value = ''
  }
}, { immediate: true })

function save() {
  emit('save', {
    key: form.key,
    value: form.value
  })
}
</script>