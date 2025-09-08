import {defineConfig} from 'vitepress'

export default defineConfig({
    title: 'Keep',
    description: 'Collaborative, secure management of secrets across applications, environments, and teams.',

    // Base URL for GitHub Pages (repository name)
    base: '/keep/',
    
    // Ignore dead links for now
    ignoreDeadLinks: true,
    
    // Favicon - must include base path explicitly for head elements
    head: [
        ['link', { rel: 'icon', type: 'image/svg+xml', href: '/keep/logo.svg' }],
        ['link', { rel: 'alternate icon', type: 'image/svg+xml', href: '/keep/logo.svg' }],
        ['link', { rel: 'mask-icon', href: '/keep/logo.svg', color: '#000000' }]
    ],

    themeConfig: {
        // Logo in nav bar
        logo: '/logo.svg',
        // GitHub repository
        socialLinks: [
            {icon: 'github', link: 'https://github.com/stechstudio/keep'}
        ],

        // Navigation
        nav: [
            {text: 'Home', link: '/'},
            {text: 'Guide', link: '/guide/'}
        ],

        // Sidebar
        sidebar: {
            '/guide/': [
                {
                    text: 'Getting Started',
                    items: [
                        {text: 'Introduction', link: '/guide/'},
                        {text: 'Installation', link: '/guide/installation'},
                        {text: 'Configuration', link: '/guide/configuration'},
                        {text: 'AWS Authentication', link: '/guide/aws-authentication'},
                        {text: 'Quick Start', link: '/guide/quick-start'}
                    ]
                },
                {
                    text: 'CLI',
                    items: [
                        {text: 'Overview', link: '/guide/cli/'},
                        {text: 'Command Reference', link: '/guide/cli/reference'}
                    ]
                },
                {
                    text: 'Interactive Shell',
                    items: [
                        {text: 'Overview', link: '/guide/shell/'},
                        {text: 'Command Reference', link: '/guide/shell/reference'}
                    ]
                },
                {
                    text: 'Web UI',
                    items: [
                        {text: 'Getting Started', link: '/guide/web-ui/'},
                        {text: 'Features', link: '/guide/web-ui/features'}
                    ]
                },
                {
                    text: 'Vaults',
                    items: [
                        {text: 'Comparison', link: '/guide/vaults'},
                        {text: 'AWS SSM', link: '/guide/vaults/aws-ssm'},
                        {text: 'AWS Secrets Manager', link: '/guide/vaults/aws-secrets-manager'}
                    ]
                },
                {
                    text: 'Deployment & Runtime',
                    items: [
                        {text: 'Overview', link: '/guide/deployment/'},
                        {text: 'Managing Templates', link: '/guide/deployment/templates'},
                        {text: 'Runtime Secrets Injection', link: '/guide/deployment/runtime-injection'},
                        {text: 'Exporting to Files', link: '/guide/deployment/exporting'}
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