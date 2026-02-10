# HTTPS Audit Report
**Date:** January 20, 2026  
**Status:** HTTPS enabled via mkcert for localhost development  
**Objective:** Ensure HTTPS-safe behavior without forcing redirects in dev

---

## Executive Summary

✅ **Cookie Security:** No cookies currently used - JWT in Authorization headers  
⚠️ **OAuth Redirects:** Hardcoded to `http://` - needs protocol detection  
⚠️ **Callback Redirects:** Frontend redirects hardcoded to `http://` - needs update  
✅ **CORS Headers:** Properly configured, protocol-agnostic  
❌ **Security Headers:** Missing HSTS, CSP for HTTPS environments  
✅ **API Endpoints:** Protocol-agnostic (no hardcoded http/https in PHP)  

---

## Detailed Findings

### 1. Cookie Security ✅

**Status:** NOT APPLICABLE - No cookies used

- **JWT Storage:** Authorization headers (not cookies)
- **Frontend Storage:** `sessionStorage` (auth_storage.js)
- **No cookie attributes needed:** System doesn't use cookies

**Recommendation:** If cookies are added in future:
- Use `Secure` flag for HTTPS
- Use `SameSite=Lax` for CSRF protection
- Use `HttpOnly` for XSS protection

---

### 2. OAuth Configuration ⚠️

**Issue:** Hardcoded `http://localhost` redirect URIs

