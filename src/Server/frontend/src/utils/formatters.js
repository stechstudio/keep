export function formatDate(dateString) {
  if (!dateString) return 'Never'
  
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffSecs = Math.floor(diffMs / 1000)
  const diffMins = Math.floor(diffSecs / 60)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)
  
  // Show relative time for recent changes
  if (diffSecs < 60) return 'Just now'
  if (diffMins < 60) return `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`
  if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`
  if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
  
  // For older dates, show the actual date with local timezone indicator
  const options = {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  }
  
  return date.toLocaleString(undefined, options)
}

/**
 * Mask a sensitive value for display
 * Matches PHP MasksValues trait behavior
 * 
 * @param {string|null} value - The value to mask
 * @param {string} maskChar - Single character to use for masking (defaults to bullet)
 * @returns {string} The masked value
 */
export function maskValue(value, maskChar = '•') {
  if (!value) return ''
  
  const str = String(value)
  const length = str.length
  
  // Ensure maskChar is a single character
  const char = (maskChar && maskChar.length === 1) ? maskChar : '•'
  
  // Short values always get generic mask (matching PHP MasksValues trait)
  if (length <= 8) {
    return char.repeat(4)
  }
  
  // Show first 4 characters plus mask chars for remaining length
  const masked = str.substring(0, 4) + char.repeat(length - 4)
  
  // Truncate long values (matching PHP MasksValues trait)
  if (length <= 24) {
    return masked
  }
  
  return masked.substring(0, 24) + ` (${length} chars)`
}

export function formatBytes(bytes) {
  if (bytes === 0) return '0 Bytes'
  
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}