<template>
  <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
    <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md">
      <div class="flex items-start space-x-3">
        <div class="flex-shrink-0">
          <div class="h-10 w-10 rounded-full bg-destructive/10 flex items-center justify-center">
            <svg class="h-6 w-6 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold">Delete Secret</h3>
          <p class="mt-2 text-sm text-muted-foreground">
            Are you sure you want to delete this secret?
          </p>
          <div class="mt-3 p-3 bg-muted/50 rounded-md">
            <div class="text-sm">
              <div class="font-medium">{{ secretKey }}</div>
              <div class="text-xs text-muted-foreground mt-1">
                {{ vault }} / {{ stage }}
              </div>
            </div>
          </div>
          <p class="mt-3 text-sm text-muted-foreground">
            This action cannot be undone.
          </p>
        </div>
      </div>
      
      <div class="flex justify-end space-x-3 mt-6">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium border border-border rounded-md hover:bg-muted transition-colors"
        >
          Cancel
        </button>
        <button
          @click="$emit('confirm')"
          class="px-4 py-2 text-sm font-medium bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90 transition-colors"
        >
          Delete Secret
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  secretKey: {
    type: String,
    required: true
  },
  vault: {
    type: String,
    required: true
  },
  stage: {
    type: String,
    required: true
  }
})

defineEmits(['close', 'confirm'])
</script>