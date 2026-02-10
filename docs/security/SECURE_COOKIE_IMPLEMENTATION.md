# Secure Cookie Implementation
**Date:** January 20, 2026  
**Status:** ✅ IMPLEMENTED  
**Objective:** Secure JWT tokens in httpOnly cookies with HTTPS support

---

## Overview

Migrated authentication from client-side sessionStorage to secure server-side httpOnly cookies for better security with HTTPS.

### Security Benefits

1. **httpOnly flag:** Prevents JavaScript access → XSS protection
2. **Secure flag:** Only transmitted over HTTPS (auto-detected for dev/prod)
3. **SameSite=Lax:** CSRF protection while allowing OAuth redirects
4. **Automatic protocol detection:** Works with both HTTP (dev) and HTTPS (prod/mkcert)

---

## Implementation Details

### Cookie Configuration

All authentication endpoints now set cookies with:

```php
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('auth_token', $token, [
    'expires' => time() + SESSION_LIFETIME,  // 8 hours
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,   // Auto-detect HTTPS
    'httponly' => true,      // No JS access
    'samesite' => 'Lax'      // CSRF protection
]);
```

**Protocol Detection:**
- **HTTPS (mkcert/production):** `secure=true` - cookie only sent over HTTPS
- **HTTP (development):** `secure=false` - cookie works over HTTP for testing

---

## Modified Files

### Core Authentication

**[api/auth/login.php](../api/auth/login.php)**
- ✅ Sets secure cookie after successful login
- ✅ Returns token in JSON response (backward compatibility)
- ✅ Logs cookie security status

**[api/auth/logout.php](../api/auth/logout.php)**
- ✅ Clears cookie by setting expiration in past
- ✅ Uses same cookie parameters for proper clearing

**[course_factory/api/auth/logout.php](../course_factory/api/auth/logout.php)**
- ✅ Matches main logout implementation

### OAuth Endpoints

**[api/auth/google/callback.php](../api/auth/google/callback.php)**
- ✅ Sets secure cookie instead of URL hash
- ✅ Auto-detects protocol for redirects
- ✅ Passes user info in query params (not token)

**[course_factory/api/auth/google/callback.php](../course_factory/api/auth/google/callback.php)**
- ✅ Matches main OAuth callback

### Authorization Functions

**[config/database.php](../config/database.php)**

**`requireAuth()` - Updated to check cookies first:**
```php
function requireAuth() {
    $token = null;
    
    // Check secure cookie first (preferred)
    if (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    // Fallback to Authorization header (backward compatibility)
    else {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    if (empty($token)) {
        sendJSON(['success' => false, 'message' => 'No authorization token provided'], 401);
    }
    
    $userData = verifyToken($token);
    if (!$userData) {
        sendJSON(['success' => false, 'message' => 'Invalid or expired token'], 401);
    }
    
    return $userData;
}
```

**`getAdminId()` - Updated to check cookies:**
```php
function getAdminId() {
    $token = null;
    
    // Check cookie first
    if (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    // Fallback to Authorization header
    else {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    if ($token) {
        $userData = verifyToken($token);
        if ($userData && isset($userData['userId'])) {
            return $userData['userId'];
        }
    }
    return null;
}
```

---

## Backward Compatibility

✅ **Maintained for gradual migration:**

1. **Server-side:** Checks cookies first, falls back to Authorization headers
2. **Response format:** Login still returns token in JSON
3. **Frontend:** Can continue using sessionStorage + headers temporarily
4. **Mixed usage:** New logins use cookies, existing sessions use headers

**Migration Path:**
- Phase 1: ✅ Server sets cookies (current implementation)
- Phase 2: Frontend reads from cookies if available
- Phase 3: Remove token from JSON response (after frontend migration)
- Phase 4: Remove Authorization header fallback (full cookie-only)

---

## Testing

### HTTP Development (localhost)
```bash
# Login over HTTP
curl -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"testpass"}' \
  -c cookies.txt

# Verify cookie set (secure=false for HTTP)
cat cookies.txt | grep auth_token

# Use cookie for authenticated request
curl http://localhost/api/student/get_advisor.php \
  -b cookies.txt
```

