<template>
  <div ref="editorContainer" class="template-code-editor"></div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter, drawSelection } from '@codemirror/view'
import { EditorState } from '@codemirror/state'
import { StreamLanguage, defaultHighlightStyle, syntaxHighlighting } from '@codemirror/language'
import { autocompletion, completionKeymap, closeBrackets, startCompletion } from '@codemirror/autocomplete'
import { history, historyKeymap } from '@codemirror/commands'
import { searchKeymap } from '@codemirror/search'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: ''
  },
  stage: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const editorContainer = ref(null)
let editorView = null

// Simple .env syntax highlighting
const envLanguage = StreamLanguage.define({
  token(stream, state) {
    // Comments
    if (stream.match(/^\s*#.*/)) {
      return 'comment'
    }
    
    // Placeholders {vault:key}
    if (stream.match(/\{[^}]+\}/)) {
      return 'string'
    }
    
    // Environment variable key
    if (stream.match(/^[A-Z_][A-Z0-9_]*(?==)/)) {
      return 'variableName'
    }
    
    // Equals sign
    if (stream.match(/=/)) {
      return 'operator'
    }
    
    // Values (everything after =)
    if (stream.match(/[^\n]*/)) {
      return 'string'
    }
    
    stream.next()
    return null
  }
})

// Store for vault secrets (populated by parent)
const vaultSecrets = ref([])

