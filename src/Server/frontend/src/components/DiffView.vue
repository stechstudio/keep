<template>
  <div>
    <!-- Controls -->
    <div class="mb-6 flex justify-between">
      <div class="flex items-center space-x-4">
        <!-- Combined Vault/Stage Dropdown -->
        <div class="relative">
          <button
              @click="showCombinationsDropdown = !showCombinationsDropdown"
              class="flex items-center space-x-2 px-3 py-2 bg-input border border-border rounded-md text-sm hover:bg-accent transition-colors"
          >
            <span v-if="availableVaults.length > 1">Toggle Vaults / Stages ({{ selectedCombinations.length }}/{{ availableCombinations.length }})</span>
            <span v-else>Toggle Stages ({{ selectedCombinations.length }}/{{ availableCombinations.length }})</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <div v-if="showCombinationsDropdown" class="absolute top-full left-0 mt-1 bg-popover border border-border rounded-md shadow-lg z-20">
            <div class="p-2 space-y-1 max-h-96 overflow-y-auto" style="min-width: max-content;">
              <label
                  v-for="combo in availableCombinations"
                  :key="combo.key"
                  class="flex items-center px-2 py-1.5 hover:bg-accent rounded cursor-pointer whitespace-nowrap"
              >
                <input
                    type="checkbox"
                    :value="combo.key"
                    v-model="selectedCombinations"
                    class="mr-3 rounded border-border bg-input text-primary focus:ring-2 focus:ring-ring"
                />
                <span class="text-sm">
                  <span v-if="availableVaults.length > 1">{{ combo.vaultDisplay }} ({{ combo.vault }})</span>
                  <span v-if="availableVaults.length > 1" class="mx-2 text-muted-foreground">/</span>
                  <strong>{{ combo.stage }}</strong>
                </span>
              </label>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex items-center space-x-2 text-sm">
          <button
              @click="selectAll"
              class="text-muted-foreground hover:text-foreground transition-colors"
          >
            Select All
          </button>
          <span class="text-muted-foreground">|</span>
          <button
              @click="selectNone"
              class="text-muted-foreground hover:text-foreground transition-colors"
          >
            Clear All
          </button>
        </div>
      </div>

      <!-- Right side controls -->
      <div class="flex space-x-2">
        <button
            @click="toggleRowColors"
            :class="[
              'p-2 text-sm border border-border rounded-md transition-all',
              showRowColors ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent'
            ]"
            title="Toggle row highlighting">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
          </svg>
        </button>
        <button
            @click="toggleUnmask"
            :class="[
              'p-2 text-sm border border-border rounded-md transition-all',
              unmaskAll ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'hover:bg-accent'
            ]"
            :title="unmaskAll ? 'Hide all values' : 'Show all values'"
        >
          <svg v-if="unmaskAll" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
          </svg>
        </button>

        <!-- Search field -->
        <div class="relative">
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            placeholder="Search by key or value..."
            class="w-64 px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          />
          <svg class="absolute right-3 top-2.5 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-flex items-center space-x-2 text-muted-foreground">
        <div class="animate-spin rounded-full h-5 w-5 border-2 border-primary border-t-transparent"></div>
        <span>Loading comparison matrix...</span>
      </div>
    </div>

    <!-- Diff Matrix -->
    <div v-else-if="diffMatrix && Object.keys(diffMatrix).length > 0" class="border border-border rounded-lg overflow-y-visible overflow-x-auto">
      <table class="w-full" style="table-layout: fixed;">
        <thead class="bg-muted sticky top-0 z-10">
        <tr>
          <th class="text-left px-4 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider sticky left-0 bg-muted z-20 w-64">
            Secret Key
          </th>
          <th
              v-for="column in activeColumns"
              :key="column"
              class="px-4 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider text-center border-l border-border"
              :style="columnWidthStyle"
          >
            <div class="flex flex-col">
              <span class="font-semibold">{{ column.vault }}</span>
              <span class="text-[10px] opacity-75">{{ column.stage }}</span>
            </div>
          </th>
        </tr>
        </thead>
        <tbody class="divide-y divide-border">
        <tr v-for="key in sortedKeys" :key="key" :class="getRowClass(key)">
          <td class="sticky left-0 z-10 border-r border-border p-0">
            <div class="bg-background">
              <div class="px-4 py-3 flex items-center space-x-2" :class="getRowClass(key)">
                <span class="font-mono text-sm font-medium">{{ key }}</span>
                <button
                    @click="toggleRowMask(key)"
                    class="p-1 rounded hover:bg-muted transition-colors"
                    :title="unmaskedRows.has(key) ? 'Hide values' : 'Show values'"
                >
                  <svg v-if="!unmaskedRows.has(key)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                  </svg>
                </button>
              </div>
            </div>
          </td>
          <td
              v-for="column in activeColumns"
              :key="`${key}-${column.vault}-${column.stage}`"
              class="px-4 py-3 text-left border-l border-border relative group"
              :class="getCellClass(key, column.vault, column.stage)"
              :style="columnWidthStyle"
          >
            <div v-if="getSecretValue(key, column.vault, column.stage)" class="flex items-center justify-between">
                <span class="font-mono text-sm break-all">
                  {{ getMaskedValue(key, column.vault, column.stage) }}
                </span>
              <SecretActionsMenu
                :secretKey="key"
                :secretValue="getSecretValue(key, column.vault, column.stage)"
                :vault="column.vault"
                :stage="column.stage"
                :showRename="false"
                :buttonClass="'opacity-0 group-hover:opacity-100'"
                @edit="editSecret"
                @copyValue="handleCopyValue"
                @copyTo="showCopyToStageDialog"
                @history="showHistoryDialog"
                @delete="showDeleteDialog"
                @refresh="loadDiff"
              />
            </div>
            <div v-else class="flex items-center justify-between">
              <span class="text-muted-foreground text-sm">—</span>
              <SecretActionsMenu
                :secretKey="key"
                :secretValue="null"
                :vault="column.vault"
                :stage="column.stage"
                :showEdit="false"
                :showRename="false"
                :showCreate="true"
                :showCopyValue="false"
                :showCopyTo="false"
                :showDelete="false"
                :showHistory="false"
                :buttonClass="'opacity-0 group-hover:opacity-100'"
                @create="createSecret"
              />
            </div>
          </td>
        </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading && diffMatrix !== null && Object.keys(diffMatrix).length === 0" class="text-center py-12 text-muted-foreground">
      <svg class="mx-auto h-12 w-12 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <p>No secrets found in the selected vaults and stages</p>
    </div>

    <!-- No Selection State -->
    <div v-else-if="!loading && selectedCombinations.length === 0" class="text-center py-12 text-muted-foreground">
      <svg class="mx-auto h-12 w-12 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      <p class="text-lg font-medium mb-2">Select Vault/Stage Combinations</p>
      <p class="text-sm">Use the dropdown above to select at least one vault/stage combination</p>
    </div>

    <!-- Edit Secret Dialog -->
    <SecretDialog
      v-if="editingSecret"
      :secret="editingSecret.isNew ? null : editingSecret"
      :vault="editingSecret.vault"
      :stage="editingSecret.stage"
      :initialKey="editingSecret.isNew ? editingSecret.key : undefined"
      @success="handleSecretSaveSuccess"
      @close="editingSecret = null"
    />

    <!-- Copy To Stage Modal -->
    <div v-if="copyingSecret" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="copyingSecret = null">
      <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Copy Secret</h2>
          <button
              @click="copyingSecret = null"
              class="p-1 rounded-md hover:bg-muted transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Key</label>
            <input
                type="text"
                :value="copyingSecret?.key"
                disabled
                class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm opacity-50"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">From</label>
            <input
                type="text"
                :value="`${copyingSecret?.vault} / ${copyingSecret?.stage}`"
                disabled
                class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm opacity-50"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Copy To</label>
            <select v-model="copyTarget" class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm">
              <option value="">Select destination...</option>
              <option
                  v-for="combo in availableCombinations"
                  :key="combo.key"
                  :value="combo.key"
                  :disabled="combo.key === `${copyingSecret?.vault}:${copyingSecret?.stage}`"
              >
                {{ combo.vaultDisplay }} / {{ combo.stage }}
              </option>
            </select>
          </div>
        </div>
        <div class="flex justify-end space-x-2 mt-6">
          <button
              @click="copyingSecret = null; copyTarget = ''"
              class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
          >
            Cancel
          </button>
          <button
              @click="executeCopy"
              :disabled="!copyTarget"
              class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Copy Secret
          </button>
        </div>
      </div>
    </div>

    <!-- Rename Dialog -->
    <RenameDialog
      v-if="renamingSecret"
      :currentKey="renamingSecret.key"
      :vault="renamingSecret.vault"
      :stage="renamingSecret.stage"
      @success="handleRenameSuccess"
      @close="renamingSecret = null"
    />
    
    <!-- Copy to Stage Dialog -->
    <CopyToStageDialog
      v-if="copyingSecretStage"
      :secretKey="copyingSecretStage.key"
      :currentVault="copyingSecretStage.vault"
      :currentStage="copyingSecretStage.stage"
      :vaults="availableVaults"
      :stages="availableStages"
      @copy="handleCopyToStage"
      @close="copyingSecretStage = null"
    />
    
    <!-- History Dialog -->
    <HistoryDialog
      v-if="historySecret"
      :secretKey="historySecret.key"
      :vault="historySecret.vault"
      :stage="historySecret.stage"
      @refresh="loadDiff"
      @close="historySecret = null"
    />
    
    <!-- Delete Confirm Dialog -->
    <DeleteConfirmDialog
      v-if="deletingSecret"
      :secretKey="deletingSecret.key"
      :vault="deletingSecret.vault"
      :stage="deletingSecret.stage"
      @success="handleDeleteSuccess"
      @close="deletingSecret = null"
    />
  </div>
