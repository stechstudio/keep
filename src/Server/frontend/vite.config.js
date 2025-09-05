import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: '../public',
    emptyOutDir: {
      allowOutsideOutDir: false,
      exclude: ['assets/logo.svg']
    },
    // Enable source maps for debugging
    sourcemap: true,
    // Minification settings
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: false,
        drop_debugger: false
      }
    },
    // Chunk size warnings
    chunkSizeWarningLimit: 500,
    rollupOptions: {
      output: {
        // Add hash for cache busting
        entryFileNames: 'assets/app.[hash].js',
        chunkFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash].[ext]',
        // Manual chunks for better caching
        manualChunks: {
          'vendor': ['vue', 'vue-router'],
          'utils': ['./src/utils/formatters.js', './src/composables/useToast.js']
        }
      }
    },
    // Report compressed size
    reportCompressedSize: true,
    // Asserts inlining threshold in bytes
    assetsInlineLimit: 4096
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    }
  }
})