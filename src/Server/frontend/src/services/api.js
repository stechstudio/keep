// API client for Keep server
class ApiClient {
  constructor() {
    this.baseUrl = ''
    this.token = window.KEEP_AUTH_TOKEN
  }

  async request(path, options = {}) {
    const response = await fetch(`${this.baseUrl}/api${path}`, {
      ...options,
      headers: {
        'X-Auth-Token': this.token,
        'Content-Type': 'application/json',
        ...options.headers,
      },
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'Request failed' }))
      throw new Error(error.error || `HTTP ${response.status}`)
    }

    return response.json()
  }

  // Generic HTTP methods for flexibility
  async get(path) {
    return this.request(path)
  }

  async post(path, body = {}) {
    return this.request(path, {
      method: 'POST',
      body: JSON.stringify(body)
    })
  }

  async put(path, body = {}) {
    return this.request(path, {
      method: 'PUT', 
      body: JSON.stringify(body)
    })
  }

  async delete(path) {
    return this.request(path, {
      method: 'DELETE'
    })
  }

  // Secrets
  async listSecrets(vault, env, unmask = false) {
    return this.request(`/secrets?vault=${vault}&env=${env}&unmask=${unmask}`)
  }

  async getSecret(key, vault, env, unmask = false) {
    return this.request(`/secrets/${encodeURIComponent(key)}?vault=${vault}&env=${env}&unmask=${unmask}`)
  }

  async createSecret(key, value, vault, env) {
    return this.request('/secrets', {
      method: 'POST',
      body: JSON.stringify({ key, value, vault, env })
    })
  }

  async updateSecret(key, value, vault, env) {
    return this.request(`/secrets/${encodeURIComponent(key)}`, {
      method: 'PUT',
      body: JSON.stringify({ value, vault, env })
    })
  }

  async deleteSecret(key, vault, env) {
    return this.request(`/secrets/${encodeURIComponent(key)}`, {
      method: 'DELETE',
      body: JSON.stringify({ vault, env })
    })
  }

  async renameSecret(oldKey, newKey, vault, env) {
    return this.request(`/secrets/${encodeURIComponent(oldKey)}/rename`, {
      method: 'POST',
      body: JSON.stringify({ newKey, vault, env })
    })
  }

  async copySecretToEnv(key, targetEnv, targetVault, sourceEnv, sourceVault) {
    return this.request(`/secrets/${encodeURIComponent(key)}/copy-to-env?vault=${sourceVault}&env=${sourceEnv}`, {
      method: 'POST',
      body: JSON.stringify({ targetEnv, targetVault })
    })
  }

  async searchSecrets(query, vault, env, unmask = false) {
    return this.request(`/search?q=${encodeURIComponent(query)}&vault=${vault}&env=${env}&unmask=${unmask}`)
  }

  async getSecretHistory(key, vault, env, limit = 10, unmask = false) {
    return this.request(`/secrets/${encodeURIComponent(key)}/history?vault=${vault}&env=${env}&limit=${limit}&unmask=${unmask}`)
  }

  // Settings & Config
  async getSettings() {
    return this.request('/settings')
  }

  async updateSettings(settings) {
    return this.request('/settings', {
      method: 'PUT',
      body: JSON.stringify(settings)
    })
  }

  // Vaults & Envs
  async listVaults() {
    return this.request('/vaults')
  }

  async addVault(vault) {
    return this.request('/vaults', {
      method: 'POST',
      body: JSON.stringify(vault)
    })
  }

  async updateVault(slug, vault) {
    return this.request(`/vaults/${slug}`, {
      method: 'PUT',
      body: JSON.stringify(vault)
    })
  }

  async deleteVault(slug) {
    return this.request(`/vaults/${slug}`, {
      method: 'DELETE'
    })
  }

  async listEnvs() {
    return this.request('/envs')
  }

  async addEnv(env) {
    return this.request('/envs', {
      method: 'POST',
      body: JSON.stringify({ env })
    })
  }

  async removeEnv(env) {
    return this.request('/envs', {
      method: 'DELETE',
      body: JSON.stringify({ env })
    })
  }

  async verifyVaults() {
    return this.request('/verify', { method: 'POST' })
  }

  // Diff & Export
  async getDiff(envs = null, vaults = null) {
    let query = '/diff?'
    if (envs) query += `envs=${envs.join(',')}&`
    if (vaults) query += `vaults=${vaults.join(',')}`
    return this.request(query)
  }

  async exportSecrets(vault, env, format = 'env') {
    return this.request('/export', {
      method: 'POST',
      body: JSON.stringify({ vault, env, format })
    })
  }

  // Import
  async analyzeImport({ content, vault, env, only = null, except = null }) {
    return this.request('/import/analyze', {
      method: 'POST',
      body: JSON.stringify({ content, vault, env, only, except })
    })
  }

  async executeImport({ content, vault, env, strategy, only = null, except = null, dry_run = false }) {
    return this.request('/import/execute', {
      method: 'POST',
      body: JSON.stringify({ content, vault, env, strategy, only, except, dry_run })
    })
  }

  // Workspace
  async getWorkspace() {
    return this.request('/workspace')
  }

  async updateWorkspace(activeVaults, activeEnvs) {
    return this.request('/workspace', {
      method: 'PUT',
      body: JSON.stringify({ 
        active_vaults: activeVaults, 
        active_envs: activeEnvs 
      })
    })
  }

  async verifyWorkspace() {
    return this.request('/workspace/verify', { method: 'POST' })
  }
}

export const api = new ApiClient()