</template>

<script setup>
import {ref, computed, onMounted, onUnmounted, watch} from 'vue'
import {useToast} from '../composables/useToast'
import {useVault} from '../composables/useVault'
import {useSecrets} from '../composables/useSecrets'
import {useKeyboardShortcuts} from '../composables/useKeyboardShortcuts'
import {maskValue} from '../utils/formatters'
import SecretActionsMenu from './SecretActionsMenu.vue'
import SecretDialog from './SecretDialog.vue'
import RenameDialog from './RenameDialog.vue'
import CopyToStageDialog from './CopyToStageDialog.vue'
import HistoryDialog from './HistoryDialog.vue'
import DeleteConfirmDialog from './DeleteConfirmDialog.vue'

const toast = useToast()
const { vaults: availableVaults, stages: availableStages, loadAll: loadVaultData } = useVault()
const { copySecretToStage } = useSecrets()
const { registerSearchHandler, registerModalCloseHandler, registerMaskToggleHandler } = useKeyboardShortcuts()

// State
const selectedCombinations = ref([])
const availableCombinations = ref([])
const fullDiffMatrix = ref(null) // Store all data from server
const loading = ref(false)
const unmaskAll = ref(localStorage.getItem('keep.unmaskAll') === 'true')
const unmaskedRows = ref(new Set())
const showCombinationsDropdown = ref(false)
const activeCellMenu = ref(null)
const menuPosition = ref(null)
const activeMenuData = ref(null)
const editingSecret = ref(null)
const renamingSecret = ref(null)
const copyingSecretStage = ref(null)
const copyingSecret = ref(null)
const copyTarget = ref('')
const historySecret = ref(null)
const deletingSecret = ref(null)
const searchQuery = ref('')
const searchInput = ref(null)
const showRowColors = ref(localStorage.getItem('keep.diff.showColors') !== 'false') // Default true

