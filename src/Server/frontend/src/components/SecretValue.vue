<template>
  <div class="flex items-center space-x-2">
    <div class="flex items-center opacity-30 group-hover:opacity-100 transition-opacity">
      <Tooltip :content="masked ? 'Show value' : 'Hide value'" :delay-duration="200">
        <button
            @click="$emit('toggle')"
            class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            :title="masked ? 'Show value' : 'Hide value'"
        >
          <svg v-if="masked" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
          </svg>
        </button>
      </Tooltip>

      <Tooltip content="Copy Value" :delay-duration="200">
        <button
            @click="handleCopyValue({ key: secret.key, value: secret.value, vault, stage })"
            class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
          </svg>
        </button>
      </Tooltip>
    </div>

    <div class="font-mono text-sm">
      {{ displayValue }}
    </div>
  </div>
</template>

<script setup>
import {computed} from 'vue'
import {maskValue} from '../utils/formatters'
import Tooltip from "@/components/ui/Tooltip.vue";
import {useToast} from '../composables/useToast'

const toast = useToast()

const props = defineProps({
  secret: {
    type: Object,
    required: true
  },
  value: String,
  masked: {
    type: Boolean,
    default: true
  }
})

defineEmits(['toggle'])

const displayValue = computed(() => {
  if (!props.masked) return props.value
  return maskValue(props.value)
})

function handleCopyValue(data) {
  if (data.value) {
    navigator.clipboard.writeText(data.value)
    toast.success('Copied to clipboard', 'Secret value has been copied to your clipboard')
  }
}
</script>