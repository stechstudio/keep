<template>
  <div class="max-w-4xl">
    <h1 class="text-2xl font-semibold mb-6">Settings</h1>
    
    <!-- Vault Configuration -->
    <div class="mb-8">
      <h2 class="text-lg font-medium mb-4">Vault Configuration</h2>
      <div class="border border-border rounded-lg p-4">
        <div v-if="vaultResults" class="space-y-3">
          <div v-for="(result, vault) in vaultResults" :key="vault" class="flex items-center justify-between py-2">
            <div class="flex items-center space-x-3">
              <div 
                :class="[
                  'w-2 h-2 rounded-full',
                  result.success ? 'bg-green-500' : 'bg-destructive'
                ]"
              />
              <span class="font-medium">{{ vault }}</span>
            </div>
            <span 
              v-if="!result.success" 
              class="text-sm text-destructive"
            >
              {{ result.error }}
            </span>
            <span 
              v-else
              class="text-sm text-muted-foreground"
            >
              Connected
            </span>
          </div>
        </div>
        
        <button
          @click="verifyVaults"
          :disabled="verifying"
          class="mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50"
        >
          {{ verifying ? 'Verifying...' : 'Verify All Vaults' }}
        </button>
      </div>
    </div>
    
    <!-- Stage Configuration -->
    <div class="mb-8">
      <h2 class="text-lg font-medium mb-4">Stages</h2>
      <div class="border border-border rounded-lg p-4">
        <div class="space-y-2">
          <div v-for="stage in stages" :key="stage" class="flex items-center justify-between py-2">
            <span>{{ stage }}</span>
            <button
              v-if="stage !== 'local'"
              @click="removeStage(stage)"
              class="p-1 rounded-md hover:bg-muted transition-colors text-destructive"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
        
        <div class="flex items-center space-x-2 mt-4">
          <input
            v-model="newStage"
            @keyup.enter="addStage"
            type="text"
            placeholder="Add new stage..."
            class="flex-1 px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          />
          <button
            @click="addStage"
            :disabled="!newStage"
            class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50"
          >
            Add Stage
          </button>
        </div>
      </div>
    </div>
    
    <!-- Server Information -->
    <div class="mb-8">
      <h2 class="text-lg font-medium mb-4">Server Information</h2>
      <div class="border border-border rounded-lg p-4 space-y-2 text-sm">
        <div class="flex items-center justify-between">
          <span class="text-muted-foreground">Server URL</span>
          <span class="font-mono">{{ window.location.origin }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-muted-foreground">Keep Version</span>
          <span class="font-mono">v1.0.0-beta</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-muted-foreground">Authentication</span>
          <span class="text-green-500">Token Injected</span>
        </div>
      </div>
    </div>
    
    <!-- Keyboard Shortcuts -->
    <div>
      <h2 class="text-lg font-medium mb-4">Keyboard Shortcuts</h2>
      <div class="border border-border rounded-lg p-4 space-y-2 text-sm">
        <div class="grid grid-cols-2 gap-4">
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground">Search</span>
            <kbd class="px-2 py-1 bg-muted rounded text-xs">/</kbd>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground">New Secret</span>
            <kbd class="px-2 py-1 bg-muted rounded text-xs">n</kbd>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground">Settings</span>
            <kbd class="px-2 py-1 bg-muted rounded text-xs">s</kbd>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const stages = ref([])
const newStage = ref('')
const vaultResults = ref(null)
const verifying = ref(false)

onMounted(async () => {
  await loadStages()
})

async function loadStages() {
  try {
    const data = await window.$api.listStages()
    stages.value = data.stages || []
  } catch (error) {
    console.error('Failed to load stages:', error)
  }
}

async function addStage() {
  if (!newStage.value) return
  
  if (!stages.value.includes(newStage.value)) {
    stages.value.push(newStage.value)
    // In a real implementation, this would save to the backend
    newStage.value = ''
  }
}

function removeStage(stage) {
  const index = stages.value.indexOf(stage)
  if (index > -1) {
    stages.value.splice(index, 1)
    // In a real implementation, this would save to the backend
  }
}

async function verifyVaults() {
  verifying.value = true
  vaultResults.value = null
  
  try {
    const data = await window.$api.verifyVaults()
    vaultResults.value = data.results || {}
  } catch (error) {
    console.error('Failed to verify vaults:', error)
  } finally {
    verifying.value = false
  }
}

// Keyboard shortcuts
onMounted(() => {
  const handleKeypress = (e) => {
    // Don't trigger if user is typing in an input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return
    
    switch(e.key) {
      case '/':
        e.preventDefault()
        document.querySelector('input[placeholder*="Search"]')?.focus()
        break
      case 'n':
        e.preventDefault()
        document.querySelector('button:has(span:contains("Add Secret"))')?.click()
        break
      case 's':
        e.preventDefault()
        // Switch to settings tab
        const settingsTab = Array.from(document.querySelectorAll('button')).find(
          btn => btn.textContent === 'Settings'
        )
        settingsTab?.click()
        break
    }
  }
  
  document.addEventListener('keypress', handleKeypress)
  
  return () => {
    document.removeEventListener('keypress', handleKeypress)
  }
})
</script>