### HTTPS Development (mkcert)
```bash
# Login over HTTPS
curl -X POST https://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"testpass"}' \
  -c cookies.txt --insecure

# Verify cookie set (secure=true for HTTPS)
cat cookies.txt | grep auth_token

# Cookie automatically sent over HTTPS
curl https://localhost/api/student/get_advisor.php \
  -b cookies.txt --insecure
```

### Browser Testing

**Login Flow:**
1. Open DevTools → Application → Cookies
2. Navigate to login page (HTTP or HTTPS)
3. Submit login form
4. Verify `auth_token` cookie appears with:
   - ✅ HttpOnly flag
   - ✅ Secure flag (HTTPS only)
   - ✅ SameSite=Lax
5. Verify cookie sent with subsequent API requests

**OAuth Flow:**
1. Click "Sign in with Google"
2. Complete OAuth flow
3. Verify cookie set on callback
4. Verify redirect to correct dashboard
5. Verify authenticated API calls work

**Logout:**
1. Click logout button
2. Verify cookie removed from browser
3. Verify API calls return 401

---

## Security Considerations

### XSS Protection ✅

**httpOnly=true:**
- JavaScript cannot access `document.cookie`
- Token cannot be stolen via XSS attacks
- Requires server-side cookie handling

**Before (sessionStorage):**
```javascript
// Vulnerable to XSS
const token = sessionStorage.getItem('token');
// Attacker script can steal this
```

**After (httpOnly cookies):**
```javascript
// No JavaScript access - XSS safe
// Token automatically sent by browser
```

### CSRF Protection ✅

**SameSite=Lax:**
- Cookies sent with top-level navigation (OAuth redirects work)
- Cookies NOT sent with cross-site POST requests
- Prevents CSRF attacks on state-changing endpoints

**Lax vs Strict:**
- `Strict`: Blocks OAuth redirects (breaks Google login)
- `Lax`: Allows GET navigation, blocks POST (best for OAuth)
- `None`: No protection (requires `Secure` flag)

### HTTPS Enforcement 🔄

**Auto-detection (dev-friendly):**
```php
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
```

- **Development (HTTP):** `secure=false` - testing without SSL
- **mkcert (HTTPS):** `secure=true` - real HTTPS behavior
- **Production (HTTPS):** `secure=true` - enforced security

**Future Enhancement:**
Consider strict HTTPS enforcement in production:
```php
// In production config
if (ENVIRONMENT === 'production' && !$isSecure) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

---

## API Endpoint Behavior

### Login Endpoints

**Before:**
```json
POST /api/auth/login.php
Response: {
  "success": true,
  "token": "eyJhbGc...",
  "user": {...}
}
```

**After:**
```http
POST /api/auth/login.php
Set-Cookie: auth_token=eyJhbGc...; Path=/; HttpOnly; Secure; SameSite=Lax

