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
  async listSecrets(vault, stage, unmask = false) {
    return this.request(`/secrets?vault=${vault}&stage=${stage}&unmask=${unmask}`)
  }

  async getSecret(key, vault, stage, unmask = false) {
    return this.request(`/secrets/${encodeURIComponent(key)}?vault=${vault}&stage=${stage}&unmask=${unmask}`)
  }

  async createSecret(key, value, vault, stage) {
    return this.request('/secrets', {
      method: 'POST',
      body: JSON.stringify({ key, value, vault, stage })
    })
  }

  async updateSecret(key, value, vault, stage) {
    return this.request(`/secrets/${encodeURIComponent(key)}`, {
      method: 'PUT',
      body: JSON.stringify({ value, vault, stage })
    })
  }

  async deleteSecret(key, vault, stage) {
    return this.request(`/secrets/${encodeURIComponent(key)}`, {
      method: 'DELETE',
      body: JSON.stringify({ vault, stage })
    })
  }

  async renameSecret(oldKey, newKey, vault, stage) {
    return this.request(`/secrets/${encodeURIComponent(oldKey)}/rename`, {
      method: 'POST',
      body: JSON.stringify({ newKey, vault, stage })
    })
  }

  async copySecretToStage(key, targetStage, targetVault, sourceStage, sourceVault) {
    return this.request(`/secrets/${encodeURIComponent(key)}/copy-to-stage?vault=${sourceVault}&stage=${sourceStage}`, {
      method: 'POST',
      body: JSON.stringify({ targetStage, targetVault })
    })
  }

  async searchSecrets(query, vault, stage, unmask = false) {
    return this.request(`/search?q=${encodeURIComponent(query)}&vault=${vault}&stage=${stage}&unmask=${unmask}`)
  }

  async getSecretHistory(key, vault, stage, limit = 10, unmask = false) {
    return this.request(`/secrets/${encodeURIComponent(key)}/history?vault=${vault}&stage=${stage}&limit=${limit}&unmask=${unmask}`)
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

  // Vaults & Stages
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

  async listStages() {
    return this.request('/stages')
  }

  async addStage(stage) {
    return this.request('/stages', {
      method: 'POST',
      body: JSON.stringify({ stage })
    })
  }

  async removeStage(stage) {
    return this.request('/stages', {
      method: 'DELETE',
      body: JSON.stringify({ stage })
    })
  }

  async verifyVaults() {
    return this.request('/verify', { method: 'POST' })
  }

  // Diff & Export
  async getDiff(stages = null, vaults = null) {
    let query = '/diff?'
    if (stages) query += `stages=${stages.join(',')}&`
    if (vaults) query += `vaults=${vaults.join(',')}`
    return this.request(query)
  }

  async exportSecrets(vault, stage, format = 'env') {
    return this.request('/export', {
      method: 'POST',
      body: JSON.stringify({ vault, stage, format })
    })
  }

  // Import
  async analyzeImport({ content, vault, stage, only = null, except = null }) {
    return this.request('/import/analyze', {
      method: 'POST',
      body: JSON.stringify({ content, vault, stage, only, except })
    })
  }

  async executeImport({ content, vault, stage, strategy, only = null, except = null, dry_run = false }) {
    return this.request('/import/execute', {
      method: 'POST',
      body: JSON.stringify({ content, vault, stage, strategy, only, except, dry_run })
    })
  }

  // Workspace
  async getWorkspace() {
    return this.request('/workspace')
  }

  async updateWorkspace(activeVaults, activeStages) {
    return this.request('/workspace', {
      method: 'PUT',
      body: JSON.stringify({ 
        active_vaults: activeVaults, 
        active_stages: activeStages 
      })
    })
  }

  async verifyWorkspace() {
    return this.request('/workspace/verify', { method: 'POST' })
  }
}

export const api = new ApiClient()