// Autocomplete for {vault:key} placeholders
function placeholderCompletions(context) {
  // Match when typing after { or anywhere in a placeholder
  let word = context.matchBefore(/\{[^}]*/)
  
  // If no match, check if we just typed {
  if (!word) {
    word = context.matchBefore(/\{/)
    if (!word) return null
  }
  
  const text = word.text.slice(1) // Remove the leading {
  
  // Build options from all available secrets
  const options = []
  
  // Group secrets by vault for better UX
  const secretsByVault = {}
  for (const secret of vaultSecrets.value) {
    if (!secretsByVault[secret.vault]) {
      secretsByVault[secret.vault] = []
    }
    secretsByVault[secret.vault].push(secret)
  }
  
  // If user hasn't typed a colon yet, we're matching vault:key combinations
  if (!text.includes(':')) {
    // Add all vault:key combinations that match the typed text
    for (const [vault, secrets] of Object.entries(secretsByVault)) {
      for (const secret of secrets) {
        const fullPlaceholder = `{${vault}:${secret.key}}`
        const matchText = `${vault}:${secret.key}`.toLowerCase()
        
        // Match if text is empty or if the vault:key contains the typed text
        if (!text || matchText.includes(text.toLowerCase())) {
          options.push({
            label: `${vault}:${secret.key}`,
            apply: `{${vault}:${secret.key}`,  // Include opening brace, but not closing (CodeMirror adds it)
            type: 'variable',
            detail: secret.description || vault
          })
        }
      }
    }
  } else {
    // User typed vault: so filter by that vault
    const [vaultPart, keyPart = ''] = text.split(':')
    const vaultSecrets = secretsByVault[vaultPart] || []
    
    for (const secret of vaultSecrets) {
      if (!keyPart || secret.key.toLowerCase().includes(keyPart.toLowerCase())) {
        options.push({
          label: secret.key,
          apply: `{${vaultPart}:${secret.key}`,  // Include opening brace since we replace from {
          type: 'variable',
          detail: secret.description || ''
        })
      }
    }
  }
  
  // If no options and no secrets, show a help message
  if (options.length === 0 && vaultSecrets.value.length === 0) {
    options.push(
      { label: 'No secrets loaded', apply: '', type: 'text', detail: 'Loading...' }
    )
  }
  
  return {
    from: word.from,
    options,
    filter: false
  }
}

// Method to update available secrets (called by parent)
function updateSecrets(secrets) {
  vaultSecrets.value = secrets || []
}

// Custom theme matching the app's dark mode
const customTheme = EditorView.theme({
  '&': {
    fontSize: '14px',
    backgroundColor: 'hsl(var(--muted) / 0.3)',
    color: 'hsl(var(--foreground))',
    height: '384px' // h-96 equivalent
  },
  '.cm-content': {
    padding: '12px 16px',
    fontFamily: 'ui-monospace, SFMono-Regular, SF Mono, Consolas, Liberation Mono, Menlo, monospace',
    caretColor: '#ffffff !important'
  },
  '.cm-line': {
    padding: '0',
    lineHeight: '1.5'
  },
  '.cm-activeLine': {
    backgroundColor: 'hsl(var(--muted))' // Reduced opacity for better text readability
  },
  '&.cm-focused': {
    outline: '2px solid hsl(var(--ring))',
    outlineOffset: '0'
  },
  '&.cm-focused .cm-cursor, &.cm-focused .cm-cursor-primary': {
    borderLeftColor: '#ffffff !important'
  },
  '.cm-cursor, .cm-cursor-primary': {
    borderLeftColor: '#ffffff !important',
    borderLeftWidth: '2px !important'
  },
  '.cm-placeholder': {
    color: 'hsl(var(--muted-foreground))',
    fontStyle: 'normal'
  },
  '.cm-gutters': {
    backgroundColor: 'hsl(var(--muted) / 0.5)',
    borderRight: '1px solid hsl(var(--border))',
    color: 'hsl(var(--muted-foreground))'
  },
  '.cm-activeLineGutter': {
    backgroundColor: 'hsl(var(--muted) / 0.7)'
  }
})

// Syntax highlighting styles  
const highlightStyle = EditorView.theme({
  // Comments (lines starting with #)
  '.tok-comment': { 
    color: '#94a3b8',  // slate-400 - Change this for comment color
    fontStyle: 'italic' 
  },
  
  // Environment variable names (KEY in KEY=value)
  '.tok-variableName': { 
    color: '#7dd3fc'   // sky-300 - Change this for variable names
  },
  
  // Placeholders ({vault:key}) and values
  '.tok-string': { 
    color: '#86efac'   // green-300 - Change this for placeholders & values
  },
  
  // Equals sign
  '.tok-operator': { 
    color: 'hsl(var(--muted-foreground))' 
  },
  
  // Additional CodeMirror default token colors
  '.ͼm': { color: '#7b7b7b' }, // Comments: light gray
  '.ͼe': { color: '#fbbf24' }, // Placeholders: yellow
  '.ͼd': { color: '#f87171' }, // red-400
  '.ͼ2': { color: '#fb923c' }, // orange-400
  '.ͼ4': { color: '#fbbf24' }, // amber-400
  '.ͼ6': { color: '#a3e635' }, // lime-400
})

onMounted(() => {
  const startState = EditorState.create({
    doc: props.modelValue,
    extensions: [
      lineNumbers(),
      highlightActiveLine(),
      highlightActiveLineGutter(),
      drawSelection(),
      history(),
      closeBrackets(),
      envLanguage,
      syntaxHighlighting(defaultHighlightStyle),
      customTheme,
      highlightStyle,
      EditorView.lineWrapping,
      EditorView.updateListener.of((update) => {
        if (update.docChanged) {
          const value = update.state.doc.toString()
          emit('update:modelValue', value)
        }
      }),
      autocompletion({
        override: [placeholderCompletions],
        tooltipClass: () => "cm-autocomplete-tooltip"
      }),
      keymap.of([
        ...completionKeymap,
        ...historyKeymap,
        ...searchKeymap,
        {
          key: 'Ctrl-Space',
          run: startCompletion
        }
      ]),
      EditorView.domEventHandlers({
        blur: () => {
          // Emit final value on blur to ensure parent gets update
          if (editorView) {
            emit('update:modelValue', editorView.state.doc.toString())
          }
        },
        keydown: (e) => {
          // Trigger autocomplete on { key
          if (e.key === '{') {
            setTimeout(() => {
              startCompletion(editorView)
            }, 10)
          }
        }
      })
    ]
  })
  
  editorView = new EditorView({
    state: startState,
    parent: editorContainer.value
  })
  
  // Set placeholder if provided
  if (props.placeholder) {
    editorView.contentDOM.setAttribute('data-placeholder', props.placeholder)
  }
})

onUnmounted(() => {
  if (editorView) {
    editorView.destroy()
  }
})

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  if (editorView && newValue !== editorView.state.doc.toString()) {
    editorView.dispatch({
      changes: {
        from: 0,
        to: editorView.state.doc.length,
        insert: newValue
      }
    })
  }
})

