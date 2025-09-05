<template>
  <!-- Import Wizard Modal -->
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeWizard">
      <div class="bg-card border border-border rounded-lg w-full max-w-3xl max-h-[80vh] overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
          <h2 class="text-lg font-semibold">Import .env File</h2>
          <button
            @click="closeWizard"
            class="p-1 rounded-md hover:bg-muted transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
          <!-- Step 1: Upload -->
          <div v-if="step === 1" class="space-y-4">
            <div>
              <h3 class="font-medium mb-2">Upload or Paste .env Content</h3>
              <p class="text-sm text-muted-foreground mb-4">
                Select a .env file from your computer or paste the content directly.
              </p>
            </div>

            <!-- File Upload with Drag & Drop -->
            <div 
              @drop="handleDrop"
              @dragover.prevent
              @dragenter.prevent
              @dragleave="isDragging = false"
              @dragenter="isDragging = true"
              :class="[
                'border-2 border-dashed rounded-lg p-6 text-center transition-colors',
                isDragging ? 'border-primary bg-primary/5' : 'border-border'
              ]"
            >
              <input
                type="file"
                ref="fileInput"
                @change="handleFileUpload"
                accept=".env,.env.*"
                class="hidden"
              />
              
              <svg class="w-12 h-12 mx-auto mb-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              
              <p class="text-sm font-medium mb-2">
                {{ isDragging ? 'Drop file here...' : 'Drag & drop your .env file here' }}
              </p>
              
              <p class="text-xs text-muted-foreground mb-4">or</p>
              
              <button
                @click="$refs.fileInput.click()"
                class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
              >
                Browse Files
              </button>
              
              <p class="text-sm text-muted-foreground mt-3">
                {{ fileName || 'Supports .env and .env.* files' }}
              </p>
            </div>

            <div class="text-center text-muted-foreground text-sm">— OR —</div>

            <!-- Text Input -->
            <div>
              <label class="block text-sm font-medium mb-2">Paste .env Content</label>
              <textarea
                v-model="envContent"
                class="w-full h-48 px-3 py-2 bg-input border border-border rounded-md text-sm font-mono focus:outline-none focus:ring-2 focus:ring-ring"
                placeholder="KEY1=value1&#10;KEY2=value2&#10;KEY3=value3"
              ></textarea>
            </div>

            <!-- Filter Options -->
            <div class="space-y-3 pt-4 border-t border-border">
              <h4 class="text-sm font-medium">Filter Options (Optional)</h4>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs text-muted-foreground mb-1">Only include keys matching</label>
                  <input
                    v-model="filters.only"
                    type="text"
                    class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    placeholder="e.g., DB_* or APP_*"
                  />
                </div>
                <div>
                  <label class="block text-xs text-muted-foreground mb-1">Exclude keys matching</label>
                  <input
                    v-model="filters.except"
                    type="text"
                    class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    placeholder="e.g., MAIL_* or TEST_*"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2: Preview & Conflicts -->
          <div v-else-if="step === 2" class="space-y-4">
            <div>
              <h3 class="font-medium mb-2">Review Import</h3>
              <div class="flex items-center space-x-6 text-sm">
                <span>
                  <span class="font-medium text-green-500">{{ analysis.new }}</span> new
                </span>
                <span>
                  <span class="font-medium text-yellow-500">{{ analysis.existing }}</span> existing
                </span>
                <span>
                  <span class="font-medium text-red-500">{{ analysis.invalid }}</span> invalid
                </span>
                <span>
                  <span class="font-medium text-gray-500">{{ analysis.empty }}</span> empty
                </span>
              </div>
            </div>

            <!-- Conflict Resolution -->
            <div v-if="analysis.existing > 0" class="border border-yellow-500/20 bg-yellow-500/5 rounded-lg p-4">
              <h4 class="font-medium text-yellow-500 mb-3">Conflict Resolution</h4>
              <div class="space-y-2">
                <label class="flex items-center space-x-2">
                  <input
                    type="radio"
                    v-model="conflictStrategy"
                    value="skip"
                    class="text-primary focus:ring-primary"
                  />
                  <span class="text-sm">Skip existing secrets (keep current values)</span>
                </label>
                <label class="flex items-center space-x-2">
                  <input
                    type="radio"
                    v-model="conflictStrategy"
                    value="overwrite"
                    class="text-primary focus:ring-primary"
                  />
                  <span class="text-sm">Overwrite existing secrets (replace with new values)</span>
                </label>
              </div>
            </div>

            <!-- Secrets Preview Table -->
            <div class="border border-border rounded-lg overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-muted">
                  <tr>
                    <th class="text-left px-4 py-2 font-medium">Key</th>
                    <th class="text-left px-4 py-2 font-medium">Value</th>
                    <th class="text-left px-4 py-2 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <tr
                    v-for="secret in analysisSecrets"
                    :key="secret.key"
                    :class="{
                      'opacity-50': secret.status === 'empty' || secret.status === 'invalid'
                    }"
                  >
                    <td class="px-4 py-2 font-mono">{{ secret.key }}</td>
                    <td class="px-4 py-2 font-mono text-xs">
                      {{ secret.value || '(empty)' }}
                    </td>
                    <td class="px-4 py-2">
                      <span
                        :class="[
                          'px-2 py-1 rounded text-xs font-medium',
                          getStatusClass(secret.status)
                        ]"
                      >
                        {{ getStatusLabel(secret.status) }}
                      </span>
                      <span v-if="secret.error" class="block text-xs text-red-500 mt-1">
                        {{ secret.error }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Dry Run Option -->
            <div class="flex items-center space-x-2">
              <input
                type="checkbox"
                v-model="dryRun"
                id="dry-run"
                class="rounded border-border"
              />
              <label for="dry-run" class="text-sm">
                Dry run (preview what would be imported without making changes)
              </label>
            </div>
          </div>

          <!-- Step 3: Results -->
          <div v-else-if="step === 3" class="space-y-4">
            <div>
              <h3 class="font-medium mb-2">Import {{ dryRun ? 'Preview' : 'Complete' }}</h3>
              <div class="flex items-center space-x-6 text-sm">
                <span>
                  <span class="font-medium text-green-500">{{ importResults.imported }}</span> imported
                </span>
                <span>
                  <span class="font-medium text-yellow-500">{{ importResults.skipped }}</span> skipped
                </span>
                <span>
                  <span class="font-medium text-red-500">{{ importResults.failed }}</span> failed
                </span>
              </div>
            </div>

            <!-- Dry Run Notice -->
            <div v-if="dryRun" class="border border-blue-500/20 bg-blue-500/5 rounded-lg p-4">
              <p class="text-sm text-blue-500">
                This was a dry run. No secrets were actually imported.
              </p>
            </div>

            <!-- Errors -->
            <div v-if="importResults.errors && importResults.errors.length > 0" class="border border-red-500/20 bg-red-500/5 rounded-lg p-4">
              <h4 class="font-medium text-red-500 mb-2">Errors</h4>
              <ul class="list-disc list-inside space-y-1">
                <li v-for="(error, index) in importResults.errors" :key="index" class="text-sm text-red-500">
                  {{ error }}
                </li>
              </ul>
            </div>

            <!-- Results Table -->
            <div class="border border-border rounded-lg overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-muted">
                  <tr>
                    <th class="text-left px-4 py-2 font-medium">Key</th>
                    <th class="text-left px-4 py-2 font-medium">Status</th>
                    <th class="text-left px-4 py-2 font-medium">Revision</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-border">
                  <tr v-for="(info, key) in importResults.results" :key="key">
                    <td class="px-4 py-2 font-mono">{{ key }}</td>
                    <td class="px-4 py-2">
                      <span
                        :class="[
                          'px-2 py-1 rounded text-xs font-medium',
                          getResultStatusClass(info.status)
                        ]"
                      >
                        {{ getResultStatusLabel(info) }}
                      </span>
                    </td>
                    <td class="px-4 py-2 text-muted-foreground">
                      {{ info.revision || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-between items-center px-6 py-4 border-t border-border">
          <div>
            <button
              v-if="step === 2"
              @click="step--"
              class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              Back
            </button>
          </div>
          <div class="flex space-x-2">
            <button
              @click="closeWizard"
              class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              {{ step === 3 ? 'Close' : 'Cancel' }}
            </button>
            <button
              v-if="step === 1"
              @click="analyzeImport"
              :disabled="!envContent || analyzing"
              class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50"
            >
              {{ analyzing ? 'Analyzing...' : 'Next' }}
            </button>
            <button
              v-else-if="step === 2"
              @click="executeImport"
              :disabled="importing"
              class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50"
            >
              {{ importing ? 'Importing...' : (dryRun ? 'Preview Import' : 'Import') }}
            </button>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useToast } from '../composables/useToast'

const props = defineProps({
  vault: String,
  stage: String
})

const emit = defineEmits(['imported', 'close'])

const toast = useToast()

// Wizard state
const step = ref(1)

// Step 1: Upload
const fileName = ref('')
const envContent = ref('')
const isDragging = ref(false)
const filters = ref({
  only: '',
  except: ''
})

// Step 2: Analysis
const analyzing = ref(false)
const analysis = ref({
  total: 0,
  new: 0,
  existing: 0,
  invalid: 0,
  empty: 0,
  secrets: []
})
const conflictStrategy = ref('skip')
const dryRun = ref(false)

// Step 3: Results
const importing = ref(false)
const importResults = ref({
  imported: 0,
  skipped: 0,
  failed: 0,
  results: {},
  errors: []
})

// Computed
const analysisSecrets = computed(() => analysis.value.secrets || [])

// Methods
function handleFileUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  
  processFile(file)
}

