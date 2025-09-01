<template>
  <div>
    <!-- Controls -->
    <div class="mb-6 flex items-center space-x-4">
      <div>
        <label class="block text-xs font-medium text-muted-foreground mb-1">Compare</label>
        <div class="flex items-center space-x-2">
          <select v-model="diffType" class="px-3 py-2 bg-input border border-border rounded-md text-sm">
            <option value="stages">Stages</option>
            <option value="vaults">Vaults</option>
          </select>
        </div>
      </div>
      
      <div v-if="diffType === 'stages'">
        <label class="block text-xs font-medium text-muted-foreground mb-1">Select Stages</label>
        <div class="flex items-center space-x-2">
          <select v-model="selectedStages" multiple class="px-3 py-2 bg-input border border-border rounded-md text-sm min-w-[200px]">
            <option v-for="stage in availableStages" :key="stage" :value="stage">{{ stage }}</option>
          </select>
        </div>
      </div>
      
      <div v-if="diffType === 'vaults'">
        <label class="block text-xs font-medium text-muted-foreground mb-1">Select Vaults</label>
        <div class="flex items-center space-x-2">
          <select v-model="selectedVaults" multiple class="px-3 py-2 bg-input border border-border rounded-md text-sm min-w-[200px]">
            <option v-for="vault in availableVaults" :key="vault" :value="vault">{{ vault }}</option>
          </select>
        </div>
      </div>
      
      <button
        @click="runDiff"
        class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors text-sm font-medium"
      >
        Compare
      </button>
    </div>

    <!-- Results -->
    <div v-if="diffResults" class="space-y-4">
      <div v-for="(group, key) in diffResults" :key="key" class="border border-border rounded-lg overflow-hidden">
        <div class="bg-muted px-4 py-2 font-mono text-sm">{{ key }}</div>
        <div class="bg-card p-4">
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th class="text-left font-medium pb-2">{{ diffType === 'stages' ? 'Stage' : 'Vault' }}</th>
                <th class="text-left font-medium pb-2">Value</th>
                <th class="text-left font-medium pb-2">Status</th>
              </tr>
            </thead>
            <tbody class="space-y-1">
              <tr v-for="(item, idx) in group" :key="idx">
                <td class="py-1">{{ item.location }}</td>
                <td class="py-1 font-mono">
                  <SecretValue :value="item.value || '(missing)'" :masked="masked" @toggle="masked = !masked" />
                </td>
                <td class="py-1">
                  <span
                    :class="[
                      'px-2 py-0.5 text-xs rounded-full',
                      item.status === 'missing' ? 'bg-destructive/20 text-destructive' :
                      item.status === 'different' ? 'bg-yellow-500/20 text-yellow-500' :
                      'bg-green-500/20 text-green-500'
                    ]"
                  >
                    {{ item.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    
    <div v-if="loading" class="text-center py-8 text-muted-foreground">
      Running comparison...
    </div>
    
    <div v-if="!loading && !diffResults" class="text-center py-8 text-muted-foreground">
      Select items to compare and click "Compare"
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import SecretValue from './SecretValue.vue'

const diffType = ref('stages')
const selectedStages = ref([])
const selectedVaults = ref([])
const availableStages = ref([])
const availableVaults = ref([])
const diffResults = ref(null)
const loading = ref(false)
const masked = ref(true)

onMounted(async () => {
  try {
    const [stagesData, vaultsData] = await Promise.all([
      window.$api.listStages(),
      window.$api.listVaults()
    ])
    availableStages.value = stagesData.stages || []
    availableVaults.value = vaultsData.vaults || []
  } catch (error) {
    console.error('Failed to load stages and vaults:', error)
  }
})

async function runDiff() {
  loading.value = true
  diffResults.value = null
  
  try {
    const params = diffType.value === 'stages'
      ? { stages: selectedStages.value }
      : { vaults: selectedVaults.value }
    
    const data = await window.$api.getDiff(params.stages, params.vaults)
    
    // Transform the diff results into a more usable format
    const results = {}
    for (const [key, values] of Object.entries(data.diff || {})) {
      results[key] = Object.entries(values).map(([location, value]) => ({
        location,
        value,
        status: value === null ? 'missing' : 'present'
      }))
      
      // Mark differences
      const uniqueValues = new Set(results[key].map(r => r.value).filter(v => v !== null))
      if (uniqueValues.size > 1) {
        results[key].forEach(r => {
          if (r.status === 'present') r.status = 'different'
        })
      }
    }
    
    diffResults.value = results
  } catch (error) {
    console.error('Failed to run diff:', error)
  } finally {
    loading.value = false
  }
}
</script>