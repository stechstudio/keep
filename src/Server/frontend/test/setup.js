import { config } from '@vue/test-utils'

// Mock window.KEEP_AUTH_TOKEN
global.window = {
  ...global.window,
  KEEP_AUTH_TOKEN: 'test-token-123'
}

// Mock $api global
global.window.$api = {
  listSecrets: vi.fn(),
  getSecret: vi.fn(),
  createSecret: vi.fn(),
  updateSecret: vi.fn(),
  deleteSecret: vi.fn(),
  renameSecret: vi.fn(),
  searchSecrets: vi.fn(),
  copySecretToStage: vi.fn(),
  getSecretHistory: vi.fn(),
  listVaults: vi.fn(),
  listStages: vi.fn(),
  getSettings: vi.fn(),
  getDiff: vi.fn(),
  exportSecrets: vi.fn(),
  verifyVaults: vi.fn()
}

// Configure Vue Test Utils
config.global.mocks = {
  $api: global.window.$api
}