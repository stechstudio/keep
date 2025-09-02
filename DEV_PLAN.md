# Keep Web UI Development Plan

## Overview

Add a local-only web UI to Keep that provides a rich, visual interface for managing secrets. The UI will be served by PHP's built-in server and communicate with Keep's existing backend classes via a lightweight API layer.

## Architecture

### Directory Structure
```
src/
  Server/
    server.php           # Main router/entry point
    Api/
      Controller.php     # Base API controller
      SecretsController.php
      VaultsController.php
      StagesController.php
      ExportController.php
    Middleware/
      AuthMiddleware.php # Token validation
    public/
      index.html         # Vue SPA entry
      assets/
        app.js          # Bundled Vue app
        app.css         # Bundled styles
```

### Request Flow
1. `keep server` launches `php -S localhost:4000 src/Server/server.php`
2. server.php routes:
   - `/` � serves index.html
   - `/assets/*` � serves static files
   - `/api/*` � handles API requests
3. API controllers use existing Keep classes (no business logic duplication)

## Implementation Phases

### Phase 1: Foundation
- [x] Create `ServerCommand` class extending `BaseCommand`
- [x] Implement server.php router with basic request handling
- [x] Set up CSRF token generation and validation
- [x] Create AuthMiddleware for token validation
- [x] Add port scanning to find available port if 4000 is taken
- [x] Implement graceful shutdown handling (Ctrl+C)
- [x] Auto-open browser on launch
- [x] Add `--port` and `--no-browser` options to command

### Phase 2: API Layer
- [x] Create base JSON response helpers (implemented inline)
- [x] Implement Secrets endpoints:
  - [x] GET `/api/secrets` - List all secrets for vault/stage
  - [x] GET `/api/secrets/{key}` - Get single secret (with unmask option)
  - [x] POST `/api/secrets` - Create/update secret
  - [x] PUT `/api/secrets/{key}` - Update secret
  - [x] DELETE `/api/secrets/{key}` - Delete secret
  - [x] POST `/api/secrets/:key/rename` - Rename secret
  - [x] POST `/api/secrets/:key/copy-to-stage` - Copy to different stage
  - [x] GET `/api/search` - Search in values
  - [x] GET `/api/secrets/:key/history` - Get secret history
- [x] Implement Vault endpoints:
  - [x] GET `/api/vaults` - List configured vaults
  - [ ] GET `/api/vaults/{name}` - Get vault details
  - [x] POST `/api/verify` - Run verification
- [x] Implement Stage endpoints:
  - [x] GET `/api/stages` - List all stages
  - [ ] POST `/api/stages` - Add custom stage
- [x] Implement Diff endpoint:
  - [x] GET `/api/diff` - Get diff matrix across stages/vaults
- [x] Implement Export endpoint:
  - [x] POST `/api/export` - Export to various formats
  - [ ] POST `/api/export/template` - Process template
- [ ] Implement Import endpoint:
  - [ ] POST `/api/import` - Import from uploaded file
- [x] Add error handling and validation to all endpoints

### Phase 3: Frontend Foundation
- [x] Set up Vue 3 build pipeline (vite)
- [x] Create base Vue app structure
- [x] Set up Vue Router for navigation (simple tab navigation)
- [ ] Implement Pinia store for state management (not needed yet)
- [x] Add Tailwind CSS for styling
- [x] Create API client service with:
  - [x] Automatic CSRF token inclusion
  - [x] Error handling
  - [x] Loading states (basic implementation)
- [x] Implement authentication flow (token injection)

### Phase 4: Core UI Components
- [x] Create layout components:
  - [x] AppHeader with vault/stage selector
  - [x] Navigation with pill-style tabs
  - [x] AppFooter with connection status (shows version)
- [x] Create shared components:
  - [x] SecretValue (with mask/unmask toggle)
  - [ ] LoadingSpinner
  - [ ] ErrorAlert
  - [x] SuccessToast (Toast system implemented)
  - [x] ConfirmDialog (DeleteConfirmDialog implemented)
  - [x] HistoryDialog (shows revision history)
  - [x] SecretActionsMenu (reusable dropdown menu)
  - [x] SearchInput with debouncing

### Phase 5: Secret Management Views
- [x] Secrets List View:
  - [x] Table with columns
  - [x] Search/filter functionality
  - [ ] Bulk selection checkboxes
  - [x] Quick actions (copy value, edit, delete)
  - [ ] Pagination for large lists