function handleDrop(event) {
  event.preventDefault()
  isDragging.value = false
  
  const files = event.dataTransfer.files
  if (files.length === 0) return
  
  const file = files[0]
  
  // Check if it's an env file
  if (!file.name.match(/\.env(\.|$)/)) {
    toast.error('Invalid file', 'Please upload a .env file')
    return
  }
  
  processFile(file)
}

function processFile(file) {
  fileName.value = file.name
  
  const reader = new FileReader()
  reader.onload = (e) => {
    envContent.value = e.target.result
  }
  reader.onerror = () => {
    toast.error('Failed to read file', 'Could not read the selected file')
  }
  reader.readAsText(file)
}

async function analyzeImport() {
  if (!envContent.value) {
    toast.error('No content to import', 'Please upload a file or paste content')
    return
  }
  
  analyzing.value = true
  
  try {
    const response = await window.$api.analyzeImport({
      content: envContent.value,
      vault: props.vault,
      stage: props.stage,
      only: filters.value.only || null,
      except: filters.value.except || null
    })
    
    analysis.value = response.analysis
    step.value = 2
    
    // Auto-select strategy based on conflicts
    if (analysis.value.existing === 0) {
      conflictStrategy.value = 'skip' // No conflicts, any strategy works
    }
  } catch (error) {
    toast.error('Failed to analyze import', error.message)
  } finally {
    analyzing.value = false
  }
}

