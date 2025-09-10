<template>
  <div class="relative">
    <!-- Three-dot menu button - only show if user has some permissions -->
    <button
      v-if="permissions.read || permissions.write"
      @click="toggleMenu($event)"
      class="p-2 rounded hover:bg-muted transition-colors"
      title="Actions"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
      </svg>
    </button>
    
    <!-- Dropdown menu -->
    <div
      v-if="menuOpen"
      @click.stop
      class="absolute right-0 mt-2 w-48 bg-popover border border-border rounded-md shadow-lg z-20"
    >
      <div class="py-1">
        <button
          v-if="permissions.write"
          @click="showImportWizard = true; menuOpen = false"
          class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors flex items-center space-x-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <span>Import .env File</span>
        </button>
        
        <button
          v-if="permissions.read"
          @click="showExportModal = true; menuOpen = false"
          class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors flex items-center space-x-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>Download Secrets</span>
        </button>
      </div>
    </div>
    
    <!-- Import Wizard Modal -->
    <ImportWizard
      v-if="showImportWizard"
      :vault="vault"
      :env="env"
      @imported="handleImported"
      @close="showImportWizard = false"
    />
    
    <!-- Export Modal -->
    <ExportModal
      v-if="showExportModal"
      :vault="vault"
      :env="env"
      :secrets="secrets"
      @close="showExportModal = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import ImportWizard from './ImportWizard.vue'
import ExportModal from './ExportModal.vue'

const props = defineProps({
  vault: String,
  env: String,
  secrets: {
    type: Array,
    default: () => []
  },
  permissions: {
    type: Object,
    default: () => ({ list: true, read: true, write: true, delete: true, history: true })
  }
})

const emit = defineEmits(['imported'])

const menuOpen = ref(false)
const showImportWizard = ref(false)
const showExportModal = ref(false)

function toggleMenu(event) {
  event.stopPropagation()
  menuOpen.value = !menuOpen.value
}

function handleImported() {
  showImportWizard.value = false
  emit('imported')
}

function closeMenu() {
  menuOpen.value = false
}

// Handle click outside
function handleClickOutside(event) {
  // Check if click is outside the component
  const button = event.target.closest('button')
  const menu = event.target.closest('.absolute.right-0')
  
  if (!button && !menu) {
    closeMenu()
  }
}

// Add/remove document listener when menu opens/closes
watch(menuOpen, (isOpen) => {
  if (isOpen) {
    // Add listener on next tick to avoid immediate close
    setTimeout(() => {
      document.addEventListener('click', handleClickOutside)
    }, 0)
  } else {
    document.removeEventListener('click', handleClickOutside)
  }
})

// Clean up listener on unmount
onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>