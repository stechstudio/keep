<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-40 text-center sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-black opacity-75"></div>
      </div>

      <!-- Modal content -->
      <div class="relative inline-block w-full max-w-4xl px-6 py-5 overflow-visible text-left align-middle transition-all transform bg-background rounded-lg shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-semibold">Edit Template</h2>
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

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>

        <!-- Editor Content -->
        <div v-else class="space-y-4">
          <!-- Toolbar -->
          <div class="flex items-center justify-between bg-muted/50 rounded-md p-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                Stage: {{ templateData.stage }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <button
                  @click="formatContent"
                  class="px-3 py-1 text-xs bg-background border border-border rounded hover:bg-muted transition-colors"
              >
                Format
              </button>
            </div>
          </div>

          <!-- Editor -->
          <div class="relative mb-8" style="overflow: visible;">
            <TemplateCodeEditor
                ref="editorRef"
                v-model="content"
                :stage="templateData.stage"
                placeholder="# Enter your template content here...&#10;# Use {vault:key} syntax for placeholders"
            />
          </div>

          <!-- Validation Messages -->
          <div v-if="validationErrors.length > 0" class="bg-destructive/10 border border-destructive/20 rounded-md p-3">
            <h3 class="text-sm font-medium mb-2 text-destructive">Validation Errors</h3>
            <ul class="space-y-1">
              <li v-for="error in validationErrors" :key="error" class="text-sm text-destructive flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ error }}
              </li>
            </ul>
          </div>

          <!-- Modified indicator -->
          <div v-if="isModified" class="flex items-center gap-2 text-sm text-warning">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Unsaved changes
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-between items-center mt-6">
          <div class="flex items-center gap-3">
            <button
                @click="validateTemplate"
                :disabled="validating"
                class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors disabled:opacity-50"
            >
              {{ validating ? 'Validating...' : 'Validate' }}
            </button>
            <!-- Inline validation status -->
            <div v-if="validationStatus" class="flex items-center gap-2">
              <svg v-if="validationStatus === 'valid'" class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <svg v-else-if="validationStatus === 'invalid'" class="w-4 h-4 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <svg v-else-if="validationStatus === 'warning'" class="w-4 h-4 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
              <span :class="[
                'text-sm',
                validationStatus === 'valid' ? 'text-green-600' : 
                validationStatus === 'invalid' ? 'text-destructive' : 
                'text-warning'
              ]">
                {{ validationMessage }}
              </span>
            </div>
          </div>
          <div class="flex gap-3">
            <button
                @click="$emit('close')"
                class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              Cancel
            </button>
            <button
                @click="saveTemplate"
                :disabled="saving || !isModified"
                class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Template' }}
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import {ref, computed, onMounted, watch, nextTick} from 'vue'
import {useToast} from '../composables/useToast'
import TemplateCodeEditor from './TemplateCodeEditor.vue'

const props = defineProps({
  template: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])
const {showToast} = useToast()

const editorRef = ref(null)
const loading = ref(true)
const saving = ref(false)
const validating = ref(false)
const content = ref('')
const originalContent = ref('')
const templateData = ref({})
const placeholders = ref([])
const validationErrors = ref([])
const validationStatus = ref('')
const validationMessage = ref('')
const secretsForEditor = ref([])

const isModified = computed(() => content.value !== originalContent.value)

onMounted(async () => {
  console.log('TemplateEditorModal mounted, template:', props.template)
  await loadTemplate()
})

async function loadTemplate() {
  console.log('loadTemplate() called')
  loading.value = true

  try {
    const response = await window.$api.get(`/templates/${encodeURIComponent(props.template.filename)}`)
    console.log('Template loaded:', response)
    templateData.value = response
    content.value = response.content || ''
    originalContent.value = response.content || ''
    placeholders.value = response.placeholders || []
    
    console.log('About to call loadSecrets()')
    // Load secrets after template data is available
    await loadSecrets()
  } catch (error) {
    console.error('Error in loadTemplate:', error)
  } finally {
    loading.value = false
  }
}