Response: {
  "success": true,
  "token": "eyJhbGc...",  // Still included for compatibility
  "user": {...}
}
```

### Protected Endpoints

**Token Precedence:**
1. Check `$_COOKIE['auth_token']` (preferred)
2. Check `Authorization: Bearer <token>` header (fallback)

**Example:**
```php
// Both work
curl -H "Authorization: Bearer eyJhbGc..." /api/endpoint.php  // Old way
curl -b "auth_token=eyJhbGc..." /api/endpoint.php           // New way
```

---

## Cookie Lifecycle

### Creation
- Login: `api/auth/login.php` sets cookie
- OAuth: `api/auth/google/callback.php` sets cookie
- Expiration: 8 hours (`SESSION_LIFETIME`)

### Validation
- Every API request checks `$_COOKIE['auth_token']`
- JWT signature verified with `JWT_SECRET`
- Expiration checked (`exp` claim)

### Destruction
- Logout: Cookie deleted by setting `expires` in past
- Expiration: Browser auto-deletes after 8 hours
- JWT expiry: Server rejects expired tokens

---

## CORS Considerations

**Credentials Required:**

For cross-origin requests with cookies:
```javascript
fetch('https://api.example.com/endpoint', {
    credentials: 'include'  // Send cookies cross-origin
});
```

**Server Configuration:**
```php
header('Access-Control-Allow-Origin: https://frontend.example.com');  // Specific origin
header('Access-Control-Allow-Credentials: true');  // Allow cookies
```

**Note:** `Access-Control-Allow-Origin: *` does NOT work with credentials.

Current implementation uses same origin (no CORS for cookies needed).

---

## Migration Strategy

### Phase 1: ✅ Server-Side (Completed)
- [x] Set cookies in login endpoints
- [x] Set cookies in OAuth callbacks
- [x] Check cookies in `requireAuth()`
- [x] Clear cookies in logout
- [x] Maintain Authorization header fallback

### Phase 2: Frontend Updates (Optional)
- [ ] Update login handlers to use cookies
- [ ] Remove sessionStorage token storage
- [ ] Update API fetch calls to omit Authorization header
- [ ] Handle cookie expiration (401 → redirect to login)

### Phase 3: Cleanup (Future)
- [ ] Remove token from JSON responses
- [ ] Remove Authorization header fallback
- [ ] Simplify requireAuth() to cookies-only

---

## Troubleshooting

### Cookie Not Set

**Check:**
1. Headers sent before `setcookie()`? (must be before output)
2. Protocol matches `secure` flag? (HTTPS required if `secure=true`)
3. Path correct? (should be `/` for site-wide)
4. Browser blocks third-party cookies? (N/A for same-origin)

**Debug:**
```php
error_log("Cookie set: secure=$isSecure, path=/, httponly=true");
```

### Cookie Not Sent

**Check:**
1. Protocol matches cookie `secure` flag
2. Path matches request URL
3. Domain matches (empty = same origin only)
4. SameSite policy allows request type
5. Browser developer tools → Application → Cookies

### 401 Unauthorized

**Check:**
1. Cookie present in request? (DevTools → Network → Request Cookies)
2. JWT token valid? (not expired, correct signature)
3. `requireAuth()` checking cookies? (verify code)
4. Cookie name correct? (`auth_token`)

---

## Security Checklist

- [x] httpOnly flag prevents XSS token theft
- [x] Secure flag enforces HTTPS in production
- [x] SameSite=Lax prevents CSRF attacks
- [x] Auto-detect protocol for dev/prod flexibility
- [x] Cookies expire with JWT lifetime (8 hours)
- [x] Logout properly clears cookies
- [x] No token in URL (removed from OAuth hash)
- [x] No token in logs (cookie not logged by Apache)
- [x] CORS credentials allowed for same origin
- [ ] TODO: Consider HSTS header for production
- [ ] TODO: Consider shorter cookie lifetime with refresh tokens

---

## References

- Previous Implementation: [OAUTH_IMPLEMENTATION_COMPLETE.md](../OAUTH_IMPLEMENTATION_COMPLETE.md)
- HTTPS Audit: [HTTPS_AUDIT_REPORT.md](./HTTPS_AUDIT_REPORT.md)
- Architecture: [ARCHITECTURE.md](../ARCHITECTURE.md)
- Cookie Security: [MDN - Set-Cookie](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie)
- SameSite: [OWASP - SameSite Cookie Attribute](https://owasp.org/www-community/SameSite)

---

## Conclusion

✅ **Secure cookie implementation complete**

**Benefits:**
- Improved XSS protection (httpOnly)
- CSRF mitigation (SameSite=Lax)
- HTTPS-ready (secure flag auto-detection)
- Backward compatible (Authorization header fallback)

**Next Steps:**
1. Test login/logout over HTTPS
2. Test OAuth flow with cookies
3. Verify cookie security flags in browser
4. Update frontend to use cookie-based auth (optional)
5. Consider removing sessionStorage fallback after migration

**Status:** Ready for testing with mkcert HTTPS
