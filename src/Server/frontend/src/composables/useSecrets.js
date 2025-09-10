import { ref } from 'vue'
import { api } from '../services/api'

export function useSecrets() {
  const secrets = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function loadSecrets(vault, env, unmask = false) {
    loading.value = true
    error.value = null
    try {
      const response = await api.listSecrets(vault, env, unmask)
      secrets.value = response.secrets
      return secrets.value
    } catch (err) {
      error.value = err.message
      secrets.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createSecret(key, value, vault, env) {
    loading.value = true
    error.value = null
    try {
      const response = await api.createSecret(key, value, vault, env)
      // Don't call loadSecrets here - let the component handle refresh
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateSecret(key, value, vault, env) {
    loading.value = true
    error.value = null
    try {
      const response = await api.updateSecret(key, value, vault, env)
      // Don't call loadSecrets here - let the component handle refresh
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteSecret(key, vault, env) {
    loading.value = true
    error.value = null
    try {
      const response = await api.deleteSecret(key, vault, env)
      // Don't call loadSecrets here - let the component handle refresh
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function renameSecret(oldKey, newKey, vault, env) {
    loading.value = true
    error.value = null
    try {
      const response = await api.renameSecret(oldKey, newKey, vault, env)
      // Don't call loadSecrets here - let the component handle refresh
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function copySecretToEnv(key, targetEnv, targetVault, sourceEnv, sourceVault) {
    loading.value = true
    error.value = null
    try {
      const response = await api.copySecretToEnv(
        key, targetEnv, targetVault, sourceEnv, sourceVault
      )
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchSecrets(query, vault, env, unmask = false) {
    loading.value = true
    error.value = null
    try {
      const response = await api.searchSecrets(query, vault, env, unmask)
      secrets.value = response.secrets
      return secrets.value
    } catch (err) {
      error.value = err.message
      secrets.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getSecretHistory(key, vault, env, limit = 10, unmask = false) {
    loading.value = true
    error.value = null
    try {
      return await api.getSecretHistory(key, vault, env, limit, unmask)
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  return {
    secrets,
    loading,
    error,
    loadSecrets,
    createSecret,
    updateSecret,
    deleteSecret,
    renameSecret,
    copySecretToEnv,
    searchSecrets,
    getSecretHistory,
    clearError
  }
}