import { createRouter, createWebHashHistory } from 'vue-router'
import SecretsTable from './components/SecretsTable.vue'
import DiffView from './components/DiffView.vue'
import SettingsView from './components/SettingsView.vue'
import TemplatesView from './components/TemplatesView.vue'

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
    path: '/templates',
    name: 'templates',
    component: TemplatesView
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