**Affected Files:**
- [config/database.php](../config/database.php#L58)
  ```php
  define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/api/auth/google/callback.php');
  ```

- [api/auth/google/login.php](../api/auth/google/login.php#L42)
  ```php
  $requiredRedirectUri = 'http://localhost/api/auth/google/callback.php';
  ```

- [api/auth/google/callback.php](../api/auth/google/callback.php#L45)
  ```php
  $requiredRedirectUri = 'http://localhost/api/auth/google/callback.php';
  ```

**Impact:**
- OAuth callbacks will fail over HTTPS unless `.env` manually updated
- Validation logic rejects HTTPS callbacks (hardcoded check)

**Recommendation:**
1. **Auto-detect protocol:**
   ```php
   $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
   $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
   $defaultRedirectUri = "$protocol://$host/api/auth/google/callback.php";
   ```

2. **Update validation:** Remove hardcoded `http://` checks

3. **Google Console:** Add both HTTP and HTTPS redirect URIs:
   - `http://localhost/api/auth/google/callback.php`
   - `https://localhost/api/auth/google/callback.php`

---

### 3. OAuth Callback Frontend Redirects ⚠️

**Issue:** Hardcoded `http://` in post-OAuth redirects

**Affected Files:**
- [api/auth/google/callback.php](../api/auth/google/callback.php#L253)
  ```php
  $baseUrl = 'http://localhost';
  $redirectUrl = $baseUrl . '/course_factory/admin_dashboard.html';
  ```

**Impact:**
- After successful OAuth, user redirected to HTTP (even if came from HTTPS)
- Breaks HTTPS-only environments

**Recommendation:**
```php
// Detect protocol from original request or OAuth state
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = "$protocol://$host";
```

---

### 4. CORS Headers ✅

**Status:** GOOD - Protocol-agnostic

**Implementation:**
- [.htaccess](../.htaccess#L53-L56) - Static CORS headers
- [config/database.php](../config/database.php#L115-L118) - `setCORSHeaders()` function

**Current Configuration:**
```apache
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, Authorization"
```

**Security Note:**
- `Access-Control-Allow-Origin: *` is permissive
- Consider restricting for production:
  ```php
  $allowedOrigins = [
      'https://professorhawkeinstein.com',
      'https://localhost',
      'http://localhost'
  ];
  ```

---

### 5. Security Headers ❌

**Missing for HTTPS:**

1. **HSTS (HTTP Strict Transport Security):**
   ```apache
   # Add to .htaccess for production only
   <IfModule mod_headers.c>
       # Only set HSTS in production over HTTPS
       Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
   </IfModule>
   ```

2. **Content Security Policy:**
   ```apache
   Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-src https://www.youtube.com;"
   ```

3. **Mixed Content Prevention:**
   - Current implementation: ✅ Protocol-relative or dynamic
   - YouTube embeds use `https://` - ✅ Safe

---

### 6. API Endpoint URLs ✅

**Status:** GOOD - No hardcoded protocols in production code

**Test/Documentation Files (HTTP references acceptable):**
- `test_analytics_endpoints.sh` - Test script (dev only)
- `verify_analytics_system.sh` - Test script (dev only)
- Documentation files - Examples only

**Production Code:**
- All API endpoints use relative paths
- No forced protocol redirects
- No hardcoded `http://` or `https://` in PHP logic

---

## Priority Recommendations

### HIGH PRIORITY (HTTPS breaks without these)

1. **Fix OAuth redirect URI detection:**
   - [config/database.php](../config/database.php#L58): Auto-detect protocol
   - [api/auth/google/login.php](../api/auth/google/login.php#L42): Remove hardcoded check
   - [api/auth/google/callback.php](../api/auth/google/callback.php#L45): Remove hardcoded check

2. **Fix post-OAuth redirects:**
   - [api/auth/google/callback.php](../api/auth/google/callback.php#L253): Detect protocol

3. **Update Google Cloud Console:**
   - Add HTTPS redirect URI: `https://localhost/api/auth/google/callback.php`

### MEDIUM PRIORITY (Security hardening)

4. **Add conditional HSTS header:**
   - Only in production
   - Only over HTTPS
   - Don't set in dev (breaks HTTP access)

5. **Add Content Security Policy:**
   - Whitelist YouTube embeds
   - Prevent inline script attacks
   - Start with report-only mode

### LOW PRIORITY (Future improvements)

6. **Restrict CORS origins in production:**
   - Keep `*` for dev
   - Use allowlist for production

7. **Add protocol upgrade hints:**
   - `Upgrade-Insecure-Requests` header
   - Only in production

---

## Implementation Plan

### Phase 1: OAuth Protocol Detection (Required for HTTPS)

**File:** [config/database.php](../config/database.php)

```php
// Auto-detect protocol for OAuth redirect URI
function getOAuthRedirectUri() {
    // Check .env first
    $envUri = getenv('GOOGLE_REDIRECT_URI');
    if ($envUri) {
        return $envUri;
    }
    
    // Auto-detect from current request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host/api/auth/google/callback.php";
}

define('GOOGLE_REDIRECT_URI', getOAuthRedirectUri());
```

**File:** [api/auth/google/login.php](../api/auth/google/login.php)

```php
// Remove hardcoded validation - trust GOOGLE_REDIRECT_URI
// DELETE LINES 42-50 (hardcoded check)
```

**File:** [api/auth/google/callback.php](../api/auth/google/callback.php)

```php
// Remove hardcoded validation - trust GOOGLE_REDIRECT_URI
// DELETE LINES 45-51 (hardcoded check)

// Fix frontend redirects (line 253)
function redirectToFrontendWithSuccess($user, $token) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "$protocol://$host";
    // ... rest of function
}
```

### Phase 2: Security Headers (Production)

**File:** [.htaccess](../.htaccess)

```apache
# Add after existing security headers (line 56)

# HSTS - only in production over HTTPS
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
</IfModule>

# Content Security Policy
Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-src https://www.youtube.com; connect-src 'self' http://localhost:8080 https://localhost:8080;"
```

### Phase 3: Environment-Specific Configuration

**File:** `.env.example`

```bash
# OAuth Configuration
# For HTTPS: Use https://localhost/api/auth/google/callback.php
# For HTTP:  Use http://localhost/api/auth/google/callback.php
# If not set, auto-detects from request protocol
GOOGLE_REDIRECT_URI=https://localhost/api/auth/google/callback.php
```

---

## Testing Checklist

### HTTP (Existing Behavior)
- [ ] OAuth login works: `http://localhost/student_portal/login.html`
- [ ] OAuth callback redirects to HTTP
- [ ] Admin panel loads
- [ ] API calls succeed

### HTTPS (New Behavior)
- [ ] OAuth login works: `https://localhost/student_portal/login.html`
- [ ] OAuth callback redirects to HTTPS
- [ ] Admin panel loads over HTTPS
- [ ] API calls succeed
- [ ] No mixed content warnings

### Mixed (Dev Flexibility)
- [ ] Can access both HTTP and HTTPS simultaneously
- [ ] No forced redirects between protocols
- [ ] `.env` override works correctly

---

## Deployment Notes

**Development (.env):**
```bash
GOOGLE_REDIRECT_URI=https://localhost/api/auth/google/callback.php
# OR leave unset to auto-detect
```

**Production (.env):**
```bash
GOOGLE_REDIRECT_URI=https://professorhawkeinstein.com/api/auth/google/callback.php
```

**Google Cloud Console:**
- Add both HTTP and HTTPS redirect URIs for dev
- Production: HTTPS only

---

## References

- OAuth Implementation: [OAUTH_IMPLEMENTATION_COMPLETE.md](../OAUTH_IMPLEMENTATION_COMPLETE.md)
- Architecture: [ARCHITECTURE.md](../ARCHITECTURE.md)
- Deployment: [DEPLOYMENT_ENVIRONMENT_CONTRACT.md](./DEPLOYMENT_ENVIRONMENT_CONTRACT.md)
- mkcert Setup: Local HTTPS certificate authority

---

## Conclusion

**Current Status:** System is HTTPS-compatible with minor updates needed

**Critical Path:**
1. Update OAuth protocol detection (3 files)
2. Add HTTPS redirect URI to Google Console
3. Test OAuth flow over HTTPS

**Risk Assessment:**
- **Low Risk:** Changes are backward-compatible
- **No Downtime:** HTTP continues to work
- **Rollback:** Revert to hardcoded values if needed

**Timeline:** 1-2 hours implementation + testing
