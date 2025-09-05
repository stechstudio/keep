<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
      <h2 class="text-lg font-semibold mb-4">Download Secrets</h2>
      
      <div class="space-y-4">
        <!-- Format Selection -->
        <div>
          <label class="block text-sm font-medium mb-2">Export Format</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="fmt in formats"
              :key="fmt.value"
              @click="selectedFormat = fmt.value"
              :class="[
                'p-3 border rounded-md transition-colors',
                selectedFormat === fmt.value
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-border hover:bg-muted'
              ]"
            >
              <div class="font-medium">{{ fmt.label }}</div>
              <div class="text-xs opacity-75 mt-1">{{ fmt.description }}</div>
            </button>
          </div>
        </div>
        
        <!-- Filename -->
        <div>
          <label class="block text-sm font-medium mb-1">Filename</label>
          <input
            v-model="filename"
            type="text"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            :placeholder="defaultFilename"
          />
        </div>
        
        <!-- Info -->
        <div class="text-xs text-muted-foreground">
          <div>Vault: <span class="font-medium">{{ vault }}</span></div>
          <div>Stage: <span class="font-medium">{{ stage }}</span></div>
          <div>Secrets: <span class="font-medium">{{ secretCount }}</span></div>
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
          @click="downloadSecrets"
          :disabled="loading"
          class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50"
        >
          <span v-if="!loading">Download</span>
          <span v-else>Preparing...</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useToast } from '../composables/useToast'
import { formatDate } from '../utils/formatters'

const props = defineProps({
  vault: String,
  stage: String,
  secrets: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close'])

const toast = useToast()

const loading = ref(false)
const selectedFormat = ref('env')
const filename = ref('')

const formats = [
  {
    value: 'env',
    label: '.env',
    description: 'KEY=value'
  },
  {
    value: 'json',
    label: 'JSON',
    description: '{"key": "value"}'
  },
  {
    value: 'csv',
    label: 'CSV',
    description: 'Spreadsheet'
  }
]

const secretCount = computed(() => props.secrets.length)

const defaultFilename = computed(() => {
  const timestamp = new Date().toISOString().split('T')[0]
  return `${props.vault}-${props.stage}-${timestamp}.${selectedFormat.value}`
})

async function downloadSecrets() {
  loading.value = true
  
  try {
    let content = ''
    const fileName = filename.value || defaultFilename.value
    
    switch (selectedFormat.value) {
      case 'json':
        const jsonData = {}
        props.secrets.forEach(secret => {
          jsonData[secret.key] = secret.value
        })
        content = JSON.stringify(jsonData, null, 2)
        break
        
      case 'csv':
        content = 'Key,Value,Modified\n'
        props.secrets.forEach(secret => {
          const key = escapeCsvField(secret.key)
          const value = escapeCsvField(secret.value)
          const modified = escapeCsvField(formatDate(secret.modified))
          content += `${key},${value},${modified}\n`
        })
        break
        
      case 'env':
      default:
        props.secrets.forEach(secret => {
          content += `${secret.key}=${formatEnvValue(secret.value)}\n`
        })
        break
    }
    
    // Create blob and download
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = fileName
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    toast.success('Export Complete', `Secrets downloaded as ${fileName}`)
    emit('close')
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Export Failed', error.message)
  } finally {
    loading.value = false
  }
}

function escapeCsvField(field) {
  if (typeof field !== 'string') {
    field = String(field || '')
  }
  // If field contains comma, quotes, or newline, wrap in quotes and escape quotes
  if (/[,"\n\r]/.test(field)) {
    return '"' + field.replace(/"/g, '""') + '"'
  }
  return field
}

function formatEnvValue(value) {
  if (typeof value !== 'string') {
    value = String(value || '')
  }
  // If value contains newlines or starts/ends with quotes, wrap in quotes
  if (value.includes('\n') || value.includes('"') || value.includes(' ')) {
    return `"${value.replace(/"/g, '\\"')}"`
  }
  return value
}
</script>