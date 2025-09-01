<template>
  <div class="flex items-center space-x-3">
    <!-- Vault Selector -->
    <div class="relative">
      <button
        @click="vaultOpen = !vaultOpen"
        class="flex items-center space-x-2 px-3 py-1.5 bg-muted rounded-md hover:bg-accent transition-colors text-sm min-w-[200px]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
        </svg>
        <span>{{ vaultDisplay || 'Select Vault' }}</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      
      <div v-if="vaultOpen" class="absolute top-full mt-1 w-80 bg-popover border border-border rounded-md shadow-lg z-10">
        <div class="py-1">
          <button
            v-for="v in vaults"
            :key="v.name || v"
            @click="selectVault(v.name || v)"
            :class="[
              'w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors',
              vault === (v.name || v) ? 'bg-accent' : ''
            ]"
          >
            {{ v.display || v }}
          </button>
        </div>
      </div>
    </div>

    <!-- Stage Selector -->
    <div class="relative">
      <button
        @click="stageOpen = !stageOpen"
        class="flex items-center space-x-2 px-3 py-1.5 bg-muted rounded-md hover:bg-accent transition-colors text-sm"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
        </svg>
        <span>{{ stage || 'Select Stage' }}</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      
      <div v-if="stageOpen" class="absolute top-full mt-1 w-48 bg-popover border border-border rounded-md shadow-lg z-10">
        <div class="py-1">
          <button
            v-for="s in stages"
            :key="s"
            @click="selectStage(s)"
            :class="[
              'w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors',
              stage === s ? 'bg-accent' : ''
            ]"
          >
            {{ s }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  vault: String,
  stage: String,
  vaults: Array,
  stages: Array
})

const emit = defineEmits(['update:vault', 'update:stage'])

const vaultOpen = ref(false)
const stageOpen = ref(false)

// Get display name for selected vault
const vaultDisplay = computed(() => {
  if (!props.vault) return ''
  const vaultObj = props.vaults?.find(v => 
    (typeof v === 'object' ? v.name : v) === props.vault
  )
  return vaultObj?.display || props.vault
})

function selectVault(v) {
  emit('update:vault', v)
  vaultOpen.value = false
}

function selectStage(s) {
  emit('update:stage', s)
  stageOpen.value = false
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.relative')) {
    vaultOpen.value = false
    stageOpen.value = false
  }
})
</script>