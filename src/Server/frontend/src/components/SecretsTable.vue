<template>
  <div>
    <!-- Header with vault/stage selectors on left, search and add button on right -->
    <div class="flex items-center justify-between mb-6">
      <!-- Left side: Vault & Stage Selectors -->
      <div class="flex items-center">
        <VaultStageSelector 
          v-model:vault="vault"
          v-model:stage="stage"
          :vaults="vaults"
          :stages="stages"
        />
      </div>
      
      <!-- Right side: Search and Add button -->
      <div class="flex items-center space-x-3">
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
          @click="showAddDialog = true"
          class="flex items-center space-x-2 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Add Secret</span>
        </button>
        
        <ImportWizard
          :vault="vault"
          :stage="stage"
          @imported="loadSecrets"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="border border-border rounded-lg">
      <table class="w-full" :class="loading && 'opacity-50 pointer-events-none'">
        <thead class="bg-muted">
          <tr>
            <th class="w-3/12 text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider rounded-tl-lg">Key</th>
            <th class="w-6/12 text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
              <div class="flex items-center space-x-2">
                <span>Value</span>
                <button
                  @click="unmaskAll = !unmaskAll"
                  class="p-1 rounded hover:bg-accent transition-colors"
                  :title="unmaskAll ? 'Hide all values' : 'Show all values'"
                >
                  <svg v-if="!unmaskAll" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                </button>
              </div>
            </th>
            <th class="w-2/12 text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Modified</th>
            <th class="relative px-6 py-3 rounded-tr-lg"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <tr v-for="secret in secrets" :key="secret.key" class="transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              {{ secret.key }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <SecretValue 
                :value="secret.value" 
                :masked="!unmaskAll && !unmaskedKeys.has(secret.key)" 
                @toggle="toggleMask(secret.key)" 
              />
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground">
              {{ formatDate(secret.modified) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
              <SecretActionsMenu
                :secretKey="secret.key"
                :secretValue="secret.value"
                :vault="vault"
                :stage="stage"
                @edit="editSecret"
                @rename="showRenameDialog"
                @copyValue="handleCopyValue"
                @copyTo="showCopyToStageDialog"
                @history="showHistoryDialog"
                @delete="showDeleteDialog"
                @refresh="loadSecrets"
              />
            </td>
          </tr>
        </tbody>
      </table>

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
    
    <!-- Rename Dialog -->
    <RenameDialog
      v-if="renamingSecret"
      :currentKey="renamingSecret.key"
      @rename="handleRename"
      @close="renamingSecret = null"
    />
    
    <!-- Copy to Stage Dialog -->
    <CopyToStageDialog
      v-if="copyingSecret"
      :secretKey="copyingSecret.key"
      :currentVault="vault"
      :currentStage="stage"
      :vaults="vaults"
      :stages="stages"
      @copy="handleCopyToStage"
      @close="copyingSecret = null"
    />
    
    <!-- History Dialog -->
    <HistoryDialog
      v-if="historySecret"
      :secretKey="historySecret.key"
      :vault="vault"
      :stage="stage"
      @refresh="loadSecrets"
      @close="historySecret = null"
    />
    
    <!-- Delete Confirm Dialog -->
    <DeleteConfirmDialog
      v-if="deletingSecret"
      :secretKey="deletingSecret.key"
      :vault="vault"
      :stage="stage"
      @confirm="confirmDelete"
      @close="deletingSecret = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import VaultStageSelector from './VaultStageSelector.vue'
import SecretValue from './SecretValue.vue'
import SecretDialog from './SecretDialog.vue'
import RenameDialog from './RenameDialog.vue'
import CopyToStageDialog from './CopyToStageDialog.vue'
import HistoryDialog from './HistoryDialog.vue'
import DeleteConfirmDialog from './DeleteConfirmDialog.vue'
import SecretActionsMenu from './SecretActionsMenu.vue'
import ImportWizard from './ImportWizard.vue'
import { useToast } from '../composables/useToast'

const emit = defineEmits(['refresh'])
const toast = useToast()

const vault = ref(localStorage.getItem('keep.secrets.vault') || '')
const stage = ref(localStorage.getItem('keep.secrets.stage') || '')
const vaults = ref([])
const stages = ref([])
const secrets = ref([])
const loading = ref(false)
const searchQuery = ref('')
const unmaskAll = ref(false)
const unmaskedKeys = ref(new Set())
const showAddDialog = ref(false)
const editingSecret = ref(null)
const renamingSecret = ref(null)
const copyingSecret = ref(null)
const historySecret = ref(null)
const deletingSecret = ref(null)

let searchTimeout = null

onMounted(async () => {
  await loadVaultsAndStages()
  await loadSecrets()
})

watch(() => [vault.value, stage.value], () => {
  // Save to localStorage
  if (vault.value) localStorage.setItem('keep.secrets.vault', vault.value)
  if (stage.value) localStorage.setItem('keep.secrets.stage', stage.value)
  
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
    
    // Set defaults only if no saved value
    if (!vault.value) {
      // Handle both new object format and old string format
      const defaultVault = settings.default_vault
      if (defaultVault) {
        vault.value = defaultVault
        localStorage.setItem('keep.secrets.vault', vault.value)
      } else if (vaults.value.length > 0) {
        vault.value = vaults.value[0].name || vaults.value[0]
        localStorage.setItem('keep.secrets.vault', vault.value)
      }
    }
    if (!stage.value && stages.value.length) {
      stage.value = stages.value[0]
      localStorage.setItem('keep.secrets.stage', stage.value)
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
      ? await window.$api.searchSecrets(searchQuery.value, vault.value, stage.value, true)  // Always get unmasked
      : await window.$api.listSecrets(vault.value, stage.value, true)  // Always get unmasked
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

function editSecret(secret) {
  editingSecret.value = secret
}

async function saveSecret(data) {
  try {
    if (editingSecret.value) {
      await window.$api.updateSecret(data.key, data.value, vault.value, stage.value)
      toast.success('Secret updated', `Secret '${data.key}' has been updated successfully`)
    } else {
      await window.$api.createSecret(data.key, data.value, vault.value, stage.value)
      toast.success('Secret created', `Secret '${data.key}' has been created successfully`)
    }
    await loadSecrets()
    closeDialog()
  } catch (error) {
    console.error('Failed to save secret:', error)
    toast.error('Failed to save secret', error.message)
  }
}

function showRenameDialog(secret) {
  renamingSecret.value = secret
}

function showCopyToStageDialog(secret) {
  copyingSecret.value = secret
}

function showHistoryDialog(secret) {
  historySecret.value = secret
}

function showDeleteDialog(secret) {
  deletingSecret.value = secret
}

function handleCopyValue(secret) {
  toast.success('Copied to clipboard', 'Secret value has been copied to your clipboard')
}

async function confirmDelete() {
  if (!deletingSecret.value) return
  
  try {
    await window.$api.deleteSecret(deletingSecret.value.key, vault.value, stage.value)
    toast.success('Secret deleted', `Secret '${deletingSecret.value.key}' has been deleted successfully`)
    await loadSecrets()
    deletingSecret.value = null
  } catch (error) {
    console.error('Failed to delete secret:', error)
    toast.error('Failed to delete secret', error.message)
  }
}

async function handleRename(newKey) {
  try {
    await window.$api.renameSecret(renamingSecret.value.key, newKey, vault.value, stage.value)
    toast.success('Secret renamed', `Secret renamed from '${renamingSecret.value.key}' to '${newKey}'`)
    await loadSecrets()
    renamingSecret.value = null
  } catch (error) {
    console.error('Failed to rename secret:', error)
    toast.error('Failed to rename secret', error.message)
  }
}

async function handleCopyToStage({ targetVault, targetStage }) {
  try {
    await window.$api.copySecretToStage(
      copyingSecret.value.key, 
      targetStage, 
      targetVault, 
      stage.value,
      vault.value
    )
    toast.success('Secret copied', `Secret '${copyingSecret.value.key}' copied to ${targetVault}:${targetStage}`)
    copyingSecret.value = null
  } catch (error) {
    console.error('Failed to copy secret:', error)
    toast.error('Failed to copy secret', error.message)
  }
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
</script>