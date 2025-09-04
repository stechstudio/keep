<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity" @click="$emit('close')">
        <div class="absolute inset-0 bg-black opacity-75"></div>
      </div>

      <!-- Modal content -->
      <div class="relative inline-block w-full max-w-4xl px-6 py-5 overflow-hidden text-left align-middle transition-all transform bg-background rounded-lg shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-semibold">Process Template</h2>
            <p class="text-sm text-muted-foreground">{{ template.filename }}</p>
          </div>
          <button
            @click="$emit('close')"
            class="p-1 rounded hover:bg-muted transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Configuration -->
        <div class="space-y-4 mb-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">Stage</label>
              <select
                v-model="selectedStage"
                class="w-full px-3 py-2 border border-border rounded-md bg-background focus:ring-2 focus:ring-primary focus:border-transparent"
              >
                <option :value="template.stage">{{ template.stage }} (template stage)</option>
                <option v-for="stage in otherStages" :key="stage" :value="stage">
                  {{ stage }}
                </option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Missing Secret Strategy</label>
              <select
                v-model="missingStrategy"
                class="w-full px-3 py-2 border border-border rounded-md bg-background focus:ring-2 focus:ring-primary focus:border-transparent"
              >
                <option value="skip">Skip (leave placeholder)</option>
                <option value="empty">Empty string</option>
                <option value="error">Error</option>
              </select>
            </div>
          </div>

          <div class="bg-muted/30 rounded-md p-3">
            <p class="text-sm text-muted-foreground">
              <strong>Skip:</strong> Leaves placeholders as-is for missing secrets<br>
              <strong>Empty:</strong> Replaces missing secrets with empty strings<br>
              <strong>Error:</strong> Stops processing if any secret is missing
            </p>
          </div>
        </div>

        <!-- Process Button -->
        <div v-if="!hasProcessed" class="flex justify-center py-8">
          <button
            @click="processTemplate"
            :disabled="processing"
            class="px-6 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50"
          >
            {{ processing ? 'Processing...' : 'Process Template' }}
          </button>
        </div>

        <!-- Results -->
        <div v-else class="space-y-4">
          <!-- Validation Status -->
          <div v-if="!result.validation.valid" class="bg-destructive/10 border border-destructive/20 rounded-md p-3">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-destructive flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
              <p class="text-sm">
                Template has {{ result.validation.errorCount }} validation error{{ result.validation.errorCount !== 1 ? 's' : '' }}. 
                Output may be incomplete.
              </p>
            </div>
          </div>

          <!-- Output -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-sm font-medium">Processed Output</label>
              <div class="flex items-center gap-2">
                <button
                  @click="downloadOutput"
                  class="px-3 py-1 text-xs bg-background border border-border rounded hover:bg-muted transition-colors"
                >
                  Download .env
                </button>
                <button
                  @click="copyOutput"
                  class="px-3 py-1 text-xs bg-background border border-border rounded hover:bg-muted transition-colors"
                >
                  {{ copied ? 'Copied!' : 'Copy' }}
                </button>
              </div>
            </div>
            <div class="relative">
              <pre class="bg-muted/50 rounded-md p-4 text-sm font-mono overflow-x-auto max-h-96 overflow-y-auto">{{ result.output }}</pre>
            </div>
          </div>

          <!-- Statistics -->
          <div class="grid grid-cols-3 gap-4">
            <div class="bg-muted/30 rounded-md p-3 text-center">
              <p class="text-2xl font-semibold">{{ result.placeholders?.length || 0 }}</p>
              <p class="text-xs text-muted-foreground">Total Placeholders</p>
            </div>
            <div class="bg-muted/30 rounded-md p-3 text-center">
              <p class="text-2xl font-semibold text-green-600">{{ processedCount }}</p>
              <p class="text-xs text-muted-foreground">Processed</p>
            </div>
            <div class="bg-muted/30 rounded-md p-3 text-center">
              <p class="text-2xl font-semibold text-warning">{{ skippedCount }}</p>
              <p class="text-xs text-muted-foreground">Skipped</p>
            </div>
          </div>
        </div>

        <!-- Error Display -->
        <div v-if="error" class="bg-destructive/10 border border-destructive/20 rounded-md p-3">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-destructive flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-sm">{{ error }}</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 mt-6">
          <button
            v-if="hasProcessed"
            @click="resetProcess"
            class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
          >
            Process Again
          </button>
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '../composables/useToast'

const props = defineProps({
  template: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close'])
const { showToast } = useToast()

const processing = ref(false)
const hasProcessed = ref(false)
const selectedStage = ref('')
const missingStrategy = ref('skip')
const stages = ref([])
const result = ref({})
const error = ref('')
const copied = ref(false)

const otherStages = computed(() => {
  return stages.value.filter(s => s !== props.template.stage)
})

const processedCount = computed(() => {
  if (!result.value.output || !result.value.placeholders) return 0
  
  // Count placeholders that were replaced (not still in output)
  let count = 0
  for (const placeholder of result.value.placeholders) {
    if (!result.value.output.includes(placeholder)) {
      count++
    }
  }
  return count
})

const skippedCount = computed(() => {
  if (!result.value.placeholders) return 0
  return result.value.placeholders.length - processedCount.value
})

onMounted(async () => {
  selectedStage.value = props.template.stage
  await loadStages()
})

async function loadStages() {
  try {
    const settings = await window.$api.getSettings()
    stages.value = settings.stages || []
  } catch (err) {
    console.error('Failed to load stages:', err)
  }
}

async function processTemplate() {
  if (!selectedStage.value) {
    showToast('Please select a stage', 'warning')
    return
  }

  processing.value = true
  error.value = ''

  try {
    const response = await window.$api.post('/templates/process', {
      filename: props.template.filename,
      stage: selectedStage.value,
      strategy: missingStrategy.value
    })

    result.value = response
    hasProcessed.value = true

    if (response.validation && !response.validation.valid) {
      showToast('Template processed with validation errors', 'warning')
    } else {
      showToast('Template processed successfully', 'success')
    }
  } catch (err) {
    error.value = err.message || 'Failed to process template'
    showToast('Failed to process template', 'error')
  } finally {
    processing.value = false
  }
}

function resetProcess() {
  hasProcessed.value = false
  result.value = {}
  error.value = ''
  copied.value = false
}

async function copyOutput() {
  try {
    await navigator.clipboard.writeText(result.value.output)
    copied.value = true
    showToast('Copied to clipboard', 'success')
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    showToast('Failed to copy to clipboard', 'error')
  }
}

function downloadOutput() {
  const blob = new Blob([result.value.output], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${selectedStage.value}.env`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
  showToast('Downloaded .env file', 'success')
}
</script>