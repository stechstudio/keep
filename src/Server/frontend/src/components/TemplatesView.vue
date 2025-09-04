<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold">Environment Templates</h1>
        <p class="text-muted-foreground mt-1">
          Manage .env templates for different stages
        </p>
      </div>
      <button 
        @click="showCreateModal = true"
        class="flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Create Template
      </button>
    </div>

    <!-- Template Path Display -->
    <div class="bg-muted/50 rounded-lg p-4">
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
        </svg>
        <span class="text-sm text-muted-foreground">Template Directory:</span>
        <span class="text-sm font-mono">{{ templatePath }}</span>
      </div>
    </div>

    <!-- Templates Table -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>

    <div v-else-if="templates.length === 0" class="text-center py-12 bg-muted/30 rounded-lg">
      <svg class="w-12 h-12 mx-auto text-muted-foreground mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      <p class="text-muted-foreground mb-2">No templates found</p>
      <p class="text-sm text-muted-foreground">Create your first template to get started</p>
    </div>

    <div v-else class="bg-background border border-border rounded-lg overflow-hidden">
      <table class="w-full">
        <thead class="bg-muted/50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Template</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Stage</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Size</th>
            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Last Modified</th>
            <th class="px-4 py-3 text-right text-sm font-medium text-muted-foreground">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <tr v-for="template in templates" :key="template.filename" class="hover:bg-muted/30 transition-colors">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="font-mono text-sm">{{ template.filename }}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                {{ template.stageDisplay || template.stage }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-muted-foreground">
              {{ formatSize(template.size) }}
            </td>
            <td class="px-4 py-3 text-sm text-muted-foreground">
              {{ formatDate(template.lastModified) }}
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <button
                  @click="editTemplate(template)"
                  class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  title="Edit Template"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                </button>
                <button
                  @click="testTemplate(template)"
                  class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  title="Test Template"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </button>
                <button
                  @click="processTemplate(template)"
                  class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  title="Process Template"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                  </svg>
                </button>
                <button
                  @click="deleteTemplate(template)"
                  class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded transition-colors"
                  title="Delete Template"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modals -->
    <CreateTemplateModal 
      v-if="showCreateModal"
      @close="showCreateModal = false"
      @created="onTemplateCreated"
    />

    <TemplateEditorModal
      v-if="editingTemplate"
      :template="editingTemplate"
      @close="editingTemplate = null"
      @saved="onTemplateSaved"
    />

    <TemplateTesterModal
      v-if="testingTemplate"
      :template="testingTemplate"
      @close="testingTemplate = null"
    />

    <TemplateProcessorModal
      v-if="processingTemplate"
      :template="processingTemplate"
      @close="processingTemplate = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from '../composables/useToast'
import CreateTemplateModal from './CreateTemplateModal.vue'
import TemplateEditorModal from './TemplateEditorModal.vue'
import TemplateTesterModal from './TemplateTesterModal.vue'
import TemplateProcessorModal from './TemplateProcessorModal.vue'

const { showToast } = useToast()

const loading = ref(false)
const templates = ref([])
const templatePath = ref('')
const showCreateModal = ref(false)
const editingTemplate = ref(null)
const testingTemplate = ref(null)
const processingTemplate = ref(null)

onMounted(() => {
  loadTemplates()
})

async function loadTemplates() {
  loading.value = true
  try {
    const response = await window.$api.get('/api/templates')
    templates.value = response.templates || []
    templatePath.value = response.templatePath || 'env'
  } catch (error) {
    showToast('Failed to load templates', 'error')
    console.error('Failed to load templates:', error)
  } finally {
    loading.value = false
  }
}

function editTemplate(template) {
  editingTemplate.value = template
}

function testTemplate(template) {
  testingTemplate.value = template
}

function processTemplate(template) {
  processingTemplate.value = template
}

async function deleteTemplate(template) {
  if (!confirm(`Are you sure you want to delete ${template.filename}?`)) {
    return
  }

  try {
    await window.$api.delete(`/api/templates/${encodeURIComponent(template.filename)}`)
    showToast('Template deleted successfully', 'success')
    await loadTemplates()
  } catch (error) {
    showToast('Failed to delete template', 'error')
    console.error('Failed to delete template:', error)
  }
}

function onTemplateCreated() {
  showCreateModal.value = false
  loadTemplates()
}

function onTemplateSaved() {
  editingTemplate.value = null
  loadTemplates()
}

function formatSize(bytes) {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 10) / 10 + ' ' + sizes[i]
}

function formatDate(timestamp) {
  if (!timestamp) return 'Unknown'
  const date = new Date(timestamp * 1000)
  const now = new Date()
  const diff = now - date
  
  // Less than a minute
  if (diff < 60000) return 'Just now'
  
  // Less than an hour
  if (diff < 3600000) {
    const minutes = Math.floor(diff / 60000)
    return `${minutes} minute${minutes === 1 ? '' : 's'} ago`
  }
  
  // Less than a day
  if (diff < 86400000) {
    const hours = Math.floor(diff / 3600000)
    return `${hours} hour${hours === 1 ? '' : 's'} ago`
  }
  
  // Less than a week
  if (diff < 604800000) {
    const days = Math.floor(diff / 86400000)
    return `${days} day${days === 1 ? '' : 's'} ago`
  }
  
  // Default to date string
  return date.toLocaleDateString()
}
</script>