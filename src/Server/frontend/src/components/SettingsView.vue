<template>
  <div class="flex gap-6">
    <!-- Left Sidebar Navigation -->
    <div class="w-48 flex-shrink-0">
      <div class="bg-muted rounded-full p-1 flex flex-col space-y-1">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'px-4 py-2 rounded-full text-sm font-medium transition-colors',
            activeTab === tab.id
              ? 'bg-background text-foreground shadow-sm'
              : 'text-muted-foreground hover:text-foreground'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 max-w-3xl">
      <!-- General Settings -->
      <div v-if="activeTab === 'general'" class="space-y-6">
        <div>
          <h1 class="text-2xl font-semibold mb-2">General Settings</h1>
          <p class="text-sm text-muted-foreground">Configure your Keep application settings</p>
        </div>

        <div class="border border-border rounded-lg p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium mb-2">Application Name</label>
            <input
              v-model="settings.appName"
              type="text"
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
              placeholder="MyApp"
            />
            <p class="text-xs text-muted-foreground mt-1">The name of your application</p>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">Default Vault</label>
            <select 
              v-model="settings.defaultVault"
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Select default vault...</option>
              <option v-for="vault in vaults" :key="vault.slug" :value="vault.slug">
                {{ vault.name }}
              </option>
            </select>
            <p class="text-xs text-muted-foreground mt-1">The default vault to use when none is specified</p>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">Default Stage</label>
            <select 
              v-model="settings.defaultStage"
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Select default stage...</option>
              <option v-for="stage in stages" :key="stage" :value="stage">
                {{ stage }}
              </option>
            </select>
            <p class="text-xs text-muted-foreground mt-1">The default stage to use when none is specified</p>
          </div>

          <div class="pt-4 flex justify-end">
            <button
              @click="saveGeneralSettings"
              class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
            >
              Save Changes
            </button>
          </div>
        </div>

        <!-- Server Information -->
        <div>
          <h2 class="text-lg font-medium mb-4">Server Information</h2>
          <div class="border border-border rounded-lg p-4 space-y-2 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Server URL</span>
              <span class="font-mono">{{ serverUrl }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Keep Version</span>
              <span class="font-mono">{{ keepVersion }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Authentication</span>
              <span class="text-green-500">Token Active</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Vaults Settings -->
      <div v-if="activeTab === 'vaults'" class="space-y-6">
        <div>
          <h1 class="text-2xl font-semibold mb-2">Vault Configuration</h1>
          <p class="text-sm text-muted-foreground">Manage your vault connections and settings</p>
        </div>

        <!-- Vault List -->
        <div class="space-y-3">
          <div 
            v-for="vault in vaults" 
            :key="vault.slug"
            class="border border-border rounded-lg p-4"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center space-x-3 mb-2">
                  <h3 class="font-medium">{{ vault.name }}</h3>
                  <span v-if="vault.isDefault" class="px-2 py-0.5 bg-primary/10 text-primary text-xs rounded-full">
                    Default
                  </span>
                </div>
                <div class="space-y-1 text-sm text-muted-foreground">
                  <p>Driver: <span class="font-mono">{{ vault.driver }}</span></p>
                  <p>Slug: <span class="font-mono">{{ vault.slug }}</span></p>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="editVault(vault)"
                  class="p-2 rounded-md hover:bg-muted transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="deleteVault(vault)"
                  class="p-2 rounded-md hover:bg-muted transition-colors text-destructive"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Vault Button -->
        <div class="flex justify-between items-center pt-4">
          <button
            @click="showVaultModal = true"
            class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
          >
            Add New Vault
          </button>
          <button
            @click="verifyAllVaults"
            :disabled="verifying"
            class="px-4 py-2 border border-border rounded-md hover:bg-muted transition-colors text-sm font-medium disabled:opacity-50"
          >
            {{ verifying ? 'Verifying...' : 'Verify All Vaults' }}
          </button>
        </div>
      </div>

      <!-- Stages Settings -->
      <div v-if="activeTab === 'stages'" class="space-y-6">
        <div>
          <h1 class="text-2xl font-semibold mb-2">Stage Configuration</h1>
          <p class="text-sm text-muted-foreground">Manage your deployment stages and environments</p>
        </div>

        <div class="border border-border rounded-lg p-4">
          <div class="space-y-2">
            <div 
              v-for="stage in stages" 
              :key="stage"
              class="flex items-center justify-between py-3 border-b border-border last:border-0"
            >
              <div class="flex items-center space-x-3">
                <span class="font-medium">{{ stage }}</span>
                <span 
                  v-if="isDefaultStage(stage)" 
                  class="px-2 py-0.5 bg-muted text-xs rounded-full text-muted-foreground"
                >
                  System
                </span>
              </div>
              <button
                v-if="!isDefaultStage(stage)"
                @click="removeStage(stage)"
                class="p-2 rounded-md hover:bg-muted transition-colors text-destructive"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
          
          <div class="flex items-center space-x-2 mt-4 pt-4 border-t border-border">
            <input
              v-model="newStage"
              @keyup.enter="addStage"
              type="text"
              placeholder="Enter new stage name..."
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

        <div class="bg-muted/30 rounded-lg p-4 text-sm text-muted-foreground">
          <p class="font-medium mb-1">About Stages</p>
          <p>Stages represent different deployment environments for your secrets. Common stages include development, staging, and production. System stages (local, staging, production) cannot be removed.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Vault Modal -->
  <div v-if="showVaultModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeVaultModal">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
      <h2 class="text-lg font-semibold mb-4">{{ editingVault ? 'Edit Vault' : 'Add New Vault' }}</h2>
      
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Name</label>
          <input
            v-model="vaultForm.name"
            type="text"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            placeholder="AWS Secrets Manager"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Slug</label>
          <input
            v-model="vaultForm.slug"
            type="text"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            placeholder="aws-secrets"
            :disabled="!!editingVault"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Driver</label>
          <select 
            v-model="vaultForm.driver"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="">Select driver...</option>
            <option value="secretsmanager">AWS Secrets Manager</option>
            <option value="ssm">AWS SSM Parameter Store</option>
            <option value="test">Test Vault</option>
          </select>
        </div>

        <div class="flex items-center space-x-2">
          <input
            v-model="vaultForm.isDefault"
            type="checkbox"
            id="vault-default"
            class="rounded border-border"
          />
          <label for="vault-default" class="text-sm">Set as default vault</label>
        </div>
      </div>

      <div class="flex justify-end space-x-2 mt-6">
        <button
          @click="closeVaultModal"
          class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
        >
          Cancel
        </button>
        <button
          @click="saveVault"
          class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
        >
          {{ editingVault ? 'Save Changes' : 'Add Vault' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from '../composables/useToast'

const toast = useToast()

// Navigation
const tabs = [
  { id: 'general', label: 'General' },
  { id: 'vaults', label: 'Vaults' },
  { id: 'stages', label: 'Stages' }
]
const activeTab = ref('general')

// General settings
const settings = ref({
  appName: '',
  defaultVault: '',
  defaultStage: ''
})
const keepVersion = ref('1.0.0-beta')

// Vaults
const vaults = ref([])
const showVaultModal = ref(false)
const editingVault = ref(null)
const vaultForm = ref({
  name: '',
  slug: '',
  driver: '',
  isDefault: false
})
const verifying = ref(false)

// Stages
const stages = ref([])
const newStage = ref('')
const defaultStages = ['local', 'staging', 'production']

// Computed
const isDefaultStage = (stage) => defaultStages.includes(stage)
const serverUrl = computed(() => typeof window !== 'undefined' ? window.location.origin : '')

// Lifecycle
onMounted(async () => {
  await loadSettings()
  await loadVaults()
  await loadStages()
})

// General Settings Functions
async function loadSettings() {
  try {
    const data = await window.$api.getSettings()
    settings.value.appName = data.app_name || 'MyApp'
    settings.value.defaultVault = data.default_vault || ''
    settings.value.defaultStage = data.default_stage || 'prod'
    keepVersion.value = data.keep_version || '1.0.0-beta'
  } catch (error) {
    console.error('Failed to load settings:', error)
    toast.error('Failed to load settings', error.message)
  }
}

async function saveGeneralSettings() {
  try {
    await window.$api.updateSettings({
      app_name: settings.value.appName,
      default_vault: settings.value.defaultVault,
      default_stage: settings.value.defaultStage
    })
    toast.success('Settings saved', 'Your changes have been saved successfully')
  } catch (error) {
    toast.error('Failed to save settings', error.message)
  }
}

// Vault Functions
async function loadVaults() {
  try {
    const data = await window.$api.listVaults()
    vaults.value = data.vaults || []
  } catch (error) {
    console.error('Failed to load vaults:', error)
    toast.error('Failed to load vaults', error.message)
  }
}

function editVault(vault) {
  editingVault.value = vault
  vaultForm.value = {
    name: vault.name,
    slug: vault.slug,
    driver: vault.driver,
    isDefault: vault.isDefault || false
  }
  showVaultModal.value = true
}

async function deleteVault(vault) {
  if (confirm(`Are you sure you want to delete the vault "${vault.name}"?`)) {
    try {
      await window.$api.deleteVault(vault.slug)
      vaults.value = vaults.value.filter(v => v.slug !== vault.slug)
      toast.success('Vault deleted', `Vault "${vault.name}" has been deleted`)
    } catch (error) {
      toast.error('Failed to delete vault', error.message)
    }
  }
}

function closeVaultModal() {
  showVaultModal.value = false
  editingVault.value = null
  vaultForm.value = {
    name: '',
    slug: '',
    driver: '',
    isDefault: false
  }
}

async function saveVault() {
  try {
    if (editingVault.value) {
      await window.$api.updateVault(editingVault.value.slug, vaultForm.value)
      const index = vaults.value.findIndex(v => v.slug === editingVault.value.slug)
      if (index !== -1) {
        vaults.value[index] = { ...vaultForm.value }
      }
      toast.success('Vault updated', `Vault "${vaultForm.value.name}" has been updated`)
    } else {
      await window.$api.addVault(vaultForm.value)
      vaults.value.push({ ...vaultForm.value })
      toast.success('Vault added', `Vault "${vaultForm.value.name}" has been added`)
    }
    closeVaultModal()
  } catch (error) {
    toast.error('Failed to save vault', error.message)
  }
}

async function verifyAllVaults() {
  verifying.value = true
  try {
    const data = await window.$api.verifyVaults()
    const results = data.results || {}
    const failedVaults = Object.entries(results).filter(([_, result]) => !result.success)
    
    if (failedVaults.length === 0) {
      toast.success('All vaults verified', 'All vaults are properly configured and accessible')
    } else {
      toast.error('Verification failed', `${failedVaults.length} vault(s) failed verification`)
    }
  } catch (error) {
    toast.error('Failed to verify vaults', error.message)
  } finally {
    verifying.value = false
  }
}

// Stage Functions
async function loadStages() {
  try {
    const data = await window.$api.listStages()
    stages.value = data.stages || []
  } catch (error) {
    console.error('Failed to load stages:', error)
    toast.error('Failed to load stages', error.message)
  }
}

async function addStage() {
  if (!newStage.value) return
  
  if (stages.value.includes(newStage.value)) {
    toast.error('Stage exists', `Stage "${newStage.value}" already exists`)
    return
  }
  
  try {
    await window.$api.addStage(newStage.value)
    stages.value.push(newStage.value)
    toast.success('Stage added', `Stage "${newStage.value}" has been added`)
    newStage.value = ''
  } catch (error) {
    toast.error('Failed to add stage', error.message)
  }
}

async function removeStage(stage) {
  if (confirm(`Are you sure you want to remove the stage "${stage}"?`)) {
    try {
      await window.$api.removeStage(stage)
      const index = stages.value.indexOf(stage)
      if (index > -1) {
        stages.value.splice(index, 1)
        toast.success('Stage removed', `Stage "${stage}" has been removed`)
      }
    } catch (error) {
      toast.error('Failed to remove stage', error.message)
    }
  }
}
</script>