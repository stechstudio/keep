<template>
  <div class="min-h-screen bg-background text-foreground">
    <!-- Navigation Bar -->
    <nav class="border-b border-border">
      <div class="max-w-full px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo & Title -->
          <div class="flex items-center">
            <svg class="w-8 h-8 mr-3 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="10" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            <span class="text-xl font-semibold">Keep</span>
          </div>

          <!-- Pill Navigation -->
          <div class="flex items-center space-x-2 bg-muted rounded-full p-1">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                activeTab === tab.id
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              ]"
            >
              {{ tab.label }}
            </button>
          </div>

          <!-- Vault & Stage Selectors -->
          <div class="flex items-center space-x-4">
            <VaultStageSelector 
              v-model:vault="selectedVault"
              v-model:stage="selectedStage"
              :vaults="vaults"
              :stages="stages"
            />
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-full px-4 sm:px-6 lg:px-8 py-6">
      <!-- Secrets Tab -->
      <div v-if="activeTab === 'secrets'">
        <SecretsTable
          :vault="selectedVault"
          :stage="selectedStage"
          @refresh="loadSecrets"
        />
      </div>

      <!-- Diff Tab -->
      <div v-if="activeTab === 'diff'">
        <DiffView />
      </div>

      <!-- Settings Tab -->
      <div v-if="activeTab === 'settings'">
        <SettingsView />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import VaultStageSelector from './components/VaultStageSelector.vue'
import SecretsTable from './components/SecretsTable.vue'
import DiffView from './components/DiffView.vue'
import SettingsView from './components/SettingsView.vue'

const activeTab = ref('secrets')
const tabs = [
  { id: 'secrets', label: 'Secrets' },
  { id: 'diff', label: 'Diff' },
  { id: 'settings', label: 'Settings' }
]

const selectedVault = ref('')
const selectedStage = ref('')
const vaults = ref([])
const stages = ref([])

onMounted(async () => {
  await loadVaultsAndStages()
})

async function loadVaultsAndStages() {
  try {
    const [vaultsData, stagesData] = await Promise.all([
      window.$api.listVaults(),
      window.$api.listStages()
    ])
    vaults.value = vaultsData.vaults || []
    stages.value = stagesData.stages || []
    
    // Set defaults
    if (vaults.value.length && !selectedVault.value) {
      selectedVault.value = vaults.value[0]
    }
    if (stages.value.length && !selectedStage.value) {
      selectedStage.value = stages.value[0]
    }
  } catch (error) {
    console.error('Failed to load vaults and stages:', error)
  }
}

async function loadSecrets() {
  // This will be called by child components when they need a refresh
}
</script>