// Expose methods for parent component
defineExpose({
  focus() {
    editorView?.focus()
  },
  getSelection() {
    if (!editorView) return { start: 0, end: 0 }
    const { from, to } = editorView.state.selection.main
    return { start: from, end: to }
  },
  setSelection(start, end) {
    if (!editorView) return
    editorView.dispatch({
      selection: { anchor: start, head: end }
    })
  },
  insertText(text) {
    if (!editorView) return
    const { from, to } = editorView.state.selection.main
    editorView.dispatch({
      changes: { from, to, insert: text }
    })
  },
  updateSecrets
})
</script>

<style scoped>
.template-code-editor {
  border: 1px solid hsl(var(--border));
  border-radius: 0.375rem;
  overflow: visible;
  position: relative;
}

.template-code-editor :deep(.cm-editor) {
  border-radius: 0.375rem;
}

.template-code-editor :deep(.cm-editor.cm-focused) {
  border-color: transparent;
}

.template-code-editor :deep(.cm-scroller) {
  font-family: ui-monospace, SFMono-Regular, SF Mono, Consolas, Liberation Mono, Menlo, monospace;
  overflow-x: auto;
  overflow-y: auto;
  min-height: 384px;
}

/* Keep tooltips positioned relative to cursor */
.template-code-editor :deep(.cm-tooltip) {
  position: absolute !important;
}

/* Ensure autocomplete dropdown is visible */
.template-code-editor :deep(.cm-tooltip-autocomplete) {
  z-index: 9999 !important;
  background: hsl(var(--background)) !important;
  border: 1px solid hsl(var(--border)) !important;
  border-radius: 0.375rem !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
  max-height: min(300px, 40vh) !important;
  overflow: visible !important;
}

.template-code-editor :deep(.cm-tooltip-autocomplete ul) {
  font-family: ui-monospace, SFMono-Regular, SF Mono, Consolas, Liberation Mono, Menlo, monospace;
  max-height: min(280px, 38vh) !important;
  font-size: 14px;
  overflow-y: auto !important;
  overflow-x: hidden !important;
}

.template-code-editor :deep(.cm-tooltip-autocomplete ul li) {
  padding: 4px 8px;
}

.template-code-editor :deep(.cm-tooltip-autocomplete ul li[aria-selected]) {
  background: hsl(var(--primary)) !important;
  color: hsl(var(--primary-foreground)) !important;
}

/* Force cursor to be white */
.template-code-editor :deep(.cm-cursor),
.template-code-editor :deep(.cm-cursor-primary),
.template-code-editor :deep(.cm-editor.cm-focused .cm-cursor),
.template-code-editor :deep(.cm-editor.cm-focused .cm-cursor-primary),
.template-code-editor :deep(.cm-content .cm-cursor),
.template-code-editor :deep(.cm-content .cm-cursor-primary) {
  border-left-color: #ffffff !important;
  border-left-width: 2px !important;
  border-left-style: solid !important;
}

/* Set caret color for the content area */
.template-code-editor :deep(.cm-content),
.template-code-editor :deep(.cm-line) {
  caret-color: #ffffff !important;
}

/* Also handle the drop cursor */
.template-code-editor :deep(.cm-dropCursor) {
  border-left-color: #ffffff !important;
}
</style>