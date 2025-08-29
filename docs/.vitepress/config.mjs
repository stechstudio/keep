import {defineConfig} from 'vitepress'

export default defineConfig({
    title: 'Keep',
    description: 'Collaborative, secure management of secrets across applications, environments, and teams.',

    // Base URL for GitHub Pages (repository name)
    base: '/keep/',

    themeConfig: {
        // GitHub repository
        socialLinks: [
            {icon: 'github', link: 'https://github.com/stechstudio/keep'}
        ],

        // Navigation
        nav: [
            {text: 'Home', link: '/'},
            {text: 'Guide', link: '/guide/'},
            {text: 'Examples', link: '/examples/'}
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
                    text: 'Managing Secrets',
                    items: [
                        {text: 'Overview', link: '/guide/managing-secrets/'},
                        {text: 'Creating & Viewing', link: '/guide/managing-secrets/creating-viewing'},
                        {text: 'Cross-Environment', link: '/guide/managing-secrets/cross-environment'},
                        {text: 'Exporting to .env', link: '/guide/managing-secrets/exporting-to-env'}
                        // {text: 'Runtime Secrets', link: '/guide/managing-secrets/runtime-secrets'} // Deferred to future release
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
                        {text: 'AWS Authentication', link: '/guide/reference/aws-authentication'},
                        {text: 'Security Architecture', link: '/guide/reference/security-architecture'}
                    ]
                }
            ],
            '/examples/': [
                {
                    text: 'Examples',
                    items: [
                        {text: 'Overview', link: '/examples/'},
                        // {text: 'Laravel Integration', link: '/examples/laravel'}, // Deferred to future release
                        {text: 'CI/CD Workflows', link: '/examples/ci-cd'},
                        {text: 'Multi-Environment Setup', link: '/examples/multi-environment'},
                        {text: 'AWS Setup', link: '/examples/aws-setup'}
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