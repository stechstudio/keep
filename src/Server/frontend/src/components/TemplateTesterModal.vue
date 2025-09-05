<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-black opacity-75"></div>
      </div>

      <!-- Modal content -->
      <div class="relative inline-block w-full max-w-3xl px-6 py-5 overflow-hidden text-left align-middle transition-all transform bg-background rounded-lg shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-semibold">Test Template</h2>
            <p class="text-sm text-muted-foreground">{{ template.filename }}</p>
          </div>
          <button
            @click="$emit('close')"
            class="p-1 rounded hover:bg-muted transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Test Configuration -->
        <div v-if="!hasRun" class="space-y-4 mb-6">
          <div>
            <label class="block text-sm font-medium mb-2">Test Against Stage</label>
            <select
              v-model="selectedStage"
              class="w-full px-3 py-2 border border-border rounded-md bg-background focus:ring-2 focus:ring-primary focus:border-transparent"
            >
              <option :value="template.stage">{{ template.stage }} (template stage)</option>
              <option v-for="stage in otherStages" :key="stage" :value="stage">
                {{ stage }}
              </option>
            </select>
          </div>
        </div>

        <!-- Results -->
        <div v-if="hasRun" class="space-y-6">
          <!-- Overall Status -->
          <div :class="[
            'rounded-lg p-4 border',
            validationResult.valid 
              ? 'bg-green-500/10 border-green-500/20' 
              : 'bg-destructive/10 border-destructive/20'
          ]">
            <div class="flex items-center gap-3">
              <svg v-if="validationResult.valid" class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <svg v-else class="w-6 h-6 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <h3 class="font-semibold">
                  {{ validationResult.valid ? 'Template is Valid' : 'Template Has Issues' }}
                </h3>
                <p class="text-sm text-muted-foreground">
                  {{ validationResult.placeholderCount }} placeholder{{ validationResult.placeholderCount !== 1 ? 's' : '' }} checked
                </p>
              </div>
            </div>
          </div>

          <!-- Errors -->
          <div v-if="validationResult.errors && validationResult.errors.length > 0" class="space-y-4">
            <h3 class="font-medium text-destructive">Errors ({{ validationResult.errors.length }})</h3>
            <div class="space-y-2">
              <div 
                v-for="error in validationResult.errors" 
                :key="`${error.line}-${error.key}`"
                class="bg-destructive/5 border border-destructive/20 rounded-md p-3"
              >
                <div class="flex items-start gap-2">
                  <svg class="w-4 h-4 mt-0.5 text-destructive flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <div class="flex-1">
                    <p class="text-sm font-medium">
                      Line {{ error.line }}: <span class="font-mono">{{ error.key }}</span>
                    </p>
                    <p class="text-sm text-muted-foreground mt-1">
                      {{ error.error }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                      Vault: {{ error.vault || 'Unknown' }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Warnings -->
          <div v-if="validationResult.warnings && validationResult.warnings.length > 0" class="space-y-4">
            <h3 class="font-medium text-warning">Warnings ({{ validationResult.warnings.length }})</h3>
            <div class="space-y-2">
              <div 
                v-for="warning in validationResult.warnings" 
                :key="`${warning.vault}-${warning.key}`"
                class="bg-warning/5 border border-warning/20 rounded-md p-3"
              >
                <div class="flex items-start gap-2">
                  <svg class="w-4 h-4 mt-0.5 text-warning flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                  <div class="flex-1">
                    <p class="text-sm font-medium">
                      {{ warning.message }}
                    </p>
                    <p class="text-sm text-muted-foreground mt-1">
                      <span class="font-mono">{{ warning.vault }}:{{ warning.key }}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Unused Secrets -->
          <div v-if="validationResult.unusedSecrets && validationResult.unusedSecrets.length > 0" class="space-y-4">
            <h3 class="font-medium">Unused Secrets ({{ validationResult.unusedSecrets.length }})</h3>
            <div class="bg-muted/30 rounded-md p-3">
              <p class="text-sm text-muted-foreground mb-3">
                These secrets exist in the vaults but are not referenced in the template:
              </p>
              <div class="flex flex-wrap gap-2">
                <span 
                  v-for="secret in validationResult.unusedSecrets" 
                  :key="`${secret.vault}-${secret.key}`"
                  class="inline-flex items-center px-2 py-1 text-xs font-mono bg-background border border-border rounded"
                >
                  {{ secret.vault }}:{{ secret.key }}
                </span>
              </div>
            </div>
          </div>

          <!-- Success Message -->
          <div v-if="validationResult.valid && (!validationResult.warnings || validationResult.warnings.length === 0)" class="text-center py-4">
            <svg class="w-12 h-12 mx-auto text-green-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-green-600 font-medium">All placeholders are valid!</p>
            <p class="text-sm text-muted-foreground mt-1">
              The template can be successfully processed for the {{ selectedStage }} stage.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end mt-6 gap-3">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
          >
            Cancel
          </button>
          <div class="flex gap-3">
            <button
              v-if="hasRun"
              @click="resetTest"
              class="px-4 py-2 text-sm border border-border rounded-md hover:bg-muted transition-colors"
            >
              Test Again
            </button>
            <button
              v-if="!hasRun"
              @click="runValidation"
              :disabled="validating || !selectedStage"
              class="px-4 py-2 text-sm bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors disabled:opacity-50"
            >
              {{ validating ? 'Validating...' : 'Run Validation' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '../composables/useToast'

const props = defineProps({
  template: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close'])
const { showToast } = useToast()

const validating = ref(false)
const hasRun = ref(false)
const selectedStage = ref('')
const stages = ref([])
const validationResult = ref({
  valid: false,
  errors: [],
  warnings: [],
  unusedSecrets: [],
  placeholderCount: 0
})

const otherStages = computed(() => {
  return stages.value.filter(s => s !== props.template.stage)
})

onMounted(async () => {
  selectedStage.value = props.template.stage
  await loadStages()
})

async function loadStages() {
  try {
    const settings = await window.$api.getSettings()
    stages.value = settings.stages || []
  } catch (error) {
    // Failed to load stages
  }
}

async function runValidation() {
  if (!selectedStage.value) {
    showToast('Please select a stage', 'warning')
    return
  }

  validating.value = true
  try {
    const response = await window.$api.post('/templates/validate', {
      filename: props.template.filename,
      stage: selectedStage.value
    })

    validationResult.value = response
    hasRun.value = true

    if (response.valid) {
      if (response.warnings && response.warnings.length > 0) {
        showToast('Template is valid with warnings', 'warning')
      } else {
        showToast('Template is fully valid!', 'success')
      }
    } else {
      showToast(`Found ${response.errors.length} error${response.errors.length !== 1 ? 's' : ''}`, 'error')
    }
  } catch (error) {
    showToast('Failed to validate template', 'error')
  } finally {
    validating.value = false
  }
}

function resetTest() {
  hasRun.value = false
  validationResult.value = {
    valid: false,
    errors: [],
    warnings: [],
    unusedSecrets: [],
    placeholderCount: 0
  }
}
</script>