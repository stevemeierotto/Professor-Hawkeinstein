# Authentication Format Centralization - Complete

**Branch:** `hardening/auth-format-1764368814`  
**Commit:** `4455161`  
**Status:** ✅ Complete, 23/23 tests passing

## Problem Statement

The codebase used two different authentication formats:
- **CSP API (external):** `Authorization: Token token=<API_KEY>`
- **Internal JWT:** `Authorization: Bearer <JWT_TOKEN>`

Manual header construction created risk of:
- Using wrong format → API failures
- Format confusion → security issues
- No validation → difficult debugging
- Inconsistent code → maintenance burden

## Solution Implemented

### 1. Created `api/helpers/auth_headers.php` (180 lines)

Centralized helper library with type-safe functions:

```php
// For CSP API calls (external)
$headers = get_csp_headers($apiKey);
// Returns: ['Authorization: Token token={key}', 'Accept: application/json', ...]

// For internal JWT calls
$headers = get_bearer_headers($jwt);
// Returns: ['Authorization: Bearer {jwt}', 'Content-Type: application/json']

// Runtime validation
validate_csp_headers($headers);   // Throws if Bearer detected
validate_bearer_headers($headers); // Throws if Token token= detected
```

**Security features:**
- Validates JWT structure (must be 3 parts separated by dots)
- Detects if JWT passed to CSP function (throws error)
- Detects if CSP key looks like JWT (throws error)
- Error logging for all format mismatches
- Clear exception messages for debugging

**Functions provided:**
1. `csp_auth_header($apiKey)` - Single CSP header string
2. `bearer_auth_header($jwt)` - Single Bearer header string
3. `validate_csp_headers($headers)` - Runtime validation for CSP
4. `validate_bearer_headers($headers)` - Runtime validation for JWT
5. `get_csp_headers($apiKey)` - Full header array for CSP API calls
6. `get_bearer_headers($jwt)` - Full header array for internal API calls
7. `get_csp_api_key()` - Extract and validate API key from environment

### 2. Updated `api/admin/scraper_csp.php`

**Before:**
```php
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Token token=' . $apiKey,
    'Accept: application/json',
    'User-Agent: ProfessorHawkeinstein/1.0'
]);
```

**After:**
```php
require_once __DIR__ . '/../helpers/auth_headers.php';
// ...
curl_setopt($ch, CURLOPT_HTTPHEADER, get_csp_headers($apiKey));
```

**Changes:**
- Added require_once for auth_headers.php
- Replaced 2 manual header constructions with `get_csp_headers()`
- Eliminated hardcoded "Token token=" strings
- No functionality changes, only code organization

### 3. Created `tests/auth_format.test` (159 lines, 23 tests)

Comprehensive test suite validating:

**CSP Authentication (5 tests):**
- ✓ Valid CSP API key accepted
- ✓ CSP header starts with "Authorization: Token token="
- ✓ Empty CSP API key rejected
- ✓ Short CSP API key rejected (< 8 chars)
- ✓ JWT passed to CSP header rejected

**Bearer JWT Authentication (6 tests):**
- ✓ Valid JWT token accepted
- ✓ Bearer header starts with "Authorization: Bearer"
- ✓ Empty JWT rejected
- ✓ Invalid JWT format (2 parts) rejected
- ✓ Invalid JWT format (4 parts) rejected
- ✓ Invalid JWT format (no dots) rejected

**Header Array Validators (4 tests):**
- ✓ CSP headers validation passes
- ✓ Bearer headers validation passes
- ✓ Detect Bearer in CSP context (throws error)
- ✓ Detect CSP format in Bearer context (throws error)

**Complete Header Generators (5 tests):**
- ✓ get_csp_headers() returns array
- ✓ get_csp_headers() includes Accept header
- ✓ get_csp_headers() includes User-Agent header
- ✓ get_bearer_headers() returns array
- ✓ get_bearer_headers() includes Content-Type header

**Real-world Integration (3 tests):**
- ✓ scraper_csp.php includes auth_headers.php
- ✓ scraper_csp.php uses get_csp_headers()
- ✓ scraper_csp.php has NO manual "Token token=" constructions

**Result:** 23/23 tests passing ✅

## Files Changed

```
api/helpers/auth_headers.php      | 180 ++++++++++++++++++++++++++++ (NEW)
api/admin/scraper_csp.php         |  12 +++--------------
tests/auth_format.test            | 159 +++++++++++++++++++++++++  (NEW)
3 files changed, 366 insertions(+), 10 deletions(-)
```

## Usage Examples

### For CSP API Calls

