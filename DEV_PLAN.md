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
  - [x] POST `/api/verify` - Run verification
  - [x] POST `/api/vaults` - Add new vault
  - [x] PUT `/api/vaults/{slug}` - Update vault
  - [x] DELETE `/api/vaults/{slug}` - Delete vault
- [x] Implement Stage endpoints:
  - [x] GET `/api/stages` - List all stages
  - [x] POST `/api/stages` - Add custom stage
  - [x] DELETE `/api/stages` - Remove stage
- [x] Implement Settings endpoints:
  - [x] GET `/api/settings` - Get app settings
  - [x] PUT `/api/settings` - Update settings
- [x] Implement Diff endpoint:
  - [x] GET `/api/diff` - Get diff matrix across stages/vaults
- [x] Implement Export endpoint:
  - [x] POST `/api/export` - Export to various formats
- [x] Add error handling and validation to all endpoints

### Phase 3: Frontend Foundation
- [x] Set up Vue 3 build pipeline (vite)
- [x] Create base Vue app structure
- [x] Set up Vue Router for navigation (simple tab navigation)
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
  - [x] SuccessToast (Toast system implemented)
  - [x] ConfirmDialog (DeleteConfirmationModal implemented)
  - [x] HistoryDialog (shows revision history)
  - [x] SecretActionsMenu (reusable dropdown menu)
  - [x] SearchInput with debouncing
  - [x] DeleteConfirmationModal (custom modal for all deletions)

### Phase 5: Secret Management Views
- [x] Secrets List View:
  - [x] Table with columns
  - [x] Search/filter functionality
  - [x] Quick actions (copy value, edit, delete)
- [x] Secret Detail View:
  - [x] View/edit secret value
  - [x] Show metadata (created, modified, revision)
  - [x] History timeline (HistoryDialog)
  - [x] Copy to clipboard
  - [x] LastModified dates from vault APIs
- [x] Add/Edit Secret Modal:
  - [x] Key validation
  - [x] Value input (with multiline support)

### Phase 6: Advanced Features
- [x] Diff Matrix View:
  - [x] Visual comparison across stages/vaults
  - [x] Color coding (present/missing/different)
  - [x] Click cell to see value comparison
  - [x] Edit secrets directly from diff view
  - [x] Create missing secrets from diff view
- [x] Export View:
  - [x] Multiple format support (env, json, yaml, shell)
  - [x] Copy to clipboard
  - [x] Download as file
  - [x] Live preview

### Phase 7: UI Polish
- [x] Toast notifications for all actions
- [ ] Essential keyboard shortcuts:
  - [ ] `/` for search focus
  - [ ] `ESC` to close modals
  - [ ] `m` to toggle mask/unmask on current page
- [x] Dark mode support (default theme)
- [x] Animations and transitions (toast animations, hover states)

### Phase 8: Build & Distribution
- [x] Set up Vite build configuration
- [x] Minimize and bundle all assets
- [x] Generate source maps for debugging
- [x] Create build script in composer.json
- [x] Ensure built assets are committed
- [x] Add cache busting for assets
- [x] Document build process
- [x] Set up GitHub Action for automated builds

### Phase 9: Testing
- [x] Unit tests for API controllers
- [x] Integration tests for API endpoints
- [x] Vue component tests with Vitest
- [x] Security testing:
  - [x] CSRF protection
  - [x] Token validation
  - [x] Input sanitization

### Phase 10: Documentation
- [x] Add "Web UI" section to main docs
- [x] Document `keep server` command options
- [x] Create UI user guide with screenshots
- [x] Document keyboard shortcuts
- [x] Add troubleshooting section
- [x] Update README with UI feature

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

- [x] Can manage secrets without using CLI
- [x] Diff view more intuitive than CLI output
- [x] Import/export workflows simplified
- [x] Zero configuration required
- [x] Works on all major platforms

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

### Quick Wins
1. **Keyboard Shortcuts** - Essential navigation (/, ESC, m for mask toggle)

### Future Considerations (if needed)
- **Bulk Operations** - Select multiple secrets for batch actions
- **Import Wizard Improvements** - Enhanced conflict resolution
- **Pagination** - Only if performance issues arise with large secret lists