async function executeImport() {
  importing.value = true
  
  try {
    const response = await window.$api.executeImport({
      content: envContent.value,
      vault: props.vault,
      stage: props.stage,
      strategy: conflictStrategy.value,
      only: filters.value.only || null,
      except: filters.value.except || null,
      dry_run: dryRun.value
    })
    
    importResults.value = response
    step.value = 3
    
    if (!dryRun.value && response.imported > 0) {
      toast.success('Import successful', `Imported ${response.imported} secrets`)
      emit('imported')
    }
  } catch (error) {
    toast.error('Failed to import', error.message)
  } finally {
    importing.value = false
  }
}

function closeWizard() {
  step.value = 1
  fileName.value = ''
  envContent.value = ''
  isDragging.value = false
  emit('close')
  filters.value = { only: '', except: '' }
  analysis.value = { total: 0, new: 0, existing: 0, invalid: 0, empty: 0, secrets: [] }
  conflictStrategy.value = 'skip'
  dryRun.value = false
  importResults.value = { imported: 0, skipped: 0, failed: 0, results: {}, errors: [] }
}

function getStatusClass(status) {
  switch (status) {
    case 'new': return 'bg-green-500/10 text-green-500'
    case 'existing': return 'bg-yellow-500/10 text-yellow-500'
    case 'invalid': return 'bg-red-500/10 text-red-500'
    case 'empty': return 'bg-gray-500/10 text-gray-500'
    default: return 'bg-muted text-muted-foreground'
  }
}

function getStatusLabel(status) {
  switch (status) {
    case 'new': return 'New'
    case 'existing': return 'Exists'
    case 'invalid': return 'Invalid'
    case 'empty': return 'Empty'
    default: return status
  }
}

function getResultStatusClass(status) {
  switch (status) {
    case 'imported':
    case 'would_import': return 'bg-green-500/10 text-green-500'
    case 'skipped': return 'bg-yellow-500/10 text-yellow-500'
    case 'failed': return 'bg-red-500/10 text-red-500'
    default: return 'bg-muted text-muted-foreground'
  }
}

function getResultStatusLabel(info) {
  switch (info.status) {
    case 'imported': return 'Imported'
    case 'would_import': return 'Would Import'
    case 'skipped':
      switch (info.reason) {
        case 'empty_value': return 'Skipped (empty)'
        case 'exists': return 'Skipped (exists)'
        default: return 'Skipped'
      }
    case 'failed':
      switch (info.reason) {
        case 'invalid_key': return 'Failed (invalid)'
        case 'exists': return 'Failed (exists)'
        case 'vault_error': return 'Failed (error)'
        default: return 'Failed'
      }
    default: return info.status
  }
}
</script>