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
              <span class="text-xs text-muted-foreground">
                {{ placeholders.length }} placeholder{{ placeholders.length !== 1 ? 's' : '' }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="insertPlaceholder"
                class="px-3 py-1 text-xs bg-background border border-border rounded hover:bg-muted transition-colors"
              >
                Insert Placeholder
              </button>
              <button
                @click="formatContent"
                class="px-3 py-1 text-xs bg-background border border-border rounded hover:bg-muted transition-colors"
              >
                Format
              </button>
            </div>
          </div>

          <!-- Editor -->
          <div class="relative">
            <textarea
              ref="editorRef"
              v-model="content"
              @input="updatePlaceholders"
              class="w-full h-96 px-4 py-3 font-mono text-sm bg-muted/30 border border-border rounded-md focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
              spellcheck="false"
              placeholder="# Enter your template content here...
# Use {vault:key} syntax for placeholders"
            ></textarea>

            <!-- Line Numbers (optional enhancement) -->
            <div class="absolute top-0 left-0 w-12 h-full bg-muted/50 border-r border-border rounded-l-md pointer-events-none">
              <div class="py-3 text-right pr-2">
                <div v-for="line in lineNumbers" :key="line" class="text-xs text-muted-foreground h-5">
                  {{ line }}
                </div>
              </div>
            </div>
          </div>

          <!-- Placeholder List -->
          <div v-if="placeholders.length > 0" class="bg-muted/30 rounded-md p-3">
            <h3 class="text-sm font-medium mb-2">Detected Placeholders</h3>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="placeholder in placeholders"
                :key="placeholder"
                class="inline-flex items-center px-2 py-1 text-xs font-mono bg-background border border-border rounded"
              >
                {{ placeholder }}
              </span>
            </div>
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
          <div class="flex gap-2">
            <button
              @click="validateTemplate"
              :disabled="validating"
              class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors disabled:opacity-50"
            >
              {{ validating ? 'Validating...' : 'Validate' }}
            </button>
            <button
              @click="testTemplate"
              class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              Test
            </button>
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

        <!-- Placeholder Selector Modal -->
        <PlaceholderSelector
          v-if="showPlaceholderSelector"
          :stage="templateData.stage"
          @close="showPlaceholderSelector = false"
          @select="onPlaceholderSelect"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from '../composables/useToast'
import PlaceholderSelector from './PlaceholderSelector.vue'

const props = defineProps({
  template: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])
const { showToast } = useToast()

const editorRef = ref(null)
const loading = ref(true)
const saving = ref(false)
const validating = ref(false)
const content = ref('')
const originalContent = ref('')
const templateData = ref({})
const placeholders = ref([])
const validationErrors = ref([])
const showPlaceholderSelector = ref(false)

const isModified = computed(() => content.value !== originalContent.value)
const lineNumbers = computed(() => {
  const lines = content.value.split('\n').length
  return Array.from({ length: lines }, (_, i) => i + 1)
})

onMounted(async () => {
  await loadTemplate()
})

async function loadTemplate() {
  loading.value = true
  try {
    const response = await window.$api.get(`/templates/${encodeURIComponent(props.template.filename)}`)
    templateData.value = response
    content.value = response.content || ''
    originalContent.value = response.content || ''
    placeholders.value = response.placeholders || []
  } catch (error) {
    showToast('Failed to load template', 'error')
    console.error('Failed to load template:', error)
    emit('close')
  } finally {
    loading.value = false
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

  try {
    const response = await window.$api.post('/templates/validate', {
      content: content.value,
      stage: templateData.value.stage
    })

    if (!response.valid) {
      validationErrors.value = response.errors.map(e => 
        `Line ${e.line}: ${e.key} - ${e.error}`
      )
      showToast('Template has validation errors', 'warning')
    } else {
      showToast('Template is valid', 'success')
      
      if (response.warnings && response.warnings.length > 0) {
        const unusedCount = response.warnings.filter(w => w.type === 'unused').length
        if (unusedCount > 0) {
          showToast(`${unusedCount} unused secret${unusedCount !== 1 ? 's' : ''} found`, 'info')
        }
      }
    }
  } catch (error) {
    showToast('Failed to validate template', 'error')
    console.error('Failed to validate template:', error)
  } finally {
    validating.value = false
  }
}

function testTemplate() {
  // This would open a test modal or navigate to test view
  // For now, just save and emit an event
  if (isModified.value) {
    showToast('Please save changes before testing', 'warning')
    return
  }
  emit('close')
  // Could emit a 'test' event to parent to open test modal
}

function insertPlaceholder() {
  showPlaceholderSelector.value = true
}

function onPlaceholderSelect(placeholder) {
  const textarea = editorRef.value
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const text = textarea.value
  
  const newText = text.substring(0, start) + placeholder + text.substring(end)
  content.value = newText
  
  // Set cursor position after inserted placeholder
  setTimeout(() => {
    textarea.selectionStart = textarea.selectionEnd = start + placeholder.length
    textarea.focus()
  }, 0)
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

// Watch for content changes to update placeholders
watch(content, updatePlaceholders)
</script>

<style scoped>
textarea {
  padding-left: 3.5rem; /* Space for line numbers */
}
</style>