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
      <div class="flex space-x-3">
        <button
            @click="unmaskAll = !unmaskAll"
            :class="[
              'p-2 text-sm border border-border rounded-md transition-all',
              unmaskAll ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent'
            ]"
            :title="unmaskAll ? 'Hide all values' : 'Show all values'"
        >
          <svg v-if="!unmaskAll" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
          </svg>
        </button>

        <div class="relative">
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            placeholder="Search by secret key or value..."
            class="w-64 px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          />
          <svg class="absolute right-3 top-2.5 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        
        <button
          v-if="permissions.write"
          @click="showAddDialog = true"
          class="flex items-center space-x-2 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Add Secret</span>
        </button>
        
        <TableActionsMenu
          :vault="vault"
          :stage="stage"
          :secrets="secrets"
          :permissions="permissions"
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
            <th class="w-6/12 text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">Value</th>
            <th class="w-2/12 text-left px-6 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider" title="Times shown in your local timezone">Modified</th>
            <th class="relative px-6 py-3 rounded-tr-lg"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <tr v-for="secret in secrets" :key="secret.key" class="group hover:bg-muted/30 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              {{ secret.key }}
            </td>
            <td class="px-6 py-4 text-sm">
              <SecretValue
                :secret="secret"
                :value="secret.value" 
                :masked="!unmaskAll && !unmaskedKeys.has(secret.key)" 
                @toggle="toggleMask(secret.key)" 
              />
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground">
              {{ formatDate(secret.modified) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
              <div class="flex items-center justify-end opacity-30 group-hover:opacity-100 transition-opacity">
                <Tooltip v-if="permissions.write" content="Edit" :delay-duration="200">
                  <button
                    @click="editSecret({ key: secret.key, value: secret.value, vault, stage })"
                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                  </button>
                </Tooltip>
                <Tooltip v-if="permissions.write" content="Rename" :delay-duration="200">
                  <button
                    @click="showRenameDialog({ key: secret.key, value: secret.value, vault, stage })"
                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                  </button>
                </Tooltip>
<!--                <Tooltip content="Copy Value" :delay-duration="200">-->
<!--                  <button-->
<!--                    @click="handleCopyValue({ key: secret.key, value: secret.value, vault, stage })"-->
<!--                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"-->
<!--                  >-->
<!--                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">-->
<!--                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>-->
<!--                    </svg>-->
<!--                  </button>-->
<!--                </Tooltip>-->
                <Tooltip v-if="permissions.write" content="Copy to Stage" :delay-duration="200">
                  <button
                    @click="showCopyToStageDialog({ key: secret.key, value: secret.value, vault, stage })"
                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                    </svg>
                  </button>
                </Tooltip>
                <Tooltip v-if="permissions.history" content="History" :delay-duration="200">
                  <button
                    @click="showHistoryDialog({ key: secret.key, vault, stage })"
                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </button>
                </Tooltip>
                <Tooltip v-if="permissions.delete" content="Delete" :delay-duration="200">
                  <button
                    @click="showDeleteDialog({ key: secret.key, vault, stage })"
                    class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded transition-colors"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                  </button>
                </Tooltip>
              </div>
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
      :vault="editingSecret ? editingSecret.vault : vault"
      :stage="editingSecret ? editingSecret.stage : stage"
      @success="handleSecretSaveSuccess"
      @close="closeDialog"
    />
    
    <!-- Rename Dialog -->
    <RenameDialog
      v-if="renamingSecret"
      :currentKey="renamingSecret.key"
      :vault="vault"
      :stage="stage"
      @success="handleRenameSuccess"
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
      @success="handleDeleteSuccess"
      @close="deletingSecret = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import VaultStageSelector from './VaultStageSelector.vue'
import SecretValue from './SecretValue.vue'
import SecretDialog from './SecretDialog.vue'
import RenameDialog from './RenameDialog.vue'
import CopyToStageDialog from './CopyToStageDialog.vue'
import HistoryDialog from './HistoryDialog.vue'
import DeleteConfirmDialog from './DeleteConfirmDialog.vue'
import TableActionsMenu from './TableActionsMenu.vue'
import Tooltip from './ui/Tooltip.vue'
import { useToast } from '../composables/useToast'
import { useVault } from '../composables/useVault'
import { useSecrets } from '../composables/useSecrets'
import { useKeyboardShortcuts } from '../composables/useKeyboardShortcuts'
import { formatDate } from '../utils/formatters'

const emit = defineEmits(['refresh'])
const toast = useToast()
const { vaults, stages, settings, loadAll: loadVaultData } = useVault()
const { secrets: allSecrets, loading, loadSecrets: fetchSecrets, copySecretToStage } = useSecrets()
const { registerSearchHandler, registerModalCloseHandler, registerMaskToggleHandler } = useKeyboardShortcuts()

const vault = ref(localStorage.getItem('keep.secrets.vault') || '')
const stage = ref(localStorage.getItem('keep.secrets.stage') || '')
const searchQuery = ref('')
const searchInput = ref(null)
const unmaskAll = ref(false)
const unmaskedKeys = ref(new Set())
const showAddDialog = ref(false)
const editingSecret = ref(null)
const renamingSecret = ref(null)
const copyingSecret = ref(null)
const historySecret = ref(null)
const deletingSecret = ref(null)

// Filter secrets client-side based on search query
const secrets = computed(() => {
  if (!searchQuery.value) {
    return allSecrets.value
  }
  
  const query = searchQuery.value.toLowerCase()
  return allSecrets.value.filter(secret => {
    // Search in both key name and value
    const keyMatches = secret.key.toLowerCase().includes(query)
    const valueMatches = secret.value && secret.value.toLowerCase().includes(query)
    return keyMatches || valueMatches
  })
})

// Get permissions for current vault and stage
const permissions = computed(() => {
  const currentVault = vaults.value.find(v => v.slug === vault.value)
  if (!currentVault || !currentVault.permissions || !stage.value) {
    // If no permissions are cached, assume full permissions for backward compatibility
    return { list: true, read: true, write: true, delete: true, history: true }
  }
  
  const stagePerms = currentVault.permissions[stage.value] || []
  return {
    list: stagePerms.includes('list'),
    read: stagePerms.includes('read'),
    write: stagePerms.includes('write'),
    delete: stagePerms.includes('delete'),
    history: stagePerms.includes('history')
  }
})

// Keyboard shortcut cleanup functions
let cleanupModalHandler = null

onMounted(async () => {
  await loadVaultsAndStages()
  await loadSecrets()
  
  // Register keyboard shortcut handlers
  registerSearchHandler(() => {
    if (searchInput.value) {
      searchInput.value.focus()
    }
  })
  
  // Register modal close handlers for all dialogs
  cleanupModalHandler = registerModalCloseHandler(() => {
    showAddDialog.value = false
    editingSecret.value = null
    renamingSecret.value = null
    copyingSecret.value = null
    historySecret.value = null
    deletingSecret.value = null
  })
  
  // Register mask toggle handler
  registerMaskToggleHandler(() => {
    unmaskAll.value = !unmaskAll.value
  })
})

onUnmounted(() => {
  // Clean up modal handler
  if (cleanupModalHandler) {
    cleanupModalHandler()
  }
})

watch(() => [vault.value, stage.value], () => {
  // Save to localStorage
  if (vault.value) localStorage.setItem('keep.secrets.vault', vault.value)
  if (stage.value) localStorage.setItem('keep.secrets.stage', stage.value)
  
  loadSecrets()
})

async function loadVaultsAndStages() {
  try {
    await loadVaultData()
    
    // Set defaults only if no saved value
    if (!vault.value) {
      const defaultVault = settings.value.default_vault
      if (defaultVault) {
        vault.value = defaultVault
        localStorage.setItem('keep.secrets.vault', vault.value)
      } else if (vaults.value.length > 0) {
        vault.value = vaults.value[0].slug || vaults.value[0]
        localStorage.setItem('keep.secrets.vault', vault.value)
      }
    }
    if (!stage.value && stages.value.length) {
      stage.value = stages.value[0]
      localStorage.setItem('keep.secrets.stage', stage.value)
    }
  } catch (error) {
    // Failed to load vaults and stages
  }
}

async function loadSecrets() {
  if (!vault.value || !stage.value) return
  
  try {
    // Always load all secrets - filtering is done client-side
    await fetchSecrets(vault.value, stage.value, true)
  } catch (error) {
    // Failed to load secrets
  }
}

function toggleMask(key) {
  if (unmaskedKeys.value.has(key)) {
    unmaskedKeys.value.delete(key)
  } else {
    unmaskedKeys.value.add(key)
  }
}

function editSecret(data) {
  editingSecret.value = data
}

async function handleSecretSaveSuccess() {
  closeDialog()
  await loadSecrets()
}

function showRenameDialog(data) {
  renamingSecret.value = data
}

function showCopyToStageDialog(data) {
  copyingSecret.value = data
}

function showHistoryDialog(data) {
  historySecret.value = data
}

function showDeleteDialog(data) {
  deletingSecret.value = data
}

function handleCopyValue(data) {
  if (data.value) {
    navigator.clipboard.writeText(data.value)
    toast.success('Copied to clipboard', 'Secret value has been copied to your clipboard')
  }
}

async function handleDeleteSuccess() {
  deletingSecret.value = null
  await loadSecrets()
}

async function handleRenameSuccess() {
  renamingSecret.value = null
  await loadSecrets()
}

async function handleCopyToStage({ targetVault, targetStage }) {
  try {
    await copySecretToStage(
      copyingSecret.value.key, 
      targetStage, 
      targetVault, 
      copyingSecret.value.stage,
      copyingSecret.value.vault
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
</script>