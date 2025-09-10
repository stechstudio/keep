<template>
  <div class="flex gap-6 mx-auto max-w-5xl">
    <!-- Left Sidebar Navigation -->
    <div class="w-48 flex-shrink-0">
      <div class="space-y-1">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'w-full px-4 py-2 rounded-md text-sm font-medium transition-colors text-left',
            activeTab === tab.id
              ? 'bg-muted text-foreground'
              : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1">
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
            <label class="block text-sm font-medium mb-2">Namespace</label>
            <input
              v-model="settings.namespace"
              type="text"
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
              placeholder="my-app"
            />
            <p class="text-xs text-muted-foreground mt-1">All secrets will be organized under this namespace</p>
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
            <label class="block text-sm font-medium mb-2">Template Directory</label>
            <input
              v-model="settings.templatePath"
              type="text"
              class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
              placeholder="env"
            />
            <p class="text-xs text-muted-foreground mt-1">Directory where .env template files are stored</p>
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
                  <p v-if="vault.scope">Scope: <span class="font-mono">{{ vault.scope }}</span></p>
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
            @click="openAddVaultModal()"
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

      <!-- Envs Settings -->
      <div v-if="activeTab === 'envs'" class="space-y-6">
        <div>
          <h1 class="text-2xl font-semibold mb-2">Env Configuration</h1>
          <p class="text-sm text-muted-foreground">Manage your deployment envs and environments</p>
        </div>

        <div class="border border-border rounded-lg p-4">
          <div class="space-y-2">
            <div 
              v-for="env in envs" 
              :key="env"
              class="flex items-center justify-between py-3 border-b border-border last:border-0"
            >
              <span class="font-medium">{{ env }}</span>
              <button
                @click="removeEnv(env)"
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
              v-model="newEnv"
              @keyup.enter="addEnv"
              type="text"
              placeholder="Enter new env name..."
              class="flex-1 px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <button
              @click="addEnv"
              :disabled="!newEnv"
              class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50"
            >
              Add Env
            </button>
          </div>
        </div>

        <div class="bg-muted/30 rounded-lg p-4 text-sm text-muted-foreground">
          <p class="font-medium mb-1">About Envs</p>
          <p>Envs represent different deployment environments for your secrets. Common envs include development, staging, and production. System envs (local, staging, production) cannot be removed.</p>
        </div>
      </div>

      <!-- Workspace Settings -->
      <div v-if="activeTab === 'workspace'" class="space-y-6">
        <div>
          <h1 class="text-2xl font-semibold mb-2">Workspace Configuration</h1>
          <p class="text-sm text-muted-foreground">Personalize your Keep experience by selecting which vaults and envs you work with</p>
        </div>

        <div class="border border-border rounded-lg p-6 space-y-6">
          <!-- Active Vaults Selection -->
          <div>
            <label class="block text-sm font-medium mb-3">Active Vaults</label>
            <p class="text-xs text-muted-foreground mb-3">Select which vaults to include in your workspace</p>
            <div class="space-y-2">
              <div v-for="vault in availableVaults" :key="vault" class="flex items-center space-x-2">
                <input
                  :id="`vault-${vault}`"
                  type="checkbox"
                  :value="vault"
                  v-model="workspaceForm.activeVaults"
                  class="rounded border-border"
                />
                <label :for="`vault-${vault}`" class="text-sm cursor-pointer">
                  {{ getVaultDisplayName(vault) }}
                </label>
              </div>
            </div>
            <div v-if="workspaceForm.activeVaults.length === 0" class="text-xs text-destructive mt-2">
              At least one vault must be selected
            </div>
          </div>

          <!-- Active Envs Selection -->
          <div>
            <label class="block text-sm font-medium mb-3">Active Envs</label>
            <p class="text-xs text-muted-foreground mb-3">Select which envs to include in your workspace</p>
            <div class="space-y-2">
              <div v-for="env in availableEnvs" :key="env" class="flex items-center space-x-2">
                <input
                  :id="`env-${env}`"
                  type="checkbox"
                  :value="env"
                  v-model="workspaceForm.activeEnvs"
                  class="rounded border-border"
                />
                <label :for="`env-${env}`" class="text-sm cursor-pointer">{{ env }}</label>
              </div>
            </div>
            <div v-if="workspaceForm.activeEnvs.length === 0" class="text-xs text-destructive mt-2">
              At least one env must be selected
            </div>
          </div>

          <div class="pt-4 flex justify-between items-center">
            <button
              @click="resetWorkspace"
              class="px-4 py-2 border border-border rounded-md hover:bg-muted transition-colors text-sm font-medium"
            >
              Reset to All
            </button>
            <div class="space-x-2">
              <button
                @click="verifyWorkspace"
                :disabled="verifyingWorkspace"
                class="px-4 py-2 border border-border rounded-md hover:bg-muted transition-colors text-sm font-medium disabled:opacity-50"
              >
                {{ verifyingWorkspace ? 'Verifying...' : 'Test Permissions' }}
              </button>
              <button
                @click="saveWorkspace"
                :disabled="workspaceForm.activeVaults.length === 0 || workspaceForm.activeEnvs.length === 0"
                class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50"
              >
                Save Workspace
              </button>
            </div>
          </div>
        </div>

        <!-- Workspace Info -->
        <div class="bg-muted/30 rounded-lg p-4 text-sm text-muted-foreground">
          <p class="font-medium mb-1">About Workspace Configuration</p>
          <p>Your workspace configuration is stored locally and not shared with your team. This allows each developer to customize their view to only show the vaults and envs they have access to and work with regularly.</p>
          <p class="mt-2">Changes to your workspace will immediately affect which secrets are visible throughout the Keep UI.</p>
        </div>

        <!-- Current Workspace Summary -->
        <div v-if="workspaceCreatedAt">
          <h2 class="text-lg font-medium mb-4">Current Workspace</h2>
          <div class="border border-border rounded-lg p-4 space-y-2 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Active Vaults</span>
              <span>{{ workspaceForm.activeVaults.length }} of {{ availableVaults.length }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Active Envs</span>
              <span>{{ workspaceForm.activeEnvs.length }} of {{ availableEnvs.length }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Created</span>
              <span>{{ formatDate(workspaceCreatedAt) }}</span>
            </div>
            <div v-if="workspaceUpdatedAt" class="flex items-center justify-between">
              <span class="text-muted-foreground">Last Updated</span>
              <span>{{ formatDate(workspaceUpdatedAt) }}</span>
            </div>
          </div>
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
          <label class="block text-sm font-medium mb-1">Driver</label>
          <select 
            v-model="vaultForm.driver"
            @change="onDriverChange"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            :disabled="!!editingVault"
          >
            <option value="">Select driver...</option>
            <option value="secretsmanager">AWS Secrets Manager</option>
            <option value="ssm">AWS SSM Parameter Store</option>
            <option value="test">Test Vault</option>
          </select>
          <p class="text-xs text-muted-foreground mt-1">{{ editingVault ? 'Driver cannot be changed after creation' : 'Select the backend vault type' }}</p>
        </div>

        <div v-if="vaultForm.driver || editingVault">
          <label class="block text-sm font-medium mb-1">Name</label>
          <input
            v-model="vaultForm.name"
            type="text"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            :placeholder="getDriverDefaults(vaultForm.driver).name"
          />
          <p class="text-xs text-muted-foreground mt-1">Friendly display name for this vault</p>
        </div>

        <div v-if="vaultForm.driver || editingVault">
          <label class="block text-sm font-medium mb-1">Slug</label>
          <input
            v-model="vaultForm.slug"
            type="text"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            :placeholder="getDriverDefaults(vaultForm.driver).slug"
            :disabled="!!editingVault"
          />
          <p class="text-xs text-muted-foreground mt-1">{{ editingVault ? 'Slug cannot be changed after creation' : 'Unique identifier for this vault' }}</p>
        </div>

        <div v-if="(vaultForm.driver === 'ssm' || vaultForm.driver === 'secretsmanager') || (editingVault && ['ssm', 'secretsmanager'].includes(editingVault.driver))">
          <label class="block text-sm font-medium mb-1">Scope (optional)</label>
          <input
            v-model="vaultForm.scope"
            type="text"
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            placeholder="app2"
          />
          <p class="text-xs text-muted-foreground mt-1">Optional scope to isolate secrets within your namespace.</p>
        </div>

        <div v-if="vaultForm.driver || editingVault" class="flex items-center space-x-2">
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

  <!-- Verification Results Modal -->
  <div v-if="showVerificationModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showVerificationModal = false">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Vault Verification Results</h2>
        <button
          @click="showVerificationModal = false"
          class="p-1 rounded-md hover:bg-muted transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div v-if="verificationResults" class="space-y-4">
        <div v-for="(envResults, vaultName) in verificationResults" :key="vaultName" 
             class="border border-border rounded-lg p-4">
          <h3 class="font-medium mb-3">{{ getVaultDisplayName(vaultName) }}</h3>
          
          <!-- Show results for each env -->
          <div v-for="(result, envName) in envResults" :key="envName" class="mb-3 last:mb-0">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm">{{ envName }}</span>
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                result.success ? 'bg-green-500/10 text-green-500' : 'bg-destructive/10 text-destructive'
              ]">
                {{ result.success ? 'Connected' : 'Failed' }}
              </span>
            </div>
            
            <div v-if="result.success && result.permissions" class="bg-gray-500/10 gap-2 grid grid-cols-5 px-3 py-1.5 rounded-md text-sm">
              <div v-for="(status, permission) in result.permissions" :key="permission"
                   class="flex items-center space-x-2">
                <svg v-if="status" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="w-4 h-4 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span :class="status ? 'text-muted-foreground' : 'text-destructive'">
                  {{ permission }}
                </span>
              </div>
            </div>
            
            <div v-else-if="result.error" class="text-sm text-destructive ml-4">
              {{ result.error }}
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-muted-foreground">
        <div class="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full mx-auto mb-4"></div>
        Verifying vaults...
      </div>

      <div class="flex justify-end mt-6">
        <button
          @click="showVerificationModal = false"
          class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
        >
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Env Confirmation Modal -->
  <DeleteConfirmationModal
    ref="deleteEnvModal"
    title="Delete Env"
    :message="`Are you sure you want to remove the env '${envToDelete}'?`"
    confirmText="Remove Env"
    @confirm="confirmDeleteEnv"
  />

  <!-- Delete Vault Confirmation Modal -->
  <DeleteConfirmationModal
    ref="deleteVaultModal"
    title="Delete Vault"
    :message="`Are you sure you want to delete the vault '${vaultToDelete?.name}'?`"
    confirmText="Delete Vault"
    @confirm="confirmDeleteVault"
  />
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from '../composables/useToast'
import DeleteConfirmationModal from './DeleteConfirmationModal.vue'

const toast = useToast()

// Navigation
const tabs = [
  { id: 'general', label: 'General' },
  { id: 'vaults', label: 'Vaults' },
  { id: 'envs', label: 'Envs' },
  { id: 'workspace', label: 'Workspace' }
]
const activeTab = ref('general')

// General settings
const settings = ref({
  appName: '',
  namespace: '',
  defaultVault: '',
  templatePath: ''
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
  scope: '',
  isDefault: false
})
const verifying = ref(false)
const showVerificationModal = ref(false)
const verificationResults = ref(null)
const vaultToDelete = ref(null)
const deleteVaultModal = ref(null)

// Envs
const envs = ref([])
const newEnv = ref('')
const envToDelete = ref('')
const deleteEnvModal = ref(null)
const serverUrl = computed(() => typeof window !== 'undefined' ? window.location.origin : '')

// Workspace
const workspaceForm = ref({
  activeVaults: [],
  activeEnvs: []
})
const availableVaults = ref([])
const availableEnvs = ref([])
const workspaceCreatedAt = ref(null)
const workspaceUpdatedAt = ref(null)
const verifyingWorkspace = ref(false)

// Helper functions
function getVaultDisplayName(slug) {
  const vault = vaults.value.find(v => v.slug === slug)
  return vault ? `${vault.name} (${vault.slug})` : slug
}

function getDriverDefaults(driver) {
  const defaults = {
    secretsmanager: { name: 'AWS Secrets Manager', slug: 'aws-secrets' },
    ssm: { name: 'AWS SSM Parameter Store', slug: 'aws-ssm' },
    test: { name: 'Test Vault', slug: 'test' }
  }
  return defaults[driver] || { name: '', slug: '' }
}

function onDriverChange() {
  if (!editingVault.value && vaultForm.value.driver) {
    const defaults = getDriverDefaults(vaultForm.value.driver)
    if (!vaultForm.value.name) {
      vaultForm.value.name = defaults.name
    }
    if (!vaultForm.value.slug) {
      vaultForm.value.slug = defaults.slug
    }
  }
}

// Lifecycle
onMounted(async () => {
  await loadSettings()
  await loadVaults()
  await loadEnvs()
  await loadWorkspace()
})

// General Settings Functions
async function loadSettings() {
  try {
    const data = await window.$api.getSettings()
    settings.value.appName = data.app_name || 'MyApp'
    settings.value.namespace = data.namespace || ''
    settings.value.defaultVault = data.default_vault || ''
    settings.value.templatePath = data.template_path || 'env'
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
      namespace: settings.value.namespace,
      default_vault: settings.value.defaultVault,
      template_path: settings.value.templatePath
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
    scope: vault.scope || '',
    isDefault: vault.isDefault || false
  }
  showVaultModal.value = true
}

function deleteVault(vault) {
  vaultToDelete.value = vault
  deleteVaultModal.value.open()
}

async function confirmDeleteVault() {
  if (!vaultToDelete.value) return
  
  try {
    await window.$api.deleteVault(vaultToDelete.value.slug)
    vaults.value = vaults.value.filter(v => v.slug !== vaultToDelete.value.slug)
    toast.success('Vault deleted', `Vault "${vaultToDelete.value.name}" has been deleted`)
  } catch (error) {
    toast.error('Failed to delete vault', error.message)
  } finally {
    vaultToDelete.value = null
  }
}

function openAddVaultModal() {
  editingVault.value = null
  vaultForm.value = {
    name: '',
    slug: '',
    driver: '',
    scope: '',
    isDefault: false
  }
  showVaultModal.value = true
}

function closeVaultModal() {
  showVaultModal.value = false
  editingVault.value = null
  vaultForm.value = {
    name: '',
    slug: '',
    driver: '',
    scope: '',
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
  showVerificationModal.value = true
  verificationResults.value = null
  
  try {
    const data = await window.$api.verifyVaults()
    const results = data.results || {}
    
    // Results now come with permissions from backend, organized by vault and env
    verificationResults.value = results
    
    // Check if any vault/env combination failed
    const failedVaults = []
    for (const [vaultName, envResults] of Object.entries(results)) {
      for (const [envName, result] of Object.entries(envResults)) {
        if (!result.success) {
          failedVaults.push(`${vaultName}:${envName}`)
        }
      }
    }
    if (failedVaults.length === 0) {
      toast.success('All vaults verified', 'All vaults are properly configured and accessible')
    } else {
      toast.error('Verification failed', `${failedVaults.length} vault/env combination(s) failed verification`)
    }
    
    // Reload vaults to get updated permissions
    await loadVaults()
  } catch (error) {
    toast.error('Failed to verify vaults', error.message)
    showVerificationModal.value = false
  } finally {
    verifying.value = false
  }
}

// Env Functions
async function loadEnvs() {
  try {
    const data = await window.$api.listEnvs()
    envs.value = data.envs || []
  } catch (error) {
    console.error('Failed to load envs:', error)
    toast.error('Failed to load envs', error.message)
  }
}

async function addEnv() {
  if (!newEnv.value) return
  
  if (envs.value.includes(newEnv.value)) {
    toast.error('Env exists', `Env "${newEnv.value}" already exists`)
    return
  }
  
  try {
    await window.$api.addEnv(newEnv.value)
    envs.value.push(newEnv.value)
    toast.success('Env added', `Env "${newEnv.value}" has been added`)
    newEnv.value = ''
    
    // Reload vaults to get updated permissions for the new env
    await loadVaults()
  } catch (error) {
    toast.error('Failed to add env', error.message)
  }
}

function removeEnv(env) {
  envToDelete.value = env
  deleteEnvModal.value.open()
}

async function confirmDeleteEnv() {
  if (!envToDelete.value) return
  
  try {
    await window.$api.removeEnv(envToDelete.value)
    const index = envs.value.indexOf(envToDelete.value)
    if (index > -1) {
      envs.value.splice(index, 1)
      toast.success('Env removed', `Env "${envToDelete.value}" has been removed`)
    }
  } catch (error) {
    toast.error('Failed to remove env', error.message)
  } finally {
    envToDelete.value = ''
  }
}

// Workspace Functions
async function loadWorkspace() {
  try {
    const data = await window.$api.getWorkspace()
    workspaceForm.value.activeVaults = data.active_vaults || []
    workspaceForm.value.activeEnvs = data.active_envs || []
    availableVaults.value = data.available_vaults || []
    availableEnvs.value = data.available_envs || []
    workspaceCreatedAt.value = data.created_at
    workspaceUpdatedAt.value = data.updated_at
  } catch (error) {
    console.error('Failed to load workspace:', error)
    toast.error('Failed to load workspace', error.message)
  }
}

async function saveWorkspace() {
  if (workspaceForm.value.activeVaults.length === 0) {
    toast.error('Invalid workspace', 'At least one vault must be selected')
    return
  }
  
  if (workspaceForm.value.activeEnvs.length === 0) {
    toast.error('Invalid workspace', 'At least one env must be selected')
    return
  }
  
  try {
    const result = await window.$api.updateWorkspace(
      workspaceForm.value.activeVaults,
      workspaceForm.value.activeEnvs
    )
    
    if (result.success) {
      workspaceUpdatedAt.value = result.workspace.updated_at
      toast.success('Workspace saved', 'Your workspace configuration has been updated')
      
      // Reload vaults and envs to reflect the filtered workspace
      await loadVaults()
      await loadEnvs()
    }
  } catch (error) {
    toast.error('Failed to save workspace', error.message)
  }
}

function resetWorkspace() {
  workspaceForm.value.activeVaults = [...availableVaults.value]
  workspaceForm.value.activeEnvs = [...availableEnvs.value]
}

async function verifyWorkspace() {
  verifyingWorkspace.value = true
  showVerificationModal.value = true
  verificationResults.value = null
  
  try {
    const data = await window.$api.verifyWorkspace()
    verificationResults.value = data.results || {}
    
    // Check if any vault/env combination failed
    const failedCombos = []
    for (const [vaultName, envResults] of Object.entries(data.results)) {
      for (const [envName, result] of Object.entries(envResults)) {
        if (!result.success) {
          failedCombos.push(`${vaultName}:${envName}`)
        }
      }
    }
    
    if (failedCombos.length === 0) {
      toast.success('Workspace verified', 'All selected vault/env combinations are accessible')
    } else {
      toast.warning('Verification complete', `${failedCombos.length} vault/env combination(s) are not accessible`)
    }
  } catch (error) {
    toast.error('Failed to verify workspace', error.message)
    showVerificationModal.value = false
  } finally {
    verifyingWorkspace.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return 'Never'
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
}
</script>