<template>
  <div class="relative inline-block text-left">
    <button
      ref="buttonRef"
      @click="toggleMenu"
      :class="['p-1 rounded hover:bg-muted transition-colors', buttonClass]"
    >
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
      </svg>
    </button>
    
    <Teleport to="body" v-if="isOpen">
      <div
        :style="menuStyle"
        class="fixed w-48 bg-popover border border-border rounded-md shadow-lg z-50"
      >
        <div class="py-1 flex flex-col">
          <button
            v-if="showEdit"
            @click="handleEdit"
            class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
          >
            Edit
          </button>
          <button
            v-if="showRename"
            @click="handleRename"
            class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
          >
            Rename
          </button>
          <button
            v-if="showCreate"
            @click="handleCreate"
            class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
          >
            Create
          </button>
          <button
            v-if="showCopyValue"
            @click="handleCopyValue"
            class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
          >
            Copy Value
          </button>
          <button
            v-if="showCopyTo"
            @click="handleCopyTo"
            class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
          >
            Copy To...
          </button>
          <button
            v-if="showHistory"
            @click="handleHistory"
            class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors"
          >
            History
          </button>
          <button
            v-if="showDelete"
            @click="handleDelete"
            class="w-full text-left px-4 py-2 text-sm text-destructive hover:bg-accent transition-colors"
          >
            Delete
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  secretKey: {
    type: String,
    required: true
  },
  secretValue: {
    type: String,
    default: null
  },
  vault: {
    type: String,
    required: true
  },
  stage: {
    type: String,
    required: true
  },
  showEdit: {
    type: Boolean,
    default: true
  },
  showRename: {
    type: Boolean,
    default: true
  },
  showCreate: {
    type: Boolean,
    default: false
  },
  showCopyValue: {
    type: Boolean,
    default: true
  },
  showCopyTo: {
    type: Boolean,
    default: true
  },
  showDelete: {
    type: Boolean,
    default: true
  },
  showHistory: {
    type: Boolean,
    default: true
  },
  buttonClass: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['edit', 'rename', 'create', 'copyValue', 'copyTo', 'delete', 'history', 'refresh'])

const isOpen = ref(false)
const menuStyle = ref({})
const buttonRef = ref(null)

function toggleMenu(event) {
  event.stopPropagation()
  
  if (isOpen.value) {
    isOpen.value = false
  } else {
    // Close any other open menus by dispatching a custom event
    document.dispatchEvent(new CustomEvent('close-all-menus'))
    
    const button = event.currentTarget
    const rect = button.getBoundingClientRect()
    
    // Always position menu below and to the left of the button
    const left = rect.right - 192 // 192px = 48rem (w-48)
    const top = rect.bottom + 4
    
    menuStyle.value = {
      left: `${left}px`,
      top: `${top}px`
    }
    
    // Small delay to prevent immediate closing
    setTimeout(() => {
      isOpen.value = true
    }, 0)
  }
}

function handleEdit() {
  emit('edit', { key: props.secretKey, value: props.secretValue, vault: props.vault, stage: props.stage })
  isOpen.value = false
}

function handleRename() {
  emit('rename', { key: props.secretKey, value: props.secretValue, vault: props.vault, stage: props.stage })
  isOpen.value = false
}

function handleCreate() {
  emit('create', { key: props.secretKey })
  isOpen.value = false
}

function handleCopyValue() {
  if (props.secretValue) {
    navigator.clipboard.writeText(props.secretValue)
  }
  emit('copyValue', { key: props.secretKey })
  isOpen.value = false
}

function handleCopyTo() {
  emit('copyTo', { key: props.secretKey, value: props.secretValue, vault: props.vault, stage: props.stage })
  isOpen.value = false
}

function handleHistory() {
  emit('history', { key: props.secretKey, vault: props.vault, stage: props.stage })
  isOpen.value = false
}

function handleDelete() {
  emit('delete', { key: props.secretKey, vault: props.vault, stage: props.stage })
  isOpen.value = false
}

// Close menu when clicking outside
function handleClickOutside(event) {
  if (isOpen.value && buttonRef.value && !buttonRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

// Close this menu when another menu opens
function handleCloseAllMenus() {
  isOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('close-all-menus', handleCloseAllMenus)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('close-all-menus', handleCloseAllMenus)
})
</script>