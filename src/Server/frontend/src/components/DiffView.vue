<template>
  <div>
    <!-- Controls -->
    <div class="mb-6 space-y-4">
      <div class="flex items-center space-x-4">
        <div class="flex-1">
          <label class="block text-xs font-medium text-muted-foreground mb-2">Select Vault</label>
          <select 
            v-model="selectedVault" 
            class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="">Select a vault...</option>
            <option v-for="vault in availableVaults" :key="vault.name" :value="vault.name">
              {{ vault.display }}
            </option>
          </select>
        </div>

        <div class="flex-1">
          <label class="block text-xs font-medium text-muted-foreground mb-2">Select Stages to Compare</label>
          <div class="flex flex-wrap gap-2">
            <label
              v-for="stage in availableStages"
              :key="stage"
              class="inline-flex items-center"
            >
              <input
                type="checkbox"
                :value="stage"
                v-model="selectedStages"
                class="mr-2 rounded border-border bg-input text-primary focus:ring-2 focus:ring-ring"
              />
              <span class="text-sm">{{ stage }}</span>
            </label>
          </div>
        </div>

        <div class="flex items-end">
          <button
            @click="runComparison"
            :disabled="!canCompare"
            class="px-6 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Compare
          </button>
        </div>
      </div>

      <!-- Toggle for mask/unmask all -->
      <div v-if="diffMatrix && Object.keys(diffMatrix).length > 0" class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <button
            @click="unmaskAll = !unmaskAll"
            class="flex items-center space-x-2 px-3 py-1.5 text-sm border border-border rounded-md hover:bg-accent transition-colors"
          >
            <svg v-if="!unmaskAll" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
            <span>{{ unmaskAll ? 'Hide All Values' : 'Show All Values' }}</span>
          </button>
          <span class="text-sm text-muted-foreground">
            {{ Object.keys(diffMatrix).length }} secrets found
          </span>
        </div>
        
        <button
          @click="exportDiff"
          class="flex items-center space-x-2 px-3 py-1.5 text-sm border border-border rounded-md hover:bg-accent transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>Export CSV</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-flex items-center space-x-2 text-muted-foreground">
        <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Comparing secrets across stages...</span>
      </div>
    </div>

    <!-- Diff Matrix -->
    <div v-else-if="diffMatrix && Object.keys(diffMatrix).length > 0" class="border border-border rounded-lg overflow-x-auto">
      <table class="w-full">
        <thead class="bg-muted sticky top-0">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider sticky left-0 bg-muted z-10">
              Secret Key
            </th>
            <th 
              v-for="stage in selectedStages" 
              :key="stage"
              class="px-4 py-3 text-xs font-medium text-muted-foreground uppercase tracking-wider text-center min-w-[200px]"
            >
              {{ stage }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <tr v-for="(stageValues, key) in diffMatrix" :key="key" class="hover:bg-muted/50 transition-colors">
            <td class="px-4 py-3 font-mono text-sm font-medium sticky left-0 bg-background">
              {{ key }}
            </td>
            <td 
              v-for="stage in selectedStages" 
              :key="`${key}-${stage}`"
              class="px-4 py-3 text-center"
              :class="getCellClass(key, stage)"
            >
              <div v-if="getSecretValue(key, stage)" class="flex items-center justify-center space-x-2">
                <SecretValue
                  :value="getSecretValue(key, stage)"
                  :masked="!unmaskAll && !unmaskedCells.has(`${key}-${stage}`)"
                  @toggle="toggleCellMask(key, stage)"
                />
                <span v-if="isDifferent(key)" class="text-yellow-500">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                </span>
              </div>
              <span v-else class="text-muted-foreground text-sm">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading && diffMatrix !== null" class="text-center py-12 text-muted-foreground">
      <svg class="mx-auto h-12 w-12 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p>No secrets found in the selected vault and stages</p>
    </div>

    <!-- Initial State -->
    <div v-else class="text-center py-12 text-muted-foreground">
      <svg class="mx-auto h-12 w-12 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
      </svg>
      <p class="text-lg font-medium mb-2">Compare Secrets Across Stages</p>
      <p class="text-sm">Select a vault and at least two stages to see differences</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import SecretValue from './SecretValue.vue'
import { useToast } from '../composables/useToast'

const toast = useToast()

const selectedVault = ref('')
const selectedStages = ref([])
const availableVaults = ref([])
const availableStages = ref([])
const diffMatrix = ref(null)
const loading = ref(false)
const unmaskAll = ref(false)
const unmaskedCells = ref(new Set())

const canCompare = computed(() => {
  return selectedVault.value && selectedStages.value.length >= 2
})

onMounted(async () => {
  try {
    const [vaultsData, stagesData] = await Promise.all([
      window.$api.listVaults(),
      window.$api.listStages()
    ])
    availableVaults.value = vaultsData.vaults || []
    availableStages.value = stagesData.stages || []
    
    // Auto-select all stages for convenience
    selectedStages.value = [...availableStages.value]
  } catch (error) {
    console.error('Failed to load vaults and stages:', error)
    toast.error('Failed to load data', error.message)
  }
})

async function runComparison() {
  if (!canCompare.value) return
  
  loading.value = true
  diffMatrix.value = null
  unmaskedCells.value.clear()
  
  try {
    const data = await window.$api.getDiff(selectedStages.value, [selectedVault.value])
    diffMatrix.value = data.diff || {}
  } catch (error) {
    console.error('Failed to run comparison:', error)
    toast.error('Comparison failed', error.message)
  } finally {
    loading.value = false
  }
}

function getSecretValue(key, stage) {
  if (!diffMatrix.value[key]) return null
  if (!diffMatrix.value[key][selectedVault.value]) return null
  return diffMatrix.value[key][selectedVault.value][stage] || null
}

function isDifferent(key) {
  if (!diffMatrix.value[key] || !diffMatrix.value[key][selectedVault.value]) return false
  
  const values = selectedStages.value
    .map(stage => diffMatrix.value[key][selectedVault.value][stage])
    .filter(v => v !== null && v !== undefined)
  
  return new Set(values).size > 1
}

function getCellClass(key, stage) {
  const value = getSecretValue(key, stage)
  if (!value) return 'bg-muted/30'
  if (isDifferent(key)) return 'bg-yellow-500/10'
  return ''
}

function toggleCellMask(key, stage) {
  const cellKey = `${key}-${stage}`
  if (unmaskedCells.value.has(cellKey)) {
    unmaskedCells.value.delete(cellKey)
  } else {
    unmaskedCells.value.add(cellKey)
  }
}

function exportDiff() {
  if (!diffMatrix.value) return
  
  // Build CSV content
  const rows = []
  rows.push(['Secret Key', ...selectedStages.value])
  
  for (const [key, vaultData] of Object.entries(diffMatrix.value)) {
    const row = [key]
    for (const stage of selectedStages.value) {
      const value = vaultData[selectedVault.value]?.[stage] || ''
      row.push(value)
    }
    rows.push(row)
  }
  
  // Convert to CSV
  const csv = rows.map(row => 
    row.map(cell => {
      // Escape quotes and wrap in quotes if contains comma or newline
      const escaped = String(cell).replace(/"/g, '""')
      return /[,\n"]/.test(escaped) ? `"${escaped}"` : escaped
    }).join(',')
  ).join('\n')
  
  // Copy to clipboard (could also trigger download)
  navigator.clipboard.writeText(csv)
    .then(() => {
      toast.success('Exported to clipboard', 'Diff matrix has been copied as CSV')
    })
    .catch(err => {
      console.error('Failed to copy:', err)
      toast.error('Export failed', 'Could not copy to clipboard')
    })
}
</script>