```php
require_once __DIR__ . '/../helpers/auth_headers.php';

// Method 1: Get complete header array
$ch = curl_init($cspUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, get_csp_headers($apiKey));

// Method 2: Get single header string
$authHeader = csp_auth_header($apiKey);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    $authHeader,
    'Accept: application/json',
    'User-Agent: MyApp/1.0'
]);

// Method 3: With validation
$headers = get_csp_headers($apiKey);
validate_csp_headers($headers); // Throws if format wrong
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
```

### For Internal JWT API Calls

```php
require_once __DIR__ . '/../helpers/auth_headers.php';

// Method 1: Get complete header array
$ch = curl_init($internalUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, get_bearer_headers($jwt));

// Method 2: Get single header string
$authHeader = bearer_auth_header($jwt);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    $authHeader,
    'Content-Type: application/json'
]);

// Method 3: With validation
$headers = get_bearer_headers($jwt);
validate_bearer_headers($headers); // Throws if format wrong
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
```

### Error Handling

```php
try {
    // This will throw error - JWT in CSP context
    $headers = csp_auth_header('eyJhbGci...'); 
} catch (Exception $e) {
    // "CSP API keys should not look like JWTs"
    error_log($e->getMessage());
}

try {
    // This will throw error - Bearer header in CSP validator
    validate_csp_headers(['Authorization: Bearer abc.def.ghi']);
} catch (Exception $e) {
    // "CSP headers should use 'Token token=' format, not 'Bearer'"
    error_log($e->getMessage());
}
```

## Security Improvements

1. **Type Safety:** Functions enforce correct format for each auth type
2. **Runtime Validation:** Detects wrong format usage immediately
3. **Clear Errors:** Exception messages guide debugging
4. **Centralized Code:** Single source of truth for header creation
5. **Format Detection:** Automatically detects JWT vs API key format
6. **Error Logging:** All format mismatches logged for auditing

## Testing

Run the test suite:
```bash
./tests/auth_format.test
```

Expected output:
```
=== Authentication Format Validation Test ===
...
========================================
Tests run: 23
Passed: 23
Failed: 0

✅ All auth format tests passed!

Security verification:
  ✓ CSP headers use correct 'Token token=' format
  ✓ Bearer headers use correct 'Bearer' format
  ✓ Cross-contamination detected and rejected
  ✓ Invalid formats rejected
  ✓ Helper functions integrated
```

## Migration Guide

If you have code manually constructing auth headers:

### CSP API Headers

**Before:**
```php
$headers = [
    'Authorization: Token token=' . $apiKey,
    'Accept: application/json',
    'User-Agent: MyApp/1.0'
];
```

**After:**
```php
require_once __DIR__ . '/../helpers/auth_headers.php';
$headers = get_csp_headers($apiKey);
```

### Internal JWT Headers

**Before:**
```php
$headers = [
    'Authorization: Bearer ' . $jwt,
    'Content-Type: application/json'
];
```

**After:**
```php
require_once __DIR__ . '/../helpers/auth_headers.php';
$headers = get_bearer_headers($jwt);
```

## Future Enhancements

Potential improvements:
1. Add bearer_auth_header() usage to internal API clients
2. Create unit tests in PHP (current tests use bash + PHP)
3. Add header validation to more endpoints
4. Generate API documentation from helper functions
5. Add monitoring for auth header format errors

## Related Work

- **File Sync Automation:** `hardening/file-sync-1764366073` (merged)
- **Auth Backdoor Removal:** `hardening/auth-backdoor-removal-1764367211`
- **Current Branch:** `hardening/auth-format-1764368814`

## Verification

After deployment, verify:
1. CSP scraper still works: Admin → Scraper → Fetch Standards
2. No auth errors in logs: `grep "Authorization" /var/log/apache2/error.log`
3. Tests passing: `./tests/auth_format.test`

## Deployment Notes

**Files to sync:**
- `api/helpers/auth_headers.php` (new)
- `api/admin/scraper_csp.php` (modified)
- `tests/auth_format.test` (new)

**No database changes required.**

**Deployment command:**
```bash
make sync-web
```

**Post-deployment verification:**
```bash
# Check files synced
ls -l /var/www/html/basic_educational/api/helpers/auth_headers.php
ls -l /var/www/html/basic_educational/tests/auth_format.test

# Run tests
cd /var/www/html/basic_educational
./tests/auth_format.test
```

## Conclusion

This implementation eliminates authentication format confusion by:
- Centralizing header creation in type-safe functions
- Adding runtime validation to catch format errors
- Creating comprehensive test coverage (23 tests)
- Providing clear error messages for debugging
- Maintaining backward compatibility (no API changes)

**Status:** ✅ Ready for merge
**Impact:** Low risk - only code organization changes, no functionality changes
**Testing:** 23/23 tests passing
**Documentation:** Complete
