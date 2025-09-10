import { ref, computed } from 'vue'
import { api } from '../services/api'

export function useVault() {
  const vaults = ref([])
  const envs = ref([])
  const settings = ref({})
  const loading = ref(false)
  const error = ref(null)

  const defaultVault = computed(() => 
    vaults.value.find(v => v.isDefault)?.slug || vaults.value[0]?.slug || 'vault'
  )

  const defaultEnv = computed(() => 
    settings.value.default_env || 'prod'
  )

  async function loadVaults() {
    loading.value = true
    error.value = null
    try {
      const response = await api.listVaults()
      vaults.value = response.vaults
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadEnvs() {
    loading.value = true
    error.value = null
    try {
      const response = await api.listEnvs()
      envs.value = response.envs
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadSettings() {
    loading.value = true
    error.value = null
    try {
      settings.value = await api.getSettings()
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadAll() {
    loading.value = true
    error.value = null
    try {
      const [vaultsRes, envsRes, settingsRes] = await Promise.all([
        api.listVaults(),
        api.listEnvs(),
        api.getSettings()
      ])
      vaults.value = vaultsRes.vaults
      envs.value = envsRes.envs
      settings.value = settingsRes
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function verifyVaults() {
    loading.value = true
    error.value = null
    try {
      return await api.verifyVaults()
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    vaults,
    envs,
    settings,
    loading,
    error,
    defaultVault,
    defaultEnv,
    loadVaults,
    loadEnvs,
    loadSettings,
    loadAll,
    verifyVaults
  }
}