async function loadSecrets() {
  try {
    // Use stage from props.template or templateData
    const stage = templateData.value?.stage || props.template?.stage
    console.log('Loading secrets for stage:', stage)
    
    if (!stage) {
      console.warn('No stage available for loading secrets')
      return
    }
    
    // Load vaults first to get all available vault names
    console.log('Loading vaults...')
    const vaultsResponse = await window.$api.listVaults()
    console.log('Vaults response:', vaultsResponse)
    
    // Extract vaults array from response
    const vaults = vaultsResponse.vaults || vaultsResponse || []
    console.log('Found vaults:', vaults)
    
    // Load secrets from all vaults for this stage
    const allSecrets = []
    
    // Ensure vaults is an array
    if (!Array.isArray(vaults)) {
      console.error('Vaults is not an array:', vaults)
      return
    }
    
    for (const vault of vaults) {
      try {
        const vaultSlug = vault.slug || vault.name || vault
        console.log(`Loading secrets from vault ${vaultSlug} for stage ${stage}...`)
        const response = await window.$api.listSecrets(vaultSlug, stage, false)
        console.log(`Response from ${vaultSlug}:`, response)
        
        const secrets = response.secrets || response || []
        const secretCount = Array.isArray(secrets) ? secrets.length : 0
        console.log(`Found ${secretCount} secrets in ${vaultSlug}`)
        
        if (Array.isArray(secrets)) {
          for (const secret of secrets) {
            allSecrets.push({
              vault: vaultSlug,
              key: secret.key || secret,
              description: secret.description || ''
            })
          }
        }
      } catch (error) {
        console.error(`Failed to load secrets from vault ${vault.slug || vault}:`, error)
      }
    }
    
    console.log(`Total: Loaded ${allSecrets.length} secrets across ${vaults.length} vaults for stage ${stage}`)
    console.log('Sample secrets:', allSecrets.slice(0, 3))
    
    // Store secrets for editor
    secretsForEditor.value = allSecrets
    
    // Try to update editor if it's ready
    await nextTick()
    if (editorRef.value) {
      console.log('Editor is ready, updating secrets immediately')
      editorRef.value.updateSecrets(allSecrets)
    } else {
      console.log('Editor not ready yet, will update via watcher')
    }
  } catch (error) {
    console.error('Failed to load secrets for autocomplete:', error)
  }
}

async function saveTemplate() {
  if (!isModified.value) return

  saving.value = true
  validationErrors.value = []

  try {
    await window.$api.put(`/templates/${encodeURIComponent(props.template.filename)}`, {
      content: content.value
    })

    originalContent.value = content.value
    showToast('Template saved successfully', 'success')
    emit('saved')
  } catch (error) {
    showToast('Failed to save template', 'error')
    console.error('Failed to save template:', error)
  } finally {
    saving.value = false
  }
}

async function validateTemplate() {
  validating.value = true
  validationErrors.value = []
  validationStatus.value = ''
  validationMessage.value = ''

  try {
    const response = await window.$api.post('/templates/validate', {
      content: content.value,
      stage: templateData.value.stage
    })

    if (!response.valid) {
      validationErrors.value = response.errors.map(e =>
          `Line ${e.line}: ${e.key} - ${e.error}`
      )
      validationStatus.value = 'invalid'
      validationMessage.value = `${response.errors.length} error${response.errors.length !== 1 ? 's' : ''} found`
    } else {
      validationErrors.value = []
      
      if (response.warnings && response.warnings.length > 0) {
        const unusedCount = response.warnings.filter(w => w.type === 'unused').length
        if (unusedCount > 0) {
          validationStatus.value = 'warning'
          validationMessage.value = `Valid with ${unusedCount} unused secret${unusedCount !== 1 ? 's' : ''}`
        } else {
          validationStatus.value = 'valid'
          validationMessage.value = 'Template is valid'
        }
      } else {
        validationStatus.value = 'valid'
        validationMessage.value = 'Template is valid'
      }
    }
  } catch (error) {
    validationStatus.value = 'invalid'
    validationMessage.value = 'Validation failed'
    console.error('Failed to validate template:', error)
  } finally {
    validating.value = false
    
    // Clear success status after 5 seconds, keep errors visible
    if (validationStatus.value === 'valid') {
      setTimeout(() => {
        validationStatus.value = ''
        validationMessage.value = ''
      }, 5000)
    }
  }
}


function formatContent() {
  // Basic formatting: ensure consistent spacing and organization
  const lines = content.value.split('\n')
  const formatted = []
  let lastWasEmpty = false

  for (const line of lines) {
    const trimmed = line.trim()

    // Skip multiple empty lines
    if (trimmed === '') {
      if (!lastWasEmpty) {
        formatted.push('')
        lastWasEmpty = true
      }
      continue
    }

    lastWasEmpty = false

    // Format based on content type
    if (trimmed.startsWith('#')) {
      // Comment line - preserve as is
      formatted.push(line)
    } else if (trimmed.includes('=')) {
      // Environment variable line
      const [key, ...valueParts] = trimmed.split('=')
      const value = valueParts.join('=')
      formatted.push(`${key.trim()}=${value.trim()}`)
    } else {
      formatted.push(line)
    }
  }

  content.value = formatted.join('\n').trim() + '\n'
  showToast('Template formatted', 'success')
}

function updatePlaceholders() {
  // Extract placeholders from content
  const regex = /\{([^:}]+):([^}]+)\}/g
  const found = new Set()
  let match

  while ((match = regex.exec(content.value)) !== null) {
    found.add(match[0])
  }

  placeholders.value = Array.from(found).sort()
}

// Watch for content changes to update placeholders and clear validation
watch(content, () => {
  updatePlaceholders()
  // Clear validation status when content changes
  if (validationStatus.value) {
    validationStatus.value = ''
    validationMessage.value = ''
    validationErrors.value = []
  }
})

// Watch for editor to be ready and update secrets
watch(editorRef, (newRef) => {
  if (newRef && secretsForEditor.value.length > 0) {
    console.log('Editor became available, updating with', secretsForEditor.value.length, 'secrets')
    newRef.updateSecrets(secretsForEditor.value)
  }
})
</script>

<style scoped>
textarea {
  padding-left: 3.5rem; /* Space for line numbers */
}
</style>