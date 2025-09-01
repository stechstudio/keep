<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-3xl max-h-[80vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Export Secrets</h2>
        <button
          @click="$emit('close')"
          class="p-1 rounded-md hover:bg-muted transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      
      <!-- Export Options -->
      <div class="mb-6 space-y-4">
        <div class="flex items-center space-x-4">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">Format</label>
            <select v-model="format" class="px-3 py-2 bg-input border border-border rounded-md text-sm">
              <option value="env">.env format</option>
              <option value="json">JSON</option>
              <option value="yaml">YAML</option>
              <option value="shell">Shell export</option>
            </select>
          </div>
          
          <div class="text-sm text-muted-foreground">
            Vault: <span class="font-medium">{{ vault }}</span> • 
            Stage: <span class="font-medium">{{ stage }}</span>
          </div>
          
          <button
            @click="exportSecrets"
            class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
          >
            Generate
          </button>
        </div>
      </div>

      <!-- Export Result -->
      <div v-if="exportResult" class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-medium">Exported {{ format.toUpperCase() }}</h3>
          <div class="flex items-center space-x-2">
            <button
              @click="copyToClipboard"
              class="px-3 py-1 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              Copy to Clipboard
            </button>
            <button
              @click="downloadFile"
              class="px-3 py-1 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              Download
            </button>
          </div>
        </div>
        
        <div class="border border-border rounded-lg bg-muted/50 p-4">
          <pre class="text-sm font-mono whitespace-pre-wrap">{{ exportResult }}</pre>
        </div>
      </div>
      
      <div v-if="loading" class="text-center py-8 text-muted-foreground">
        Exporting secrets...
      </div>
      
      <div v-if="!loading && !exportResult" class="text-center py-8 text-muted-foreground">
        Select a format and click "Generate" to export secrets
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  vault: String,
  stage: String
})

const emit = defineEmits(['close'])

const format = ref('env')
const exportResult = ref('')
const loading = ref(false)

async function exportSecrets() {
  if (!props.vault || !props.stage) return
  
  loading.value = true
  exportResult.value = ''
  
  try {
    const data = await window.$api.exportSecrets(props.vault, props.stage, format.value)
    exportResult.value = data.content || ''
  } catch (error) {
    console.error('Failed to export secrets:', error)
    alert('Failed to export secrets: ' + error.message)
  } finally {
    loading.value = false
  }
}

function copyToClipboard() {
  navigator.clipboard.writeText(exportResult.value)
    .then(() => {
      // Could add a toast notification here
    })
    .catch(err => {
      console.error('Failed to copy to clipboard:', err)
    })
}

function downloadFile() {
  const extension = format.value === 'env' ? '.env' :
                   format.value === 'json' ? '.json' :
                   format.value === 'yaml' ? '.yaml' :
                   '.sh'
  
  const filename = `${props.vault}-${props.stage}${extension}`
  const blob = new Blob([exportResult.value], { type: 'text/plain' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}
</script>