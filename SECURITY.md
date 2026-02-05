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