- [x] Secret Detail View:
  - [x] View/edit secret value
  - [x] Show metadata (created, modified, revision)
  - [x] History timeline (HistoryDialog)
  - [x] Copy to clipboard
- [x] Add/Edit Secret Modal:
  - [x] Key validation
  - [x] Value input (with multiline support)
  - [ ] Encryption toggle
  - [ ] Save with loading state
- [ ] Bulk Operations:
  - [ ] Select all/none
  - [ ] Bulk delete with confirmation
  - [ ] Bulk export selected
  - [ ] Bulk copy to another stage

### Phase 6: Advanced Features
- [x] Diff Matrix View:
  - [x] Visual comparison across stages/vaults
  - [x] Color coding (present/missing/different)
  - [x] Click cell to see value comparison
  - [x] Edit secrets directly from diff view
  - [x] Create missing secrets from diff view
  - [ ] Export diff as CSV
- [ ] Template Builder:
  - [ ] Drag secrets to template
  - [ ] Live preview
  - [ ] Save/load templates
  - [ ] Test template processing
- [x] Export View:
  - [x] Multiple format support (env, json, yaml, shell)
  - [x] Copy to clipboard
  - [x] Download as file
  - [x] Live preview
- [ ] Import Wizard:
  - [ ] File upload or paste
  - [ ] Preview what will be imported
  - [ ] Conflict resolution options
  - [ ] Dry run mode
- [ ] Search & Replace:
  - [ ] Search across all secrets
  - [ ] Replace values with preview
  - [ ] Regex support
  - [ ] Undo capability

### Phase 7: UI Polish
- [x] Toast notifications for all actions
- [ ] Add keyboard shortcuts:
  - [ ] `/` for search focus
  - [ ] `n` for new secret
  - [ ] `e` for edit
  - [ ] `d` for delete
  - [ ] `?` for help
- [x] Dark mode support (default theme)
- [ ] Responsive design for smaller screens
- [ ] Loading skeletons for better UX
- [x] Animations and transitions (toast animations, hover states)
- [ ] Empty states with helpful actions
- [ ] Tooltips for all actions
- [ ] Export/import UI settings

### Phase 8: Security Hardening
- [ ] Implement rate limiting on API endpoints
- [ ] Add request size limits
- [ ] Sanitize all user inputs
- [ ] Add Content Security Policy headers
- [ ] Implement session timeout (configurable)
- [ ] Add audit logging for all actions
- [ ] Clear clipboard after timeout
- [x] Mask values by default everywhere
- [ ] Add "lock screen" feature

### Phase 9: Build & Distribution
- [x] Set up Vite build configuration
- [ ] Minimize and bundle all assets
- [ ] Generate source maps for debugging
- [ ] Create build script in composer.json
- [ ] Ensure built assets are committed
- [ ] Add cache busting for assets
- [ ] Document build process
- [ ] Set up GitHub Action for automated builds

### Phase 10: Testing
- [ ] Unit tests for API controllers
- [ ] Integration tests for API endpoints
- [ ] Vue component tests with Vitest
- [ ] E2E tests with Playwright:
  - [ ] Secret CRUD operations
  - [ ] Diff view functionality
  - [ ] Import/export flows
  - [ ] Error handling
- [ ] Security testing:
  - [ ] CSRF protection
  - [ ] Token validation
  - [ ] Input sanitization
- [ ] Cross-browser testing

### Phase 11: Documentation
- [ ] Add "Web UI" section to main docs
- [ ] Document `keep server` command options
- [ ] Create UI user guide with screenshots
- [ ] Document keyboard shortcuts
- [ ] Add troubleshooting section
- [ ] Create video demo/tutorial
- [ ] Update README with UI feature

## Technical Decisions

### Why Vue 3?
- Lightweight (~100KB)
- Excellent TypeScript support
- Composition API for better code organization
- Built-in reactivity system
- Large ecosystem

### Why Tailwind CSS?
- Utility-first approach perfect for admin UIs
- Small bundle size with PurgeCSS
- Consistent design system
- Dark mode support built-in

### Why Vite?
- Lightning fast HMR for development
- Optimized production builds
- Native ES modules
- Built-in TypeScript support