// Toggle row colors and persist preference
function toggleRowColors() {
  showRowColors.value = !showRowColors.value
  localStorage.setItem('keep.diff.showColors', showRowColors.value.toString())
}

// Toggle unmask and persist preference
function toggleUnmask() {
  unmaskAll.value = !unmaskAll.value
  localStorage.setItem('keep.unmaskAll', unmaskAll.value.toString())
}

// Computed style for dynamic column widths
const columnWidthStyle = computed(() => {
  const columnCount = activeColumns.value.length
  if (columnCount === 0) return {}
  
  // Calculate equal width for value columns
  // Since first column is 256px (w-64), and we want equal distribution for the rest
  // We'll use calc to get the remaining space divided equally
  return {
    width: `calc((100% - 16rem) / ${columnCount})`
  }
})

// Computed columns based on selected combinations
const activeColumns = computed(() => {
  return selectedCombinations.value.map(key => {
    const [vault, stage] = key.split(':')
    // Find the vault display name
    const vaultObj = availableVaults.value.find(v => (v.slug || v.name || v) === vault)
    const vaultDisplay = vaultObj?.name || vaultObj?.display || vault
    return {vault, stage, vaultDisplay}
  })
})

// Save selections when they change
watch(selectedCombinations, (newVal) => {
  localStorage.setItem('keep.diff.selections', JSON.stringify(newVal))
}, {deep: true})

