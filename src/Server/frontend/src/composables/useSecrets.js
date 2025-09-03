import { ref } from 'vue'
import { api } from '../services/api'

export function useSecrets() {
  const secrets = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function loadSecrets(vault, stage, unmask = false) {
    loading.value = true
    error.value = null
    try {
      const response = await api.listSecrets(vault, stage, unmask)
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

  async function createSecret(key, value, vault, stage) {
    loading.value = true
    error.value = null
    try {
      const response = await api.createSecret(key, value, vault, stage)
      await loadSecrets(vault, stage)
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateSecret(key, value, vault, stage) {
    loading.value = true
    error.value = null
    try {
      const response = await api.updateSecret(key, value, vault, stage)
      await loadSecrets(vault, stage)
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteSecret(key, vault, stage) {
    loading.value = true
    error.value = null
    try {
      const response = await api.deleteSecret(key, vault, stage)
      await loadSecrets(vault, stage)
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function renameSecret(oldKey, newKey, vault, stage) {
    loading.value = true
    error.value = null
    try {
      const response = await api.renameSecret(oldKey, newKey, vault, stage)
      await loadSecrets(vault, stage)
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function copySecretToStage(key, targetStage, targetVault, sourceStage, sourceVault) {
    loading.value = true
    error.value = null
    try {
      const response = await api.copySecretToStage(
        key, targetStage, targetVault, sourceStage, sourceVault
      )
      return response
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchSecrets(query, vault, stage, unmask = false) {
    loading.value = true
    error.value = null
    try {
      const response = await api.searchSecrets(query, vault, stage, unmask)
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

  async function getSecretHistory(key, vault, stage, limit = 10, unmask = false) {
    loading.value = true
    error.value = null
    try {
      return await api.getSecretHistory(key, vault, stage, limit, unmask)
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
    copySecretToStage,
    searchSecrets,
    getSecretHistory,
    clearError
  }
}