import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { api } from './services/api'

// Make API available globally
window.$api = api

createApp(App)
  .use(router)
  .mount('#app')