## Recent Accomplishments (Since Last Update)

### Settings & Configuration
- ✅ Complete Settings view with sub-navigation (General, Vaults, Stages)
- ✅ Vault management - Add/Edit/Delete vaults with driver selection
- ✅ Stage management - Add/Remove stages (no more "system stages")
- ✅ General settings - App name, namespace, default vault/stage configuration
- ✅ Vault verification modal showing detailed permissions matrix
- ✅ Smart vault creation with driver-first selection and auto-populated defaults

### UI/UX Improvements  
- ✅ Replaced ALL native browser confirms with custom DeleteConfirmationModal
- ✅ Fixed logo.svg deletion issue with Vite configuration
- ✅ Diff table uses slugs for space efficiency while selectors show friendly names
- ✅ Improved Add Vault modal UX with conditional field display

### Data Enhancements
- ✅ Added lastModified date tracking to Secret class with Carbon
- ✅ Capture LastModifiedDate from AWS SSM API
- ✅ Capture CreatedDate from AWS Secrets Manager API
- ✅ Display modification dates in Secrets table

### Previous Session
- ✅ Implemented secret history endpoint and UI dialog
- ✅ Created reusable SecretActionsMenu component (DRY principle)
- ✅ Fixed edit functionality on Diff page
- ✅ Improved client-side masking consistency
- ✅ Added logo to header with proper Vite asset handling
- ✅ Consolidated duplicate code between Secrets and Diff views

## Current Status

✅ **PRODUCTION READY** - The Web UI is feature-complete for all core functionality:

**Complete Features:**
- Full CRUD operations for secrets with revision tracking
- Advanced diff view for cross-stage/vault comparison
- Complete Settings management (General, Vaults, Stages)
- Export functionality with multiple formats
- Import wizard with drag-and-drop and conflict resolution
- Professional UI with custom modals, toasts, and consistent interactions
- LastModified tracking from vault APIs
- Vault verification with detailed permissions display
- Production build pipeline with optimization and cache busting
- Server-side test coverage
- Full documentation (user guide, troubleshooting, API reference)

**Single Remaining Enhancement:**
- Add keyboard shortcuts (/, ESC, m) for power users

The Web UI provides a powerful, intuitive alternative to CLI operations for managing secrets across environments.

## Template Management Feature

### Navigation Update
- [ ] Add "Templates" tab to main navigation in App.vue
  - [ ] Position after "Compare" tab
  - [ ] Route: `/templates`
  - [ ] Component: `TemplatesView.vue`

## Latest Session Accomplishments (Phase 8-10)

### ✅ Phase 8: Build & Distribution
- Enhanced Vite configuration with terser minification
- Implemented code splitting (vendor, utils, app chunks)
- Added cache busting with content hashes
- Created composer build scripts
- Set up GitHub Actions CI/CD workflow
- Created BUILD.md documentation

### ✅ Phase 9: Testing
- PHP unit tests for all controllers
- Security-focused test suite
- Vue component tests with Vitest
- API service tests with mocked fetch
- Composable tests for shared logic
- Test configuration with coverage reporting

### ✅ Phase 10: Documentation
- Updated README with Web UI section
- Created comprehensive WEB_UI.md guide
- Documented SERVER command with all options
- Created extensive TROUBLESHOOTING.md
- Added security best practices throughout

🎯 **Remaining Tasks:**
1. ✅ Essential keyboard shortcuts (/, ESC, m for mask toggle) - COMPLETED
2. Template Management UI (see detailed plan below)

## Template Management Feature

### Overview
Add a Templates page to the Web UI that scans for .env template files, provides editing capabilities, and allows testing templates against different stages. Templates use the `{vault:key}` placeholder syntax and are merged with actual secret values.

### Phase 1: Backend Infrastructure

#### Settings Enhancement
- [ ] Add `template_path` field to Settings/General page
  - [ ] Default value: `./env` if not specified
  - [ ] Text input with folder browser/validator
  - [ ] Save to `settings.json` configuration