// Filter the diff matrix based on selected combinations and search query
const diffMatrix = computed(() => {
  if (!fullDiffMatrix.value) return {}
  if (selectedCombinations.value.length === 0) return {}

  const query = searchQuery.value.toLowerCase().trim()
  const filteredDiff = {}
  
  for (const [key, vaultData] of Object.entries(fullDiffMatrix.value)) {
    let hasSelectedCombination = false
    const filteredVaultData = {}
    
    // Check if key matches search query
    let matchesSearch = !query || key.toLowerCase().includes(query)

    for (const [vault, stageData] of Object.entries(vaultData)) {
      const filteredStageData = {}
      for (const [stage, value] of Object.entries(stageData)) {
        if (selectedCombinations.value.includes(`${vault}:${stage}`)) {
          filteredStageData[stage] = value
          hasSelectedCombination = true
          
          // Check if value matches search query (only for visible columns)
          if (!matchesSearch && query && value) {
            matchesSearch = value.toLowerCase().includes(query)
          }
        }
      }
      if (Object.keys(filteredStageData).length > 0) {
        filteredVaultData[vault] = filteredStageData
      }
    }

    // Only include if it has selected combinations AND matches search
    if (hasSelectedCombination && matchesSearch) {
      filteredDiff[key] = filteredVaultData
    }
  }

  return filteredDiff
})

// Get sorted keys for display
const sortedKeys = computed(() => {
  return Object.keys(diffMatrix.value).sort((a, b) => a.localeCompare(b))
})

// Get the status of a secret row
function getRowStatus(key) {
  if (!diffMatrix.value[key]) return 'incomplete'

  const values = []
  let totalExpected = activeColumns.value.length
  let totalFound = 0

  for (const column of activeColumns.value) {
    const value = getSecretValue(key, column.vault, column.stage)
    if (value !== null && value !== undefined) {
      values.push(value)
      totalFound++
    }
  }

  // If not present in all combinations, it's incomplete
  if (totalFound < totalExpected) {
    return 'incomplete'
  }

  // If all values are identical, it's identical
  if (new Set(values).size === 1) {
    return 'identical'
  }

  // Otherwise, it's different
  return 'different'
}

// Get the CSS class for a row based on its status
function getRowClass(key) {
  if (!showRowColors.value) return ''
  const status = getRowStatus(key)
  switch (status) {
    case 'incomplete':
      return 'bg-red-500/40'
    case 'identical':
      return 'bg-green-500/40'
    case 'different':
      return 'bg-yellow-500/40'
    default:
      return ''
  }
}


// Close dropdowns when clicking outside
// Keyboard shortcut cleanup functions
let cleanupModalHandler = null

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  loadInitialData()
  
  // Register keyboard shortcut handlers
  registerSearchHandler(() => {
    if (searchInput.value) {
      searchInput.value.focus()
    }
  })
  
  // Register modal close handlers for all dialogs
  cleanupModalHandler = registerModalCloseHandler(() => {
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
  document.removeEventListener('click', handleClickOutside)
  // Clean up modal handler
  if (cleanupModalHandler) {
    cleanupModalHandler()
  }
})

function handleClickOutside(e) {
  if (!e.target.closest('.relative')) {
    showCombinationsDropdown.value = false
  }
  // Close menu if clicking outside of it
  if (activeCellMenu.value && !e.target.closest('[data-menu-key]')) {
    activeCellMenu.value = null
    menuPosition.value = null
    activeMenuData.value = null
  }
}

async function loadInitialData() {
  try {
    await loadVaultData()

    // Build all combinations
    availableCombinations.value = []
    for (const vault of availableVaults.value) {
      for (const stage of availableStages.value) {
        const vaultSlug = vault.slug || vault.name || vault
        availableCombinations.value.push({
          key: `${vaultSlug}:${stage}`,
          vault: vaultSlug,
          vaultDisplay: vault.display || vault,
          stage: stage
        })
      }
    }

    // Load saved selections or select all by default
    const savedSelections = localStorage.getItem('keep.diff.selections')
    if (savedSelections) {
      try {
        const saved = JSON.parse(savedSelections)
        // Only use saved selections that are still valid
        selectedCombinations.value = saved.filter(s =>
            availableCombinations.value.some(c => c.key === s)
        )
      } catch (e) {
        // If parsing fails, select all
        selectedCombinations.value = availableCombinations.value.map(c => c.key)
      }
    } else {
      // Select all by default
      selectedCombinations.value = availableCombinations.value.map(c => c.key)
    }

    // Load diff automatically
    await loadDiff()
  } catch (error) {
    console.error('Failed to load initial data:', error)
    toast.error('Failed to load data', error.message)
  }
}

