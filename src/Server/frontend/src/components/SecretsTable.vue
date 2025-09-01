<template>
  <div>
    <!-- Vault & Stage Selector -->
    <div class="mb-4">
      <VaultStageSelector 
        v-model:vault="vault"
        v-model:stage="stage"
        :vaults="vaults"
        :stages="stages"
      />
    </div>
    
    <!-- Header with search and add button -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center space-x-4">
        <div class="relative">
          <input
            v-model="searchQuery"
            @input="debounceSearch"
            type="text"
            placeholder="Search secrets..."
            class="w-64 px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          />
          <svg class="absolute right-3 top-2.5 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        
        <button
          @click="showExportDialog = true"
          class="flex items-center space-x-2 px-3 py-2 border border-border rounded-md hover:bg-muted transition-colors text-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span>Export</span>
        </button>
      </div>
      
      <button
        @click="showAddDialog = true"
        class="flex items-center space-x-2 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Add Secret</span>
      </button>
    </div>

    <!-- Table -->
    <div class="border border-border rounded-lg overflow-hidden">
      <table class="w-full">
        <thead class="bg-muted">
          <tr>
            <th class="text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Key</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Value</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Modified</th>
            <th class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="bg-card divide-y divide-border">
          <tr v-for="secret in secrets" :key="secret.key" class="hover:bg-muted/50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              {{ secret.key }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <SecretValue :value="secret.value" :masked="!unmaskedKeys.has(secret.key)" @toggle="toggleMask(secret.key)" />
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground">
              {{ formatDate(secret.modified) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
              <div class="relative inline-block text-left">
                <button
                  @click="toggleMenu(secret.key)"
                  class="p-1 rounded-md hover:bg-muted transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                  </svg>
                </button>
                
                <div
                  v-if="openMenu === secret.key"
                  class="absolute right-0 mt-2 w-48 bg-popover border border-border rounded-md shadow-lg z-10"
                >
                  <div class="py-1">
                    <button
                      @click="editSecret(secret)"
                      class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
                    >
                      Edit
                    </button>
                    <button
                      @click="copyToClipboard(secret.value)"
                      class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
                    >
                      Copy Value
                    </button>
                    <button
                      @click="deleteSecret(secret.key)"
                      class="w-full text-left px-4 py-2 text-sm text-destructive hover:bg-accent transition-colors"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <div v-if="loading" class="p-8 text-center text-muted-foreground">
        Loading secrets...
      </div>
      
      <div v-if="!loading && secrets.length === 0" class="p-8 text-center text-muted-foreground">
        No secrets found
      </div>
    </div>

    <!-- Add/Edit Dialog -->
    <SecretDialog
      v-if="showAddDialog || editingSecret"
      :secret="editingSecret"
      :vault="vault"
      :stage="stage"
      @save="saveSecret"
      @close="closeDialog"
    />
    
    <!-- Export Dialog -->
    <ExportDialog
      v-if="showExportDialog"
      :vault="vault"
      :stage="stage"
      @close="showExportDialog = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import VaultStageSelector from './VaultStageSelector.vue'
import SecretValue from './SecretValue.vue'
import SecretDialog from './SecretDialog.vue'
import ExportDialog from './ExportDialog.vue'

const emit = defineEmits(['refresh'])

const vault = ref('')
const stage = ref('')
const vaults = ref([])
const stages = ref([])
const secrets = ref([])
const loading = ref(false)
const searchQuery = ref('')
const unmaskedKeys = ref(new Set())
const openMenu = ref(null)
const showAddDialog = ref(false)
const showExportDialog = ref(false)
const editingSecret = ref(null)

let searchTimeout = null

onMounted(async () => {
  await loadVaultsAndStages()
  await loadSecrets()
})

watch(() => [vault.value, stage.value], () => {
  loadSecrets()
})

async function loadVaultsAndStages() {
  try {
    const [vaultsData, stagesData, settings] = await Promise.all([
      window.$api.listVaults(),
      window.$api.listStages(),
      window.$api.getSettings()
    ])
    vaults.value = vaultsData.vaults || []
    stages.value = stagesData.stages || []
    
    // Set defaults
    if (!vault.value) {
      vault.value = settings.default_vault || vaults.value[0] || ''
    }
    if (!stage.value && stages.value.length) {
      stage.value = stages.value[0]
    }
  } catch (error) {
    console.error('Failed to load vaults and stages:', error)
  }
}

async function loadSecrets() {
  if (!vault.value || !stage.value) return
  
  loading.value = true
  try {
    const data = searchQuery.value
      ? await window.$api.searchSecrets(searchQuery.value, vault.value, stage.value)
      : await window.$api.listSecrets(vault.value, stage.value)
    secrets.value = data.secrets || []
  } catch (error) {
    console.error('Failed to load secrets:', error)
    secrets.value = []
  } finally {
    loading.value = false
  }
}

function debounceSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadSecrets()
  }, 300)
}

function toggleMask(key) {
  if (unmaskedKeys.value.has(key)) {
    unmaskedKeys.value.delete(key)
  } else {
    unmaskedKeys.value.add(key)
  }
}

function toggleMenu(key) {
  openMenu.value = openMenu.value === key ? null : key
}

function editSecret(secret) {
  editingSecret.value = secret
  openMenu.value = null
}

async function saveSecret(data) {
  try {
    if (editingSecret.value) {
      await window.$api.updateSecret(data.key, data.value, vault.value, stage.value)
    } else {
      await window.$api.createSecret(data.key, data.value, vault.value, stage.value)
    }
    await loadSecrets()
    closeDialog()
  } catch (error) {
    console.error('Failed to save secret:', error)
    alert('Failed to save secret: ' + error.message)
  }
}

async function deleteSecret(key) {
  if (!confirm(`Delete secret "${key}"?`)) return
  
  try {
    await window.$api.deleteSecret(key, vault.value, stage.value)
    await loadSecrets()
    openMenu.value = null
  } catch (error) {
    console.error('Failed to delete secret:', error)
    alert('Failed to delete secret: ' + error.message)
  }
}

function copyToClipboard(value) {
  navigator.clipboard.writeText(value)
  openMenu.value = null
}

function closeDialog() {
  showAddDialog.value = false
  editingSecret.value = null
}

function formatDate(dateString) {
  if (!dateString) return 'Never'
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
}

// Close menu when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.relative')) {
    openMenu.value = null
  }
})
</script>