### Security Approach
1. **Token-based auth**: Generate random token on server start
2. **CSRF protection**: Include token in all API requests
3. **Localhost only**: Bind to 127.0.0.1, not 0.0.0.0
4. **No persistent storage**: Everything in memory during session
5. **Masked values**: Show masked by default, unmask on demand

## API Response Format

All API responses follow this structure:

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message",
  "errors": { ... }  // Only on validation errors
}
```

Error responses:
```json
{
  "success": false,
  "message": "Error description",
  "code": "ERROR_CODE",
  "details": { ... }  // Optional
}
```

## UI Design Principles

1. **Clarity**: Every action should be obvious
2. **Safety**: Destructive actions require confirmation
3. **Speed**: Common tasks should be 1-2 clicks
4. **Feedback**: Every action has immediate visual feedback
5. **Consistency**: Similar actions work the same way everywhere

## Performance Targets

- Initial page load: < 2 seconds
- API response time: < 500ms
- Search results: < 100ms (with debouncing)
- Build size: < 500KB total

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- No IE11 support

## Success Metrics

- [ ] Can manage secrets without using CLI
- [ ] Diff view more intuitive than CLI output
- [ ] Import/export workflows simplified
- [ ] Zero configuration required
- [ ] Works on all major platforms

## Open Questions

1. Should we add WebSocket support for real-time updates?
2. Should we allow configuring which network interface to bind to?
3. Should we add user preferences persistence (localStorage)?
4. Should we support multiple tabs/windows?
5. Should we add CSV export for all data views?

## Future Enhancements (v2)

- WebSocket for real-time collaboration indicators
- Secret rotation scheduling UI
- Backup/restore functionality
- Secret usage analytics
- Integration with AWS CloudTrail viewer
- Plugin system for custom vault drivers
- Mobile-responsive design improvements
- PWA capabilities for "install" option

---

## Completed Features

### ✅ Core Functionality
- Full CRUD operations for secrets
- Rename secrets
- Copy secrets to different stages
- Search functionality with highlighting
- Vault and stage switching
- Diff view for comparing across stages
- Export functionality (env, json, yaml, shell formats)
- Toast notifications for all actions
- Dark theme by default
- Responsive design
- Clean architecture with controllers and router
- Secret history/revision tracking
- Client-side value masking with toggle
- Consistent actions menu across all views

### ✅ UI Components
- SecretsTable with integrated actions menu
- SecretActionsMenu (reusable dropdown component)
- RenameDialog for renaming secrets
- CopyToStageDialog for stage copying
- SecretDialog for add/edit
- DeleteConfirmDialog with custom styling
- HistoryDialog showing revision history
- VaultStageSelector
- Toast notification system
- Masked/unmasked value display
- Logo integration in header

## Next Priority Features

### Quick Wins (1-2 hours each)
1. **Loading Spinner Component** - Show during API calls for better UX
2. **Empty States** - Helpful messages when no secrets/vaults exist
3. **Keyboard Shortcuts** - `/` for search, `n` for new, `e` for edit
4. **Settings View** - Display/edit Keep configuration

### Medium Features (2-4 hours each)
5. **Bulk Operations** - Select multiple secrets for delete/export/copy
6. **Import Wizard** - Upload .env files with preview and conflict resolution
7. **Template Builder** - Visual template creation with drag-and-drop
8. **Pagination** - Handle large secret lists efficiently

### Advanced Features (4+ hours)
9. **Search & Replace** - Find and replace values across all secrets
10. **Audit Log** - Track all changes with user/timestamp
11. **WebSocket Support** - Real-time updates when secrets change
12. **Export Settings** - Save/restore UI preferences

## Recent Accomplishments (Since Last Update)

- ✅ Implemented secret history endpoint and UI dialog
- ✅ Created reusable SecretActionsMenu component (DRY principle)
- ✅ Added custom delete confirmation dialog
- ✅ Fixed edit functionality on Diff page
- ✅ Improved client-side masking consistency
- ✅ Added logo to header with proper Vite asset handling
- ✅ Consolidated duplicate code between Secrets and Diff views

## Current Status

The Web UI is now feature-complete for basic secret management operations. All core CRUD operations work seamlessly, the diff view provides powerful cross-stage comparison, and the export functionality supports multiple formats. The UI is polished with consistent interactions and proper error handling.

**Ready for beta testing and user feedback!**