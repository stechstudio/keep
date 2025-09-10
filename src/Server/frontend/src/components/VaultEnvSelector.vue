<template>
  <div class="flex items-center space-x-3">
    <!-- Vault Selector - only show if more than one vault -->
    <div v-if="vaults && vaults.length > 1" class="relative">
      <button
        @click="vaultOpen = !vaultOpen"
        class="flex items-center space-x-2 px-3 pl-0 py-1.5 transition-colors"
      >
        <span>{{ vaultDisplay || 'Select Vault' }}</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      
      <div v-if="vaultOpen" class="absolute top-full mt-1 bg-popover border border-border rounded-md shadow-lg z-10">
        <div class="py-1">
          <button
            v-for="v in vaults"
            :key="v.slug || v.name || v"
            @click="selectVault(v.slug || v.name || v)"
            :class="[
              'w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors whitespace-nowrap',
              vault === (v.slug || v.name || v) ? 'bg-white/10' : ''
            ]"
          >
            {{ v.display || v }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="vaults && vaults.length > 1" class="text-lg text-white/30 font-bold pr-3">/</div>

    <!-- Env Selector -->
    <div class="relative">
      <button
        @click="envOpen = !envOpen"
        class="flex items-center space-x-2 px-3 pl-0 py-1.5 transition-colors"
      >
        <span>{{ env || 'Select Env' }}</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      
      <div v-if="envOpen" class="absolute top-full mt-1 w-48 bg-popover border border-border rounded-md shadow-lg z-10">
        <div class="py-1">
          <button
            v-for="s in envs"
            :key="s"
            @click="selectEnv(s)"
            :class="[
              'w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors',
              env === s ? 'bg-white/10' : ''
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
  env: String,
  vaults: Array,
  envs: Array
})

const emit = defineEmits(['update:vault', 'update:env'])

const vaultOpen = ref(false)
const envOpen = ref(false)

// Get display name for selected vault
const vaultDisplay = computed(() => {
  if (!props.vault) return ''
  const vaultObj = props.vaults?.find(v => 
    (typeof v === 'object' ? (v.slug || v.name) : v) === props.vault
  )
  return vaultObj?.display || props.vault
})

function selectVault(v) {
  emit('update:vault', v)
  vaultOpen.value = false
}

function selectEnv(s) {
  emit('update:env', s)
  envOpen.value = false
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.relative')) {
    vaultOpen.value = false
    envOpen.value = false
  }
})
</script>