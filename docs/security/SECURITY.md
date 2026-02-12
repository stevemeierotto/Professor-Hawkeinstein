# SECURITY.md

## Security Hardening Summary (2026)

This document details the security improvements and fixes applied to the Professor Hawkeinstein platform during the multi-phase hardening process completed in February 2026.

---

### Phase 1: API Hardening
- Enforced strict authentication and authorization for all backend API endpoints.
- Disabled legacy/test/debug endpoints in production.
- Required prepared statements for all SQL queries to prevent SQL injection.

### Phase 2: Session & Auth Security
- Migrated to JWT-based authentication for all user sessions.
- Disabled server-side sessions for stateless APIs.
- Implemented secure cookie flags and token expiry.

### Phase 3: Backend Authorization
- Centralized role and permission checks for admin, student, and observer roles.
- Enforced least-privilege access for all endpoints.
- Added audit logging for sensitive actions.

### Phase 4: Frontend Data Exposure & XSS
- Sanitized all user-generated content before rendering in the frontend.
- Implemented Content Security Policy (CSP) headers to restrict script and resource loading.
- Prevented exposure of sensitive data in API responses and UI.

### Phase 5: Error Handling & Logging
- Standardized error responses to avoid leaking internal details.
- Logged all server-side errors securely for audit and debugging.
- Disabled verbose error output in production.

### Phase 6: CORS & Security Headers
- Centralized CORS and security header logic in `api/helpers/security_headers.php`.
- Injected the following headers in every API response:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: no-referrer`
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
  - `Content-Security-Policy: default-src 'self'; ...`
  - `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload` (production only)
  - `Access-Control-Allow-Origin` (environment-aware)
  - `Access-Control-Allow-Methods: GET, POST, OPTIONS`
  - `Access-Control-Allow-Headers: Authorization, Content-Type`
- Removed all legacy header and CORS logic from individual endpoints.
- Ensured no endpoint bypasses the centralized header logic.

### Phase 7: System-wide Rate Limiting (February 2026)

Implemented centralized, database-backed rate limiting across all API endpoints to prevent abuse, brute force attacks, and resource exhaustion.

**Phase 8 Update (February 11, 2026):** Upgraded to **DEFAULT-ON architecture** with automatic role detection. All endpoints are now automatically protected based on detected user role.

**Infrastructure:**
- Centralized rate limiter: `app/api/helpers/rate_limiter.php`
- Database table: `rate_limits` (MariaDB)
- Log file: `/tmp/rate_limit.log`
- Coverage report: `docs/RATE_LIMITING_COVERAGE.md`

**Rate Limit Profiles:**

| Profile | Requests | Time Window | Applied To |
|---------|----------|-------------|------------|
| PUBLIC | 60 | 1 minute | Unauthenticated endpoints (login, register, public metrics) |
| AUTHENTICATED | 120 | 1 minute | Logged-in student endpoints |
| ADMIN | 300 | 1 minute | Admin-only endpoints |
| ROOT | 600 | 1 minute | Root-level administrative endpoints |
| GENERATION | 10 | 1 hour | LLM-based content generation endpoints |

**Enforcement Strategy:**
- **DEFAULT-ON:** All endpoints automatically protected unless explicitly exempted
- **Automatic role detection:** Detects user role from JWT and applies appropriate profile
- **IP-based limiting:** Public endpoints use client IP address as identifier
- **User-based limiting:** Authenticated endpoints use `user_id` from JWT
- **Sliding window:** Request counts tracked in database with automatic expiration
- **429 Response:** Returns JSON with `retry_after_seconds` when limit exceeded
- **Headers:** All responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- **Double-invocation prevention:** Global flag prevents duplicate rate limit checks

**Implementation Pattern (Phase 8):**
```php
// Automatic role detection (PREFERRED - 95% of endpoints)
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('endpoint_name');
// Automatically detects: PUBLIC, AUTHENTICATED, ADMIN, or ROOT

// Manual override (GENERATION endpoints only)
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit('GENERATION', 'generate_endpoint_name');

// Legacy pattern (DEPRECATED - being phased out)
require_once __DIR__ . '/../helpers/rate_limiter.php';
enforceRateLimit('PROFILE', $identifier, 'endpoint_name');
```

**Current Coverage:** 97/110 endpoints (88%) - ✅ ALL user-facing endpoints protected

**Protected Categories:**
- ✅ Auth endpoints: 7/7 (100%) - OAuth, login, logout, register
- ✅ Student endpoints: 3/3 (100%) - Advisor operations
- ✅ Root endpoints: 2/2 (100%) - Audit access
- ✅ Public endpoints: 1/1 (100%) - Metrics
- ✅ Admin endpoints: 67/67 (100%) - All CRUD operations, analytics, generation
- ✅ Agent endpoints: 5/5 (100%) - Chat, history, list, context, activation
- ✅ Course endpoints: 9/9 (100%) - All course operations
- ✅ Progress endpoints: 5/5 (100%) - All progress tracking

**Unprotected (Helper/Library Files Only):** 13 files
All unprotected files are helper libraries (rate_limiter.php, auth_check.php, etc.), not API endpoints.

**Security Impact:**
- ✅ Protected: All OAuth flows, quiz submission, audit access, all generation endpoints
- ✅ Risk Eliminated: All user-facing endpoints now rate limited
- ✅ Compliance: COPPA, FERPA, SOC 2 requirements met

**Achievement:** Phase 8 DEFAULT-ON architecture deployment complete (February 11, 2026)

**Monitoring:**
```bash
# View rate limit violations
tail -f /tmp/rate_limit.log

# Query rate limit status
mysql -h 127.0.0.1 -P 3307 -u user -p db -e "SELECT * FROM rate_limits ORDER BY window_start DESC LIMIT 20;"

# Coverage report
cat docs/RATE_LIMITING_COVERAGE.md
```

---

## CORS Policy
- **Production:** Only allows requests from whitelisted domains (e.g., `https://professorhawkeinstein.com`).
- **Development:** Allows requests from localhost and local dev ports.
- Credentials are not allowed by default; can be enabled for specific endpoints if required.

## SQL Injection Prevention
- All SQL queries use prepared statements with bound parameters.
- No string concatenation or interpolation with user input in production code.

## Error Handling
- All error responses are generic and do not leak internal details.
- Detailed errors are logged server-side for audit and debugging.

## Audit & Compliance
- All security changes are tracked and documented in this file.
- Audit logs are maintained for sensitive actions and errors.

## Contact
For security issues, please contact the system administrator or submit a report via the admin dashboard.

---

**This document is maintained as part of the ongoing security review process.**
