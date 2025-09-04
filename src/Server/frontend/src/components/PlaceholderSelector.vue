<template>
  <div class="fixed inset-0 z-[60] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity" @click="$emit('close')">
        <div class="absolute inset-0 bg-black opacity-50"></div>
      </div>

      <!-- Modal content -->
      <div class="relative inline-block w-full max-w-lg px-6 py-5 overflow-hidden text-left align-middle transition-all transform bg-background rounded-lg shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Insert Placeholder</h3>
          <button
            @click="$emit('close')"
            class="p-1 rounded hover:bg-muted transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Search -->
        <div class="mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search secrets..."
            class="w-full px-3 py-2 border border-border rounded-md bg-background focus:ring-2 focus:ring-primary focus:border-transparent"
            @keydown.escape="$emit('close')"
          />
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
        </div>

        <!-- Placeholder List -->
        <div v-else-if="filteredPlaceholders.length > 0" class="max-h-96 overflow-y-auto space-y-1">
          <button
            v-for="item in filteredPlaceholders"
            :key="item.placeholder"
            @click="selectPlaceholder(item.placeholder)"
            class="w-full text-left px-3 py-2 rounded hover:bg-muted transition-colors group"
          >
            <div class="flex items-center justify-between">
              <div>
                <span class="font-mono text-sm">{{ item.placeholder }}</span>
                <div class="text-xs text-muted-foreground mt-1">
                  <span class="inline-flex items-center gap-1">
                    <span>{{ item.vault }}</span>
                    <span>•</span>
                    <span>{{ item.key }}</span>
                  </span>
                </div>
              </div>
              <span class="text-xs text-muted-foreground group-hover:text-foreground">
                {{ item.value }}
              </span>
            </div>
          </button>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8">
          <svg class="w-12 h-12 mx-auto text-muted-foreground mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
          </svg>
          <p class="text-muted-foreground">
            {{ searchQuery ? 'No matching secrets found' : 'No secrets available' }}
          </p>
        </div>

        <!-- Footer -->
        <div class="mt-4 pt-4 border-t border-border">
          <p class="text-xs text-muted-foreground">
            Select a secret to insert its placeholder at the cursor position
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  stage: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'select'])

const loading = ref(true)
const searchQuery = ref('')
const placeholders = ref([])

const filteredPlaceholders = computed(() => {
  if (!searchQuery.value) {
    return placeholders.value
  }
  
  const query = searchQuery.value.toLowerCase()
  return placeholders.value.filter(item => 
    item.placeholder.toLowerCase().includes(query) ||
    item.vault.toLowerCase().includes(query) ||
    item.key.toLowerCase().includes(query)
  )
})

onMounted(async () => {
  await loadPlaceholders()
})

async function loadPlaceholders() {
  loading.value = true
  try {
    const response = await window.$api.get(`/api/templates/placeholders?stage=${props.stage}`)
    placeholders.value = response.placeholders || []
  } catch (error) {
    console.error('Failed to load placeholders:', error)
    placeholders.value = []
  } finally {
    loading.value = false
  }
}

function selectPlaceholder(placeholder) {
  emit('select', placeholder)
  emit('close')
}
</script>