import { ref } from 'vue'

const toasts = ref([])
let nextId = 1

export function useToast() {
  function toast({ title, description, variant = 'default', duration = 3000 }) {
    const id = nextId++
    const newToast = {
      id,
      title,
      description,
      variant,
    }
    
    toasts.value.push(newToast)
    
    if (duration > 0) {
      setTimeout(() => {
        dismiss(id)
      }, duration)
    }
    
    return id
  }
  
  function dismiss(id) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }
  
  function success(title, description) {
    return toast({ title, description, variant: 'success' })
  }
  
  function error(title, description) {
    return toast({ title, description, variant: 'destructive' })
  }
  
  function info(title, description) {
    return toast({ title, description, variant: 'default' })
  }
  
  // Generic showToast function that accepts variant as second parameter
  function showToast(title, variant = 'default', description = '') {
    const variantMap = {
      success: 'success',
      error: 'destructive',
      warning: 'warning',
      info: 'default',
      destructive: 'destructive',
      default: 'default'
    }
    
    return toast({ 
      title, 
      description, 
      variant: variantMap[variant] || 'default' 
    })
  }
  
  return {
    toasts,
    toast,
    dismiss,
    success,
    error,
    info,
    showToast,
  }
}