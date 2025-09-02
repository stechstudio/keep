import { createRouter, createWebHashHistory } from 'vue-router'
import SecretsTable from './components/SecretsTable.vue'
import DiffView from './components/DiffView.vue'
import SettingsView from './components/SettingsView.vue'

const routes = [
  {
    path: '/',
    redirect: '/secrets'
  },
  {
    path: '/secrets',
    name: 'secrets',
    component: SecretsTable
  },
  {
    path: '/diff',
    name: 'diff',
    component: DiffView
  },
  {
    path: '/settings',
    name: 'settings',
    component: SettingsView
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

export default router