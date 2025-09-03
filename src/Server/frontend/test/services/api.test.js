import { describe, it, expect, vi, beforeEach } from 'vitest'
import { api } from '@/services/api'

// Mock fetch globally
global.fetch = vi.fn()
global.window.KEEP_AUTH_TOKEN = 'test-token'

describe('ApiClient', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetch.mockClear()
  })

  it('includes auth token in all requests', async () => {
    fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ success: true })
    })
    
    await api.listSecrets('vault', 'stage')
    
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/secrets'),
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Auth-Token': 'test-token',
          'Content-Type': 'application/json'
        })
      })
    )
  })

  it('handles successful responses', async () => {
    const mockData = { secrets: [{ key: 'SECRET1' }] }
    fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => mockData
    })
    
    const result = await api.listSecrets('vault', 'stage')
    
    expect(result).toEqual(mockData)
  })

  it('throws error for failed responses', async () => {
    fetch.mockResolvedValueOnce({
      ok: false,
      status: 404,
      json: async () => ({ error: 'Not found' })
    })
    
    await expect(api.getSecret('missing', 'vault', 'stage'))
      .rejects.toThrow('Not found')
  })

  it('handles network errors', async () => {
    fetch.mockRejectedValueOnce(new Error('Network error'))
    
    await expect(api.listVaults())
      .rejects.toThrow('Network error')
  })

  describe('Secret operations', () => {
    it('lists secrets with unmask parameter', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ secrets: [] })
      })
      
      await api.listSecrets('vault1', 'prod', true)
      
      expect(fetch).toHaveBeenCalledWith(
        '/api/secrets?vault=vault1&stage=prod&unmask=true',
        expect.any(Object)
      )
    })

    it('creates secret with POST', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true })
      })
      
      await api.createSecret('KEY', 'value', 'vault', 'stage')
      
      expect(fetch).toHaveBeenCalledWith(
        '/api/secrets',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({
            key: 'KEY',
            value: 'value',
            vault: 'vault',
            stage: 'stage'
          })
        })
      )
    })

    it('deletes secret with DELETE method', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true })
      })
      
      await api.deleteSecret('KEY', 'vault', 'stage')
      
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/secrets/KEY'),
        expect.objectContaining({
          method: 'DELETE',
          body: JSON.stringify({ vault: 'vault', stage: 'stage' })
        })
      )
    })

    it('encodes special characters in keys', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true })
      })
      
      await api.getSecret('KEY/WITH/SLASHES', 'vault', 'stage')
      
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/secrets/KEY%2FWITH%2FSLASHES'),
        expect.any(Object)
      )
    })
  })

  describe('Vault operations', () => {
    it('lists vaults', async () => {
      const mockVaults = [{ slug: 'aws' }]
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ vaults: mockVaults })
      })
      
      const result = await api.listVaults()
      
      expect(result.vaults).toEqual(mockVaults)
      expect(fetch).toHaveBeenCalledWith('/api/vaults', expect.any(Object))
    })

    it('verifies vaults with POST', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ results: {} })
      })
      
      await api.verifyVaults()
      
      expect(fetch).toHaveBeenCalledWith(
        '/api/verify',
        expect.objectContaining({ method: 'POST' })
      )
    })
  })

  describe('Export operations', () => {
    it('exports secrets with format parameter', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ content: 'exported' })
      })
      
      await api.exportSecrets('vault', 'stage', 'json')
      
      expect(fetch).toHaveBeenCalledWith(
        '/api/export',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({
            vault: 'vault',
            stage: 'stage',
            format: 'json'
          })
        })
      )
    })
  })

  describe('Import operations', () => {
    it('analyzes import with filters', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ analysis: {} })
      })
      
      await api.analyzeImport({
        content: 'KEY=value',
        vault: 'vault',
        stage: 'stage',
        only: 'KEY*',
        except: 'SECRET*'
      })
      
      expect(fetch).toHaveBeenCalledWith(
        '/api/import/analyze',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({
            content: 'KEY=value',
            vault: 'vault',
            stage: 'stage',
            only: 'KEY*',
            except: 'SECRET*'
          })
        })
      )
    })

    it('executes import with strategy', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ imported: 5 })
      })
      
      await api.executeImport({
        content: 'KEY=value',
        vault: 'vault',
        stage: 'stage',
        strategy: 'overwrite',
        dry_run: true
      })
      
      expect(fetch).toHaveBeenCalledWith(
        '/api/import/execute',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"strategy":"overwrite"')
        })
      )
    })
  })
})