<template>
  <div>
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
          Env: <span class="font-medium">{{ env }}</span>
        </div>
        
        <button
          @click="exportSecrets"
          class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
        >
          Export
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
    
    <div v-if="!loading && !exportResult && vault && env" class="text-center py-8 text-muted-foreground">
      Select a format and click "Export" to generate output
    </div>
    
    <div v-if="!vault || !env" class="text-center py-8 text-muted-foreground">
      Please select a vault and env to export
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useToast } from '../composables/useToast'

const props = defineProps({
  vault: String,
  env: String
})

const toast = useToast()
const format = ref('env')
const exportResult = ref('')
const loading = ref(false)

async function exportSecrets() {
  if (!props.vault || !props.env) return
  
  loading.value = true
  exportResult.value = ''
  
  try {
    const data = await window.$api.exportSecrets(props.vault, props.env, format.value)
    exportResult.value = data.content || ''
  } catch (error) {
    console.error('Failed to export secrets:', error)
    toast.error('Export failed', error.message)
  } finally {
    loading.value = false
  }
}

function copyToClipboard() {
  navigator.clipboard.writeText(exportResult.value)
    .then(() => {
      toast.success('Copied to clipboard', 'Export content has been copied to your clipboard')
    })
    .catch(err => {
      console.error('Failed to copy to clipboard:', err)
      toast.error('Copy failed', 'Failed to copy to clipboard')
    })
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
  
  const filename = `${props.vault}-${props.env}${extension}`
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