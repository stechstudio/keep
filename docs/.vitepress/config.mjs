import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Keep',
  description: 'Collaborative, secure management of secrets across applications, environments, and teams.',
  
  // Base URL for GitHub Pages (repository name)
  base: '/keep/',
  
  themeConfig: {
    // GitHub repository
    socialLinks: [
      { icon: 'github', link: 'https://github.com/stechstudio/keep' }
    ],
    
    // Navigation
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Guide', link: '/guide/' },
      { text: 'Reference', link: '/reference/' },
      { text: 'Examples', link: '/examples/' }
    ],
    
    // Sidebar
    sidebar: {
      '/guide/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/guide/' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
            { text: 'Quick Start', link: '/guide/quick-start' }
          ]
        },
        {
          text: 'Core Concepts',
          items: [
            { text: 'Vaults', link: '/guide/vaults' },
            { text: 'Stages', link: '/guide/stages' },
            { text: 'Templates', link: '/guide/templates' }
          ]
        },
        {
          text: 'Vault Drivers',
          items: [
            { text: 'Local Vault', link: '/guide/vaults/local' },
            { text: 'AWS SSM', link: '/guide/vaults/aws-ssm' },
            { text: 'AWS Secrets Manager', link: '/guide/vaults/aws-secrets-manager' }
          ]
        }
      ],
      '/reference/': [
        {
          text: 'CLI Commands',
          items: [
            { text: 'Overview', link: '/reference/' },
            { text: 'configure', link: '/reference/commands/configure' },
            { text: 'vault:add', link: '/reference/commands/vault-add' },
            { text: 'vault:list', link: '/reference/commands/vault-list' },
            { text: 'set', link: '/reference/commands/set' },
            { text: 'get', link: '/reference/commands/get' },
            { text: 'list', link: '/reference/commands/list' },
            { text: 'delete', link: '/reference/commands/delete' },
            { text: 'copy', link: '/reference/commands/copy' },
            { text: 'export', link: '/reference/commands/export' },
            { text: 'import', link: '/reference/commands/import' },
            { text: 'cache', link: '/reference/commands/cache' },
            { text: 'diff', link: '/reference/commands/diff' },
            { text: 'merge', link: '/reference/commands/merge' }
          ]
        }
      ],
      '/examples/': [
        {
          text: 'Examples',
          items: [
            { text: 'Overview', link: '/examples/' },
            { text: 'Laravel Integration', link: '/examples/laravel' },
            { text: 'CI/CD Workflows', link: '/examples/ci-cd' },
            { text: 'Multi-Environment Setup', link: '/examples/multi-environment' },
            { text: 'AWS Setup', link: '/examples/aws-setup' }
          ]
        }
      ]
    },
    
    // Footer
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2025 Signature Technology Studio, Inc'
    },
    
    // Edit link
    editLink: {
      pattern: 'https://github.com/stechstudio/keep/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    },
    
    // Search
    search: {
      provider: 'local'
    }
  }
})