#### Template Service Refactoring
- [ ] Create `src/Services/TemplateService.php` to centralize template logic
  - [ ] Move template processing logic from CLI commands
  - [ ] Methods needed:
    - [ ] `scanTemplates(string $path): array` - Find all .env files
    - [ ] `loadTemplate(string $path): Template` - Load template content
    - [ ] `validateTemplate(Template $template, string $stage): ValidationResult`
    - [ ] `processTemplate(Template $template, string $stage): ProcessedTemplate`
    - [ ] `extractPlaceholders(Template $template): array` - Get all {vault:key} patterns
    - [ ] `saveTemplate(string $path, string $content): void` - Save edited template
    - [ ] `generateTemplate(string $stage, array $vaultFilter = []): string` - Create template from secrets
    - [ ] `normalizeKeyToEnv(string $key): string` - Convert key to ENV format (UPPERCASE_WITH_UNDERSCORES)
- [ ] Create `ValidationResult` data class with:
  - [ ] Valid/invalid placeholders
  - [ ] Missing secrets
  - [ ] Unused secrets  
  - [ ] Line-by-line validation info
- [ ] Create `ProcessedTemplate` data class with:
  - [ ] Original template content
  - [ ] Processed output
  - [ ] Placeholder mappings
  - [ ] Validation results

### Phase 2: API Endpoints

#### Template Controller (`src/Server/Controllers/TemplateController.php`)
- [ ] GET `/api/templates` - List all templates in configured path
  - [ ] Return array of: `{filename, path, stage, size, lastModified}`
  - [ ] Extract stage from filename pattern: `{stage}.env`
  - [ ] Smart stage matching: `prod.env` → `production`, `dev.env` → `development`
- [ ] GET `/api/templates/{filename}` - Get template content
  - [ ] Return: `{content, placeholders, stage}`
  - [ ] Include extracted placeholders with line numbers
- [ ] PUT `/api/templates/{filename}` - Save edited template
  - [ ] Accept: `{content}`
  - [ ] Validate file exists and is writable
  - [ ] Return success/error status
- [ ] POST `/api/templates/generate` - Generate template from existing secrets
  - [ ] Accept: `{stage, vaults?, filename}`
  - [ ] Return: `{content, filename, secretCount}`
  - [ ] Groups secrets by vault with comment headers
  - [ ] Converts keys to ENV format (uppercase with underscores)
  - [ ] Includes sample non-secret variables as comments
- [ ] POST `/api/templates/validate` - Validate template against stage
  - [ ] Accept: `{content, stage, filename?}`
  - [ ] Return: `{valid, errors, warnings, unusedSecrets}`
- [ ] POST `/api/templates/process` - Process template with actual values
  - [ ] Accept: `{content, stage, filename?, strategy}`
  - [ ] Strategy options: `fail`, `blank`, `skip`, `remove`
  - [ ] Return: `{output, validation, placeholders}`
- [ ] GET `/api/templates/placeholders` - Get all available placeholders
  - [ ] Accept: `{stage}`
  - [ ] Return all possible `{vault:key}` combinations for autocomplete

### Phase 3: Frontend Components

#### Templates View (`src/Server/frontend/src/components/TemplatesView.vue`)
- [ ] Main templates list view
  - [ ] Table with columns: File, Stage, Size, Last Modified, Actions
  - [ ] File icons for .env files
  - [ ] Stage badge (auto-detected from filename)
  - [ ] Action buttons: Edit, Test
  - [ ] Header buttons:
    - [ ] "Create Template" - Create from existing secrets
    - [ ] "Upload/Paste Template" - Test arbitrary template
- [ ] Empty state when no templates found
  - [ ] Show configured template path
  - [ ] Instructions for adding templates
  - [ ] "Create First Template" button
  - [ ] Link to settings to change path

#### Template Editor Modal (`src/Server/frontend/src/components/TemplateEditorModal.vue`)
- [ ] Full-screen modal with code editor
- [ ] Features:
  - [ ] Syntax highlighting for .env format
  - [ ] Line numbers
  - [ ] Find/Replace (Ctrl+F, Ctrl+H)
  - [ ] Placeholder highlighting (different color for `{vault:key}` patterns)
  - [ ] Save button with Ctrl+S shortcut
  - [ ] Cancel with confirmation if unsaved changes
