<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-card border border-border rounded-lg p-6 w-96 max-w-full">
      <h2 class="text-lg font-semibold mb-4">Copy Secret</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Secret Key</label>
        <input
          :value="secretKey"
          disabled
          class="w-full px-3 py-2 bg-muted border border-border rounded-md text-sm opacity-60"
        />
      </div>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">From</label>
        <input
          :value="`${currentVault} / ${currentEnv}`"
          disabled
          class="w-full px-3 py-2 bg-muted border border-border rounded-md text-sm opacity-60"
        />
      </div>
      
      <div class="mb-6">
        <label class="block text-sm font-medium mb-2">Copy To</label>
        <select
          v-model="targetCombination"
          class="w-full px-3 py-2 bg-input border border-border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        >
          <option value="">Select destination...</option>
          <option
            v-for="combo in availableCombinations"
            :key="combo.key"
            :value="combo.key"
            :disabled="combo.key === `${currentVault}:${currentEnv}`"
          >
            {{ combo.display }} / {{ combo.env }}
          </option>
        </select>
      </div>
      
      <div class="flex justify-end space-x-3">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm border border-border rounded-md hover:bg-accent transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleCopy"
          :disabled="!targetCombination"
          class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Copy Secret
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  secretKey: {
    type: String,
    required: true
  },
  currentVault: {
    type: String,
    required: true
  },
  currentEnv: {
    type: String,
    required: true
  },
  vaults: {
    type: Array,
    required: true
  },
  envs: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['close', 'copy'])

const targetCombination = ref('')

const availableCombinations = computed(() => {
  const combinations = []
  for (const vault of props.vaults) {
    for (const env of props.envs) {
      combinations.push({
        key: `${vault.slug || vault}:${env}`,
        vaultSlug: vault.slug || vault,
        display: vault.display || vault.name || vault,
        env: env
      })
    }
  }
  return combinations
})

function handleCopy() {
  if (targetCombination.value) {
    const [targetVault, targetEnv] = targetCombination.value.split(':')
    emit('copy', { targetVault, targetEnv })
  }
}
</script>