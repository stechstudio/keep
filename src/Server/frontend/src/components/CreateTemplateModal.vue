<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity" @click="$emit('close')">
        <div class="absolute inset-0 bg-black opacity-75"></div>
      </div>

      <!-- Modal content -->
      <div class="relative inline-block w-full max-w-2xl px-6 py-5 overflow-hidden text-left align-middle transition-all transform bg-background rounded-lg shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold">Add Template</h2>
          <button
            @click="$emit('close')"
            class="p-1 rounded hover:bg-muted transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Stage Selection -->
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-2">Stage</label>
            <select
              v-model="selectedStage"
              @change="checkExistingTemplate"
              class="w-full px-3 py-2 border border-border rounded-md bg-background focus:ring-2 focus:ring-primary focus:border-transparent"
            >
              <option value="">Select a stage</option>
              <option v-for="stage in stages" :key="stage" :value="stage">
                {{ stage }}
              </option>
            </select>
          </div>

          <!-- Template exists warning -->
          <div v-if="templateExists" class="bg-warning/10 border border-warning/20 rounded-md p-3">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-warning flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
              <div>
                <p class="text-sm font-medium">Template already exists</p>
                <p class="text-sm text-muted-foreground">{{ selectedStage }}.env already exists. You can edit it from the templates list.</p>
              </div>
            </div>
          </div>

          <!-- Preview Section -->
          <div v-if="preview && !templateExists" class="space-y-2">
            <label class="block text-sm font-medium">Preview</label>
            <div class="relative">
              <pre class="bg-muted/50 rounded-md p-3 text-sm font-mono overflow-x-auto max-h-64 overflow-y-auto">{{ preview }}</pre>
              <button
                v-if="preview"
                @click="copyPreview"
                class="absolute top-2 right-2 p-1.5 bg-background border border-border rounded hover:bg-muted transition-colors"
                title="Copy to clipboard"
              >
                <svg v-if="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                </svg>
                <svg v-else class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
              </button>
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
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 mt-6">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
          >
            Cancel
          </button>
          <button
            v-if="selectedStage && preview && !templateExists"
            @click="generatePreview"
            class="px-4 py-2 text-sm bg-muted text-foreground rounded-md hover:bg-muted/80 transition-colors"
          >
            Refresh Preview
          </button>
          <button
            v-if="!preview && selectedStage && !templateExists"
            @click="generatePreview"
            :disabled="generatingPreview"
            class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50"
          >
            {{ generatingPreview ? 'Generating...' : 'Generate Preview' }}
          </button>
          <button
            v-if="preview && !templateExists"
            @click="createTemplate"
            :disabled="creating"
            class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50"
          >
            {{ creating ? 'Adding...' : 'Add Template' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useToast } from '../composables/useToast'

const emit = defineEmits(['close', 'created'])
const { showToast } = useToast()

const stages = ref([])
const selectedStage = ref('')
const preview = ref('')
const error = ref('')
const generatingPreview = ref(false)
const creating = ref(false)
const templateExists = ref(false)
const copied = ref(false)

onMounted(async () => {
  await loadStages()
})

async function loadStages() {
  try {
    const settings = await window.$api.getSettings()
    stages.value = settings.stages || []
  } catch (err) {
    error.value = 'Failed to load stages'
    console.error('Failed to load stages:', err)
  }
}

async function checkExistingTemplate() {
  if (!selectedStage.value) {
    templateExists.value = false
    return
  }

  try {
    const response = await window.$api.get('/templates')
    const templates = response.templates || []
    templateExists.value = templates.some(t => t.stage === selectedStage.value)
    
    if (templateExists.value) {
      preview.value = ''
      error.value = ''
    }
  } catch (err) {
    console.error('Failed to check existing templates:', err)
  }
}

async function generatePreview() {
  if (!selectedStage.value) {
    error.value = 'Please select a stage'
    return
  }

  error.value = ''
  generatingPreview.value = true

  try {
    const response = await window.$api.post('/templates/generate', {
      stage: selectedStage.value,
      vaults: [] // Empty array means use all vaults
    })
    
    preview.value = response.content || ''
  } catch (err) {
    error.value = err.message || 'Failed to generate template preview'
    preview.value = ''
  } finally {
    generatingPreview.value = false
  }
}

async function createTemplate() {
  if (!selectedStage.value || !preview.value) {
    return
  }

  error.value = ''
  creating.value = true

  try {
    const response = await window.$api.post('/templates/create', {
      stage: selectedStage.value,
      vaults: [] // Empty array means use all vaults
    })
    
    showToast(`Template created: ${response.filename}`, 'success')
    emit('created', response)
  } catch (err) {
    error.value = err.message || 'Failed to create template'
  } finally {
    creating.value = false
  }
}

async function copyPreview() {
  try {
    await navigator.clipboard.writeText(preview.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    showToast('Failed to copy to clipboard', 'error')
  }
}

// Reset preview when stage changes
watch(selectedStage, () => {
  preview.value = ''
  error.value = ''
})
</script>