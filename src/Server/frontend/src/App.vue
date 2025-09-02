<template>
  <div class="min-h-screen bg-background text-foreground">
    <!-- Toast Notifications -->
    <ToastContainer />
    
    <!-- Navigation Bar -->
    <nav class="border-b border-border">
      <div class="max-w-full px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo & Title -->
          <div class="flex items-center w-1/3">
<!--            <svg class="w-8 h-8 mr-3 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">-->
<!--              <rect x="3" y="11" width="18" height="10" rx="2" ry="2"/>-->
<!--              <path d="M7 11V7a5 5 0 0110 0v4"/>-->
<!--            </svg>-->
            <span class="text-xl font-semibold opacity-75">{{ appName }} Secrets</span>
          </div>

          <!-- Pill Navigation -->
          <div class="flex items-center space-x-2 bg-muted rounded-full p-1">
            <router-link
              v-for="tab in tabs"
              :key="tab.id"
              :to="tab.path"
              v-slot="{ isActive }"
              custom
            >
              <button
                @click="$router.push(tab.path)"
                :class="[
                  'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
                ]"
              >
                {{ tab.label }}
              </button>
            </router-link>
          </div>
          <div class="w-1/3 text-right">
            <a href="https://github.com/stechstudio/keep" target="_blank" class="text-white/50 hover:text-white">Keep v{{ keepVersion }}</a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-full px-4 sm:px-6 lg:px-8 py-6">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import ToastContainer from './components/ToastContainer.vue'

const tabs = [
  { id: 'secrets', label: 'Secrets', path: '/secrets' },
  { id: 'diff', label: 'Compare', path: '/diff' },
  { id: 'settings', label: 'Settings', path: '/settings' }
]

const appName = ref('Keep')
const keepVersion = ref('')

onMounted(async () => {
  await loadSettings()
})

async function loadSettings() {
  try {
    const settings = await window.$api.getSettings()
    appName.value = settings.app_name || 'Keep'
    keepVersion.value = settings.keep_version || ''
  } catch (error) {
    console.error('Failed to load settings:', error)
  }
}
</script>