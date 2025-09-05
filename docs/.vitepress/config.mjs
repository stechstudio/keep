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
                        {text: 'Quick Start', link: '/guide/quick-start'}
                    ]
                },
                {
                    text: 'CLI Commands',
                    items: [
                        {text: 'Overview', link: '/guide/cli-commands/'},
                        {text: 'Creating & Viewing', link: '/guide/cli-commands/creating-viewing'},
                        {text: 'Cross-Environment', link: '/guide/cli-commands/cross-environment'},
                        {text: 'Exporting to .env', link: '/guide/cli-commands/exporting-to-env'}
                        // {text: 'Runtime Secrets', link: '/guide/cli-commands/runtime-secrets'} // Deferred to future release
                    ]
                },
                {
                    text: 'Interactive Shell',
                    items: [
                        {text: 'Getting Started', link: '/guide/shell'},
                        {text: 'Commands & Shortcuts', link: '/guide/shell-commands'},
                        {text: 'Tips & Tricks', link: '/guide/shell-tips'}
                    ]
                },
                {
                    text: 'Web UI',
                    items: [
                        {text: 'Getting Started', link: '/guide/web-ui/'},
                        {text: 'Managing Secrets', link: '/guide/web-ui/managing-secrets'},
                        {text: 'Diff & Compare', link: '/guide/web-ui/diff-compare'},
                        {text: 'Import & Export', link: '/guide/web-ui/import-export'},
                        {text: 'Security', link: '/guide/web-ui/security'}
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
                    text: 'Reference',
                    items: [
                        {text: 'CLI Reference', link: '/guide/reference/cli-reference'},
                        {text: 'AWS Authentication', link: '/guide/reference/aws-authentication'}
                        // {text: 'Security Architecture', link: '/guide/reference/security-architecture'} // Deferred - encrypted cache feature
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