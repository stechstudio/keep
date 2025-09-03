import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useVault } from '@/composables/useVault'
import * as api from '@/services/api'

vi.mock('@/services/api', () => ({
  api: {
    listVaults: vi.fn(),
    listStages: vi.fn(),
    getSettings: vi.fn(),
    verifyVaults: vi.fn()
  }
}))

describe('useVault', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('initializes with empty state', () => {
    const { vaults, stages, settings, loading, error } = useVault()
    
    expect(vaults.value).toEqual([])
    expect(stages.value).toEqual([])
    expect(settings.value).toEqual({})
    expect(loading.value).toBe(false)
    expect(error.value).toBe(null)
  })

  it('loads vaults successfully', async () => {
    const mockVaults = [
      { slug: 'aws', name: 'AWS Vault', isDefault: true },
      { slug: 'local', name: 'Local Vault', isDefault: false }
    ]
    
    api.api.listVaults.mockResolvedValue({ vaults: mockVaults })
    
    const { vaults, loading, loadVaults } = useVault()
    
    const promise = loadVaults()
    expect(loading.value).toBe(true)
    
    await promise
    
    expect(vaults.value).toEqual(mockVaults)
    expect(loading.value).toBe(false)
    expect(api.api.listVaults).toHaveBeenCalledTimes(1)
  })

  it('handles vault loading errors', async () => {
    const errorMessage = 'Network error'
    api.api.listVaults.mockRejectedValue(new Error(errorMessage))
    
    const { vaults, error, loadVaults } = useVault()
    
    await expect(loadVaults()).rejects.toThrow(errorMessage)
    
    expect(vaults.value).toEqual([])
    expect(error.value).toBe(errorMessage)
  })

  it('loads all data in parallel', async () => {
    const mockVaults = [{ slug: 'aws', name: 'AWS' }]
    const mockStages = ['local', 'prod']
    const mockSettings = { app_name: 'Test App' }
    
    api.api.listVaults.mockResolvedValue({ vaults: mockVaults })
    api.api.listStages.mockResolvedValue({ stages: mockStages })
    api.api.getSettings.mockResolvedValue(mockSettings)
    
    const { vaults, stages, settings, loadAll } = useVault()
    
    await loadAll()
    
    expect(vaults.value).toEqual(mockVaults)
    expect(stages.value).toEqual(mockStages)
    expect(settings.value).toEqual(mockSettings)
    
    // Verify all calls were made
    expect(api.api.listVaults).toHaveBeenCalledTimes(1)
    expect(api.api.listStages).toHaveBeenCalledTimes(1)
    expect(api.api.getSettings).toHaveBeenCalledTimes(1)
  })

  it('computes default vault correctly', () => {
    const { vaults, defaultVault } = useVault()
    
    // No vaults
    expect(defaultVault.value).toBe('vault')
    
    // With vaults, one default
    vaults.value = [
      { slug: 'aws', isDefault: true },
      { slug: 'local', isDefault: false }
    ]
    expect(defaultVault.value).toBe('aws')
    
    // No default marked, use first
    vaults.value = [
      { slug: 'first', isDefault: false },
      { slug: 'second', isDefault: false }
    ]
    expect(defaultVault.value).toBe('first')
  })

  it('verifies vaults and returns results', async () => {
    const mockResults = {
      results: {
        aws: { success: true, permissions: { Read: true, Write: true } },
        local: { success: false, error: 'Access denied' }
      }
    }
    
    api.api.verifyVaults.mockResolvedValue(mockResults)
    
    const { verifyVaults } = useVault()
    const results = await verifyVaults()
    
    expect(results).toEqual(mockResults)
    expect(api.api.verifyVaults).toHaveBeenCalledTimes(1)
  })
})