async function loadDiff() {
  // Load ALL vaults and stages from the server once
  const vaults = availableVaults.value.map(v => v.slug || v.name || v)
  const stages = [...availableStages.value]

  loading.value = true

  try {
    const data = await window.$api.getDiff(stages, vaults)
    fullDiffMatrix.value = data.diff || {}
  } catch (error) {
    console.error('Failed to load diff:', error)
    toast.error('Failed to load comparison', error.message)
    fullDiffMatrix.value = {}
  } finally {
    loading.value = false
  }
}

function getSecretValue(key, vault, stage) {
  if (!diffMatrix.value[key]) return null
  if (!diffMatrix.value[key][vault]) return null
  return diffMatrix.value[key][vault][stage] || null
}

function getCellClass(key, vault, stage) {
  const value = getSecretValue(key, vault, stage)
  if (!value) return 'bg-muted/30'
  return ''
}

function toggleRowMask(key) {
  if (unmaskedRows.value.has(key)) {
    unmaskedRows.value.delete(key)
  } else {
    unmaskedRows.value.add(key)
  }
}

function getMaskedValue(key, vault, stage) {
  const value = getSecretValue(key, vault, stage)
  if (!value) return ''

  // Check if row is unmasked or global unmask is on
  if (unmaskAll.value || unmaskedRows.value.has(key)) {
    return value
  }

  return maskValue(value, '•')
}

// Cell menu functionality is now handled by SecretActionsMenu component

function createSecret(data) {
  editingSecret.value = {key: data.key, vault: data.vault, stage: data.stage, value: '', isNew: true}
}

function editSecret(data) {
  editingSecret.value = {key: data.key, vault: data.vault, stage: data.stage, value: data.value}
}

function showCopyToStageDialog(data) {
  copyingSecretStage.value = {key: data.key, vault: data.vault, stage: data.stage, value: data.value}
}

function showHistoryDialog(data) {
  historySecret.value = {key: data.key, vault: data.vault, stage: data.stage}
}

function showDeleteDialog(data) {
  deletingSecret.value = {key: data.key, vault: data.vault, stage: data.stage}
}

function handleCopyValue(data) {
  toast.success('Copied to clipboard', 'Secret value has been copied to your clipboard')
}

async function handleDeleteSuccess() {
  await loadDiff()
  deletingSecret.value = null
}

function copyToStage(key, vault, stage) {
  copyingSecret.value = {key, vault, stage, value: getSecretValue(key, vault, stage)}
}

// Delete secret is now handled by SecretActionsMenu component

async function handleSecretSaveSuccess() {
  editingSecret.value = null
  await loadDiff()
}

async function executeCopy() {
  if (!copyingSecret.value || !copyTarget.value) return

  const [targetVault, targetStage] = copyTarget.value.split(':')

  try {
    await copySecretToStage(
        copyingSecret.value.key,
        targetStage,
        targetVault,
        copyingSecret.value.stage,
        copyingSecret.value.vault
    )
    toast.success('Secret copied', `Copied '${copyingSecret.value.key}' to ${targetVault}:${targetStage}`)
    copyingSecret.value = null
    copyTarget.value = ''
    await loadDiff()
  } catch (error) {
    toast.error('Copy failed', error.message)
  }
}

function selectAll() {
  selectedCombinations.value = availableCombinations.value.map(c => c.key)
}

function selectNone() {
  selectedCombinations.value = []
}

async function handleCopyToStage({ targetVault, targetStage }) {
  try {
    await copySecretToStage(
      copyingSecretStage.value.key, 
      targetStage, 
      targetVault, 
      copyingSecretStage.value.stage,
      copyingSecretStage.value.vault
    )
    toast.success('Secret copied', `Secret '${copyingSecretStage.value.key}' copied to ${targetVault}:${targetStage}`)
    copyingSecretStage.value = null
    await loadDiff()
  } catch (error) {
    console.error('Failed to copy secret:', error)
    toast.error('Failed to copy secret', error.message)
  }
}

async function handleRenameSuccess() {
  await loadDiff()
  renamingSecret.value = null
}

</script>