- [ ] Editor library options:
  - [ ] CodeMirror 6 (lightweight, good .env support)
  - [ ] Monaco Editor (VSCode editor, more features but larger)
  - [ ] Decision: Start with CodeMirror for simplicity

#### Template Tester Modal (`src/Server/frontend/src/components/TemplateTesterModal.vue`)
- [ ] Split-pane view: Template | Output
- [ ] Left pane (Template):
  - [ ] Read-only code view with line numbers
  - [ ] Placeholder highlighting:
    - [ ] ✅ Green: Valid placeholder with value
    - [ ] ❌ Red: Missing secret
    - [ ] ⚠️ Yellow: Empty value
  - [ ] Hover tooltip showing actual value (masked by default)
- [ ] Right pane (Output):
  - [ ] Processed template with actual values
  - [ ] Copy button for output
  - [ ] Download as file button
  - [ ] Toggle mask/unmask for values
- [ ] Bottom panel:
  - [ ] Validation summary (X valid, Y missing, Z warnings)
  - [ ] List of issues with line numbers
  - [ ] "Create Missing Secrets" button for quick fixes
  - [ ] Missing strategy selector (fail/blank/skip/remove)

#### Create Template Modal (`src/Server/frontend/src/components/CreateTemplateModal.vue`)
- [ ] For creating new template from existing secrets
- [ ] Step 1: Configuration
  - [ ] Stage selector (required)
  - [ ] Filename input (auto-suggest: `{stage}.env`)
  - [ ] "Include all secrets" checkbox (default: true)
  - [ ] Vault filter checkboxes (if multiple vaults)
- [ ] Step 2: Preview & Edit
  - [ ] Generated template preview with:
    - [ ] All secrets converted to ENV format (uppercase, underscores)
    - [ ] Proper placeholders: `DB_PASSWORD={vault-name:db_password}`
    - [ ] Grouped by vault with comment headers
    - [ ] Example non-secret entries as comments
  - [ ] Editable code area for customization
  - [ ] "Add Common Variables" helper:
    - [ ] Quick-add buttons for: APP_ENV, APP_DEBUG, APP_URL, etc.
    - [ ] Insert at cursor position
- [ ] Step 3: Save
  - [ ] Save to configured template path
  - [ ] Option to "Save and Test" immediately

#### Custom Template Modal (`src/Server/frontend/src/components/CustomTemplateModal.vue`)
- [ ] For "Upload/Paste Template" functionality
- [ ] Two tabs: Paste | Upload
- [ ] Paste tab:
  - [ ] Large textarea for template content
  - [ ] Stage selector dropdown
  - [ ] "Test Template" button
- [ ] Upload tab:
  - [ ] Drag-and-drop zone
  - [ ] File input for .env files
  - [ ] Auto-detect stage from filename
  - [ ] Stage selector (override auto-detection)
- [ ] Both lead to Template Tester Modal

### Phase 4: Autocomplete Feature (Stretch Goal)

#### Placeholder Autocomplete
- [ ] Integrate with CodeMirror/Monaco editor
- [ ] Trigger on typing `{` character
- [ ] Show dropdown with available vault:key combinations
- [ ] Filter as user types
- [ ] Group by vault for easier navigation
- [ ] Show current value preview (masked)
- [ ] Cache placeholder list for performance
- [ ] Update cache when switching stages

### Phase 5: Testing

#### Backend Tests
- [ ] TemplateService unit tests
  - [ ] Template scanning with various file patterns
  - [ ] Stage extraction from filenames
  - [ ] Placeholder extraction and validation
  - [ ] Template processing with different strategies
- [ ] TemplateController integration tests
  - [ ] All endpoints with valid/invalid inputs
  - [ ] File permissions handling
  - [ ] Large template handling

#### Frontend Tests
- [ ] Component tests for new Vue components
- [ ] API client tests for template endpoints
- [ ] Mock template data for testing

### Phase 6: Documentation

