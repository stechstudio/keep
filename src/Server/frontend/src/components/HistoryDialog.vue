<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-4xl max-h-[80vh] flex flex-col">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold">Secret History</h2>
          <p class="text-sm text-muted-foreground mt-1">
            {{ secretKey }} • {{ vault }} / {{ stage }}
          </p>
        </div>
        <button
          @click="$emit('close')"
          class="p-1 rounded-md hover:bg-muted transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      
      <div v-if="loading" class="flex-1 flex items-center justify-center">
        <div class="text-muted-foreground">Loading history...</div>
      </div>
      
      <div v-else-if="error" class="flex-1 flex items-center justify-center">
        <div class="text-destructive">{{ error }}</div>
      </div>
      
      <div v-else-if="history.length === 0" class="flex-1 flex items-center justify-center">
        <div class="text-muted-foreground">No history found for this secret</div>
      </div>
      
      <div v-else class="flex-1 overflow-y-auto">
        <table class="w-full">
          <thead class="sticky top-0 bg-card border-b border-border">
            <tr>
              <th class="text-left px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Version</th>
              <th class="text-left px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                <div class="flex items-center space-x-2">
                  <span>Value</span>
                  <button
                    @click="toggleUnmask"
                    class="p-1 rounded hover:bg-accent transition-colors"
                    :title="unmasked ? 'Hide values' : 'Show values'"
                  >
                    <svg v-if="!unmasked" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
              </th>
              <th class="text-left px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Type</th>
              <th class="text-left px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wider">Modified</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="(entry, index) in history" :key="`${entry.version}-${index}`" class="hover:bg-muted/50 transition-colors">
              <td class="px-4 py-3 text-sm">
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-accent">
                  v{{ entry.version }}
                </span>
                <span v-if="index === 0" class="ml-2 inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-primary/10 text-primary">
                  Current
                </span>
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="font-mono">{{ getMaskedValue(entry.value) }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-muted-foreground">
                {{ entry.dataType }}
              </td>
              <td class="px-4 py-3">
                <div class="text-sm">{{ entry.modifiedDate }}</div>
                <div class="text-xs text-muted-foreground/70">{{ entry.modifiedBy }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="flex justify-between items-center mt-4 pt-4 border-t border-border">
        <div class="text-sm text-muted-foreground">
          Showing {{ history.length }} revision{{ history.length === 1 ? '' : 's' }}
        </div>
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium border border-border rounded-md hover:bg-muted transition-colors"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useSecrets } from '../composables/useSecrets'
import { maskValue } from '../utils/formatters'

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

const emit = defineEmits(['close', 'refresh'])

const { getSecretHistory } = useSecrets()
const history = ref([])
const loading = ref(true)
const error = ref(null)
const unmasked = ref(false)

onMounted(() => {
  loadHistory()
})

async function loadHistory() {
  loading.value = true
  error.value = null
  
  try {
    const data = await getSecretHistory(
      props.secretKey, 
      props.vault, 
      props.stage, 
      20, 
      true  // Always get unmasked values
    )
    history.value = data.history || []
  } catch (err) {
    console.error('Failed to load history:', err)
    error.value = err.message || 'Failed to load history'
  } finally {
    loading.value = false
  }
}

function toggleUnmask() {
  unmasked.value = !unmasked.value
}

function getMaskedValue(value) {
  if (!value) return '(null)'
  if (unmasked.value) return value
  return maskValue(value, '••••')
}
</script>