# Controller vs Command Duplication Analysis

## Summary
After analyzing all controllers and their corresponding CLI commands, I've identified several areas of code duplication that could be refactored for better code reuse.

## Areas of Duplication Found

### 1. ✅ Vault Permission Testing (HIGH PRIORITY)
**Duplicated in:**
- `VerifyCommand::verifyVaultStage()` - Lines 53-145
- `VaultController::testVaultPermissions()` - Lines 369-422

**Issue:** Both implement nearly identical logic for testing vault permissions (List, Write, Read, History, Delete).

**Recommendation:** Extract to a shared service:
```php
namespace STS\Keep\Services;

class VaultPermissionTester {
    public function testPermissions($vault): array
    public function formatResults(array $results): array
}
```

### 2. ✅ Export Logic (MEDIUM PRIORITY)
**Duplicated in:**
- `ExportCommand` - Uses sophisticated services (DirectExportService, TemplateParseService)
- `ExportController::export()` - Lines 8-28 (basic inline implementation)

**Issue:** ExportController has simplified inline export logic while ExportCommand uses proper service classes.

**Recommendation:** ExportController should use the existing export services:
```php
// ExportController should use DirectExportService
$exportService = new DirectExportService($secretLoader, $outputWriter);
return $exportService->exportToFormat($secrets, $format);
```

### 3. ✅ Secret Key Validation (LOW PRIORITY)
**Duplicated in:**
- `SetCommand::validateUserKey()` - Validates key format
- `SecretController` - No validation (accepts any key)
- Frontend validation in SecretDialog.vue and RenameDialog.vue

**Issue:** Inconsistent validation rules across interfaces.

**Recommendation:** Create a shared validator:
```php
namespace STS\Keep\Services;

class SecretKeyValidator {
    public function validate(string $key): void
    public function getValidationRules(): array
}
```

## Areas Without Duplication (Good!)

### ✅ Import Logic
Both `ImportCommand` and `ImportController` properly use `ImportService` - no duplication!

### ✅ Stage Management  
Only in `VaultController` - no CLI duplication.

### ✅ Vault Management
`VaultAddCommand`, `VaultEditCommand`, and `VaultController` don't have significant duplication since they handle different aspects (interactive CLI vs API).

## Proposed Refactoring Priority

### High Priority
1. **Extract VaultPermissionTester Service**
   - Move permission testing logic from both VerifyCommand and VaultController
   - Both should use the shared service
   - Estimated effort: 2 hours

### Medium Priority  
2. **Unify Export Logic**
   - Make ExportController use existing export services
   - Add CSV format support to ExportController
   - Estimated effort: 1 hour

### Low Priority
3. **Standardize Key Validation**
   - Create SecretKeyValidator service
   - Use in SetCommand, SecretController, and provide rules to frontend
   - Estimated effort: 1 hour

## Benefits of Refactoring
- **Consistency**: Same business logic across CLI and Web UI
- **Maintainability**: Single source of truth for each operation
- **Testing**: Test business logic once in the service
- **Future-proof**: New interfaces (mobile API, etc.) can reuse services

## Code Patterns to Follow
Based on the successful ImportService pattern:
1. Extract business logic to services in `STS\Keep\Services`
2. Commands and Controllers should be thin orchestration layers
3. Services should be interface-agnostic
4. Services should return structured data that can be formatted by the caller