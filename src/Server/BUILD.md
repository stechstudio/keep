# Keep Web UI Build Guide

## Quick Start

From the project root:
```bash
composer build
```

This installs dependencies and builds production assets.

## Build Commands

### From Project Root (Composer)
```bash
composer ui:install    # Install npm dependencies
composer ui:build      # Build production assets
composer ui:dev        # Start dev server with HMR
composer ui:clean      # Clean build artifacts
composer build         # Full build (install + build)
```

### From Frontend Directory (NPM)
```bash
cd src/Server/frontend
npm install            # Install dependencies
npm run build          # Production build
npm run build:watch    # Watch mode for development
npm run dev            # Dev server with HMR
npm run clean          # Remove build artifacts
```

## Build Configuration

### Production Optimizations
- **Minification**: JavaScript and CSS are minified with Terser
- **Tree Shaking**: Unused code is eliminated
- **Code Splitting**: Vendor and utility code split into separate chunks
- **Cache Busting**: Asset filenames include content hashes
- **Source Maps**: Generated for debugging production issues
- **Asset Inlining**: Small assets (<4KB) are inlined

### Output Structure
```
src/Server/public/
├── index.html                  # Entry point
├── assets/
│   ├── app.[hash].js          # Main application
│   ├── vendor.[hash].js       # Vue and dependencies  
│   ├── utils.[hash].js        # Shared utilities
│   ├── app.[hash].css         # Compiled styles
│   ├── *.map                  # Source maps
│   └── logo.svg               # Static assets
```

## Development Workflow

1. **Start the dev server**:
   ```bash
   composer ui:dev
   ```
   This starts Vite with hot module replacement on port 5173.

2. **Make changes** to Vue components or styles

3. **Build for testing**:
   ```bash
   composer ui:build
   ```

4. **Test with Keep server**:
   ```bash
   bin/keep server
   ```

## CI/CD

GitHub Actions automatically builds the UI when:
- Changes are pushed to main/develop branches
- Pull requests modify frontend files

The workflow:
1. Installs Node.js dependencies
2. Runs production build
3. Uploads artifacts for review
4. Commits built assets to main branch

## Troubleshooting

### Build Fails
```bash
# Clean and rebuild
composer ui:clean
composer build
```

### Port Conflicts
The dev server uses port 5173. If occupied:
```bash
npm run dev -- --port 3000
```

### Large Bundle Size
Check bundle analysis:
```bash
cd src/Server/frontend
npm run build:analyze
```

## Performance Targets

- Initial bundle: < 300KB gzipped
- Vendor chunk: < 150KB gzipped  
- CSS: < 50KB gzipped
- First paint: < 2s on 3G
- Build time: < 30s