- [ ] Update WEB_UI.md with Templates section
- [ ] Document template filename conventions
- [ ] Add examples of template usage
- [ ] Document placeholder syntax
- [ ] Add troubleshooting for common template issues

### Technical Decisions

#### Why .env format only?
- Keep already uses .env as primary template format
- Simplifies implementation and testing
- Consistent with existing CLI functionality
- Most common use case for secrets management

#### Why filesystem-based?
- No database required
- Templates often in version control
- Direct file editing maintains compatibility with CLI
- Simpler deployment and backup

#### Stage detection from filename
- Convention: `{stage}.env` or `{stage}.template.env`
- Fallback mappings: `prod` → `production`, `dev` → `development`
- No stage in filename: Allow manual selection in UI

#### Template Generation Algorithm
- Load all secrets for selected stage across all vaults
- Convert keys to ENV format:
  - Replace hyphens with underscores
  - Convert to uppercase
  - Preserve existing underscores
  - Examples: `db-password` → `DB_PASSWORD`, `apiKey` → `APIKEY`, `API_KEY` → `API_KEY`
- Group by vault with comment headers:
  ```env
  # ===== Vault: aws-ssm =====
  DB_HOST={aws-ssm:db-host}
  DB_PASSWORD={aws-ssm:db-password}
  
  # ===== Vault: secrets-manager =====
  API_KEY={secrets-manager:api-key}
  ```
- Add common non-secret variables as commented examples:
  ```env
  # ===== Application Settings (non-secret) =====
  # APP_NAME=MyApp
  # APP_ENV=production
  # APP_DEBUG=false
  # APP_URL=https://example.com
  ```

### Success Criteria

- [ ] Can view all templates in configured directory
- [ ] Can create new template from existing stage secrets
- [ ] Can edit templates with syntax highlighting
- [ ] Can test templates showing validation errors
- [ ] Can see processed output with actual values
- [ ] Template generation properly converts keys to ENV format
- [ ] Generated templates are organized by vault with headers
- [ ] Autocomplete helps discover available secrets (stretch)
- [ ] No code duplication with CLI template commands
- [ ] Performance: Template processing < 500ms

## Shell Enhancements

### Proposed Improvements
Based on user feedback and common usage patterns, these shell enhancements would improve productivity:

#### High Priority (Most Useful)
1. **Stage Shortcuts** - Allow `local`, `staging`, `production` as shortcuts for `stage local`, etc.
   - Implementation: Check if input matches known stage name, auto-expand to `stage <name>`
   - Benefit: Faster context switching, matches user intuition

2. **Temporary Context Override** - Support `get production/API_KEY` syntax
   - Implementation: Parse key for stage/vault prefix, use temporarily without switching context
   - Benefit: Quick checks without losing current context

3. **Pattern Support** - Extend pattern matching to more commands
   - `list DB_*` - Filter list output
   - `delete TEMP_* --force` - Bulk delete by pattern
   - `get API_*` - Show all matching secrets
   - Implementation: Add pattern matching to command arguments
   - Benefit: Batch operations without leaving shell

4. **Multi-line Secret Input** - Support for certificates and keys
   - Implementation: When `set KEY` without value, enter multi-line mode until Ctrl+D
   - Benefit: Essential for SSL certs, private keys, JSON configs

#### Medium Priority (Nice to Have)
5. **Command Chaining** - Support `&&` operator
   - Example: `stage production && list`
   - Implementation: Parse for &&, execute commands sequentially
   - Benefit: Combine related operations

6. **Output Piping** - Allow piping to system commands
   - Example: `list | grep -i database`
   - Implementation: Detect pipe operator, pass output to system
   - Benefit: Leverage existing Unix tools

7. **Quick Repeat** - Support `!!` for last command
   - Implementation: Track last command, expand !! before execution
   - Benefit: Standard shell behavior users expect

#### Low Priority (Consider Later)
8. **Command History Search** - Ctrl+R for reverse search
9. **Shell Aliases** - User-defined shortcuts in config
10. **Output Redirection** - Support `>` and `>>` operators

### Implementation Notes
- Maintain backward compatibility
- Add feature flags for experimental features
- Document clearly what's shell-only vs available in CLI
- Consider security implications of piping/redirection