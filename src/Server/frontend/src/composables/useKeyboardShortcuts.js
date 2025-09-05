import { onMounted, onUnmounted, ref } from 'vue'

// Global registry for keyboard shortcut handlers
const handlers = ref({
  search: null,
  closeModal: [],
  toggleMask: null
})

export function useKeyboardShortcuts() {
  // Register handlers
  function registerSearchHandler(handler) {
    handlers.value.search = handler
  }
  
  function registerModalCloseHandler(handler) {
    handlers.value.closeModal.push(handler)
    
    // Return cleanup function
    return () => {
      const index = handlers.value.closeModal.indexOf(handler)
      if (index > -1) {
        handlers.value.closeModal.splice(index, 1)
      }
    }
  }
  
  function registerMaskToggleHandler(handler) {
    handlers.value.toggleMask = handler
  }
  
  // Global keyboard event handler
  function handleKeydown(event) {
    // Check if target is inside a CodeMirror editor
    const isInCodeMirror = event.target.closest('.cm-editor')
    
    // Don't trigger shortcuts when typing in inputs, textareas, or CodeMirror
    if (event.target.tagName === 'INPUT' || 
        event.target.tagName === 'TEXTAREA' || 
        event.target.tagName === 'SELECT' ||
        isInCodeMirror) {
      
      // ESC should still work to close modals even when in an input or CodeMirror
      if (event.key === 'Escape') {
        event.preventDefault()
        // Call all registered modal close handlers
        handlers.value.closeModal.forEach(handler => handler())
      }
      return
    }
    
    // '/' to focus search
    if (event.key === '/' && !event.metaKey && !event.ctrlKey) {
      event.preventDefault()
      if (handlers.value.search) {
        handlers.value.search()
      }
    }
    
    // 'ESC' to close modals
    if (event.key === 'Escape') {
      event.preventDefault()
      // Call all registered modal close handlers
      handlers.value.closeModal.forEach(handler => handler())
    }
    
    // 'm' to toggle mask/unmask
    if (event.key === 'm' && !event.metaKey && !event.ctrlKey) {
      event.preventDefault()
      if (handlers.value.toggleMask) {
        handlers.value.toggleMask()
      }
    }
  }
  
  // Set up global listener (only once)
  let isListenerSetup = false
  
  function setupGlobalListener() {
    if (!isListenerSetup) {
      document.addEventListener('keydown', handleKeydown)
      isListenerSetup = true
    }
  }
  
  function teardownGlobalListener() {
    if (isListenerSetup) {
      document.removeEventListener('keydown', handleKeydown)
      isListenerSetup = false
    }
  }
  
  return {
    registerSearchHandler,
    registerModalCloseHandler,
    registerMaskToggleHandler,
    setupGlobalListener,
    teardownGlobalListener
  }
}