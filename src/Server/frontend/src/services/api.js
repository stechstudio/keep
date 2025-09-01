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

  async searchSecrets(query, vault, stage, unmask = false) {
    return this.request(`/search?q=${encodeURIComponent(query)}&vault=${vault}&stage=${stage}&unmask=${unmask}`)
  }

  // Vaults & Stages
  async listVaults() {
    return this.request('/vaults')
  }

  async listStages() {
    return this.request('/stages')
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
}

export const api = new ApiClient()