# Authentication Security Upgrade Proposal
**Platform:** Professor Hawkeinstein Educational Platform  
**Date:** January 17, 2026  
**Status:** Proposal for Review

---

## Executive Summary

This document proposes a secure upgrade to the platform's authentication system by adding:
1. **Google OAuth 2.0** ("Sign in with Google")
2. **Optional TOTP MFA** (Time-based One-Time Password, RFC 6238)

The upgrade maintains full backward compatibility with existing username/password accounts while strengthening security for an education platform serving minors.

---

## 1. Security Architecture Overview

### 1.1 Current State Analysis

**Existing System:**
- Username/password authentication with Argon2ID hashing + pepper
- JWT-based stateless authorization (HS256, 24-hour expiry)
- Role-based access control (student, admin, root)
- Password verification via PHP's `password_verify()`
- Session tracking in database (though JWT is stateless)

**Strengths:**
✅ Argon2ID is a strong password hashing algorithm  
✅ Password pepper adds defense-in-depth  
✅ JWT enables stateless API authentication  
✅ Prepared statements prevent SQL injection  

**Weaknesses:**
⚠️ No multi-factor authentication  
⚠️ Password reuse across sites is common (credential stuffing risk)  
⚠️ No SSO option for organizational deployments  
⚠️ JWT secret stored in config file (should be in environment variable)  

### 1.2 Proposed Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Authentication Layer                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────┐ │
│  │   Local Auth     │  │   Google OAuth   │  │ TOTP MFA  │ │
│  │  (Username/Pass) │  │    (OpenID)      │  │(Optional) │ │
│  └──────────────────┘  └──────────────────┘  └───────────┘ │
│           │                      │                   │       │
│           └──────────────────────┴───────────────────┘       │
│                              ▼                                │
│                   ┌────────────────────┐                     │
│                   │  Identity Linking  │                     │
│                   │  (users + auth_*   │                     │
│                   │   tables)          │                     │
│                   └────────────────────┘                     │
│                              ▼                                │
│                   ┌────────────────────┐                     │
│                   │   Generate JWT     │                     │
│                   │  (userId, role)    │                     │
│                   └────────────────────┘                     │
└─────────────────────────────────────────────────────────────┘
```

**Key Principles:**
1. **Defense in depth:** Multiple authentication methods, optional MFA
2. **Zero trust:** Always verify JWT on protected endpoints
3. **Least privilege:** Role-based access remains unchanged
4. **Graceful degradation:** If Google OAuth fails, local auth still works
5. **Audit trail:** Log all authentication events (success/failure)

---

## 2. Database Schema Changes

### 2.1 New Tables

#### `auth_providers` - Track authentication methods per user

```sql
CREATE TABLE auth_providers (
    auth_provider_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider_type ENUM('local', 'google') NOT NULL,
    provider_user_id VARCHAR(255) NULL,  -- Google sub claim
    provider_email VARCHAR(255) NULL,    -- Email from OAuth provider
    is_primary BOOLEAN DEFAULT FALSE,    -- Primary auth method
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_user (provider_type, provider_user_id),
    INDEX idx_user_provider (user_id, provider_type),
    INDEX idx_provider_lookup (provider_type, provider_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Rationale:**
- Allows multiple auth methods per user (link Google to existing account)
- `provider_user_id` stores Google's unique sub claim (never changes)
- `provider_email` helps with account recovery but not used for identity
- `is_primary` indicates preferred login method

#### `auth_totp` - TOTP MFA secrets (encrypted at rest)

```sql
CREATE TABLE auth_totp (
    totp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    secret_encrypted VARBINARY(512) NOT NULL,  -- AES-256 encrypted TOTP secret
    backup_codes_encrypted TEXT NULL,          -- JSON array of bcrypt-hashed codes
    enabled BOOLEAN DEFAULT FALSE,
    enrolled_at TIMESTAMP NULL,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_totp (user_id),
    INDEX idx_enabled (user_id, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Security Notes:**
- TOTP secrets MUST be encrypted at rest (AES-256-GCM)
- Encryption key stored in environment variable, NOT in database
- Backup codes stored as bcrypt hashes (like passwords)
- Each backup code can only be used once

#### `auth_events` - Audit log for authentication attempts

```sql
CREATE TABLE auth_events (
    event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                          -- NULL if login failed
    event_type ENUM(
        'login_success', 
        'login_failed', 
        'mfa_success', 
        'mfa_failed',
        'oauth_link', 
        'oauth_unlink',
        'mfa_enrolled',
        'mfa_disabled',
        'password_changed',
        'token_refresh'
    ) NOT NULL,
    auth_method ENUM('local', 'google', 'mfa') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    metadata JSON NULL,                        -- Additional context
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_events (user_id, created_at),
    INDEX idx_event_type (event_type, created_at),
    INDEX idx_ip_address (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Use Cases:**
- Detect credential stuffing (multiple failed logins from same IP)
- Anomaly detection (login from unusual location)
- Compliance audit trail (required for educational platforms)
- User account security dashboard

### 2.2 Modify Existing Tables

#### `users` table changes

```sql
-- Add new columns to users table
ALTER TABLE users 
    ADD COLUMN mfa_enabled BOOLEAN DEFAULT FALSE AFTER is_active,
    ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER email,
    ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER email_verified,
    ADD COLUMN email_verification_expires TIMESTAMP NULL AFTER email_verification_token,
    ADD INDEX idx_email_verified (email, email_verified),
    ADD INDEX idx_mfa_enabled (user_id, mfa_enabled);

-- Make password_hash nullable (Google OAuth users may not have password)
ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL;
```

**Rationale:**
- `mfa_enabled`: Quick check if user requires TOTP verification
- `email_verified`: Critical for OAuth linking (prevent account takeover)
- Google OAuth users authenticated via Google, no local password needed
- Email verification tokens short-lived (24-hour expiry)

### 2.3 Migration Strategy

```bash
# Create migration script
php migrations/add_oauth_mfa_support.php

# Rollback script (if needed)
php migrations/rollback_oauth_mfa_support.php
```

**Migration Steps:**
1. Add new tables in transaction
2. Backfill `auth_providers` with existing users (type='local')
3. Add columns to `users` table
4. Create indexes
5. Verify data integrity
6. Commit transaction

**Backward Compatibility:**
- Existing users automatically marked as 'local' provider
- Null `password_hash` only allowed for OAuth-only accounts
- MFA disabled by default (opt-in)

---

## 3. API Endpoints

### 3.1 Google OAuth Flow

#### **POST** `/api/auth/oauth/google/initiate`
**Purpose:** Start Google OAuth flow  
**Authentication:** None  
**Request:**
```json
{
  "redirect_uri": "https://app.professorhawkeinstein.org/auth/callback"
}
```
**Response:**
```json
{
  "success": true,
  "authorization_url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...",
  "state": "random_csrf_token_stored_in_session"
}
```

**Implementation Notes:**
- Use `league/oauth2-google` library (proven, actively maintained)
- Generate cryptographically secure CSRF `state` token
- Store state in Redis or database with 10-minute expiry
- Request minimal scopes: `openid email profile`

---

#### **POST** `/api/auth/oauth/google/callback`
**Purpose:** Handle Google OAuth callback  
**Authentication:** None (validates state token)  
**Request:**
```json
{
  "code": "authorization_code_from_google",
  "state": "csrf_token_from_initiate"
}
```
**Response (new user):**
```json
{
  "success": true,
  "message": "Account created via Google",
  "token": "jwt_token_here",
  "user": {
    "user_id": 123,
    "username": "johndoe_google",
    "email": "john@gmail.com",
    "full_name": "John Doe",
    "role": "student",
    "mfa_enabled": false,
    "requires_mfa": false
  }
}
```
**Response (existing user, email match):**
```json
{
  "success": true,
  "message": "Google account linked to existing account",
  "token": "jwt_token_here",
  "user": { ... }
}
```

**Security Checks:**
1. Validate state token (CSRF protection)
2. Exchange code for access token (server-to-server)
3. Verify ID token signature (Google's public keys)
4. Check `aud` claim matches client ID
5. Check `iss` is `accounts.google.com` or `https://accounts.google.com`
6. Check `exp` claim (token not expired)
7. Extract `sub` (unique user ID), `email`, `name`

**Account Linking Logic:**
- If email exists AND verified → link Google to existing account
- If email exists but NOT verified → require password confirmation
- If email doesn't exist → create new user
- Store Google `sub` in `auth_providers.provider_user_id`

---

#### **POST** `/api/auth/oauth/google/unlink`
**Purpose:** Remove Google OAuth from account  
**Authentication:** Required (JWT)  
**Request:**
```json
{
  "password": "user_password_for_confirmation"
}
```
**Response:**
```json
{
  "success": true,
  "message": "Google account unlinked. You can still log in with password."
}
```

**Security Checks:**
- Require current password verification (prevent hijacked session)
- Ensure user has at least one remaining auth method
- If Google was only method, force password creation first
- Log event in `auth_events`

---

### 3.2 TOTP MFA Endpoints

#### **POST** `/api/auth/mfa/enroll`
**Purpose:** Generate TOTP secret and QR code  
**Authentication:** Required (JWT)  
**Request:**
```json
{
  "app_name": "Professor Hawkeinstein"
}
```
**Response:**
```json
{
  "success": true,
  "secret": "BASE32_ENCODED_SECRET",
  "qr_code_url": "otpauth://totp/Professor%20Hawkeinstein:john@example.com?secret=...",
  "backup_codes": [
    "12345678",
    "23456789",
    "34567890",
    "45678901",
    "56789012"
  ]
}
```

**Implementation:**
- Use `spomky-labs/otphp` library (RFC 6238 compliant)
- Generate 160-bit random secret (cryptographically secure)
- Encrypt secret with AES-256-GCM before storing
- Generate 5 backup codes (8 digits each, bcrypt hashed)
- TOTP not enabled until user verifies first code

---

#### **POST** `/api/auth/mfa/verify-enrollment`
**Purpose:** Confirm TOTP setup by verifying first code  
**Authentication:** Required (JWT)  
**Request:**
```json
{
  "totp_code": "123456"
}
```
**Response:**
```json
{
  "success": true,
  "message": "MFA enabled successfully"
}
```

**Security:**
- Use 30-second time window (±1 window = 60 seconds tolerance)
- Allow only one verification attempt per 30-second window (rate limiting)
- Log event in `auth_events`
- Update `users.mfa_enabled = TRUE`

---

#### **POST** `/api/auth/mfa/verify`
**Purpose:** Verify TOTP code during login  
**Authentication:** Partial (temporary token from initial login)  
**Request:**
```json
{
  "temp_token": "temporary_jwt_from_login",
  "totp_code": "123456"
}
```
**Response:**
```json
{
  "success": true,
  "token": "full_jwt_token_with_all_permissions",
  "user": { ... }
}
```

**Flow:**
1. User logs in with password/OAuth → receives temp JWT (limited scope)
2. Temp JWT has `mfa_pending: true`, short expiry (5 minutes)
3. User submits TOTP code → receives full JWT if valid
4. Rate limit: 5 attempts per 10 minutes per user

---

#### **POST** `/api/auth/mfa/disable`
**Purpose:** Disable TOTP MFA  
**Authentication:** Required (JWT + password or TOTP)  
**Request:**
```json
{
  "password": "user_password",
  "totp_code": "123456"  // Required if password is null (OAuth users)
}
```
**Response:**
```json
{
  "success": true,
  "message": "MFA disabled"
}
```

**Security:**
- Require password OR valid TOTP code
- Delete TOTP secret from database
- Log event in `auth_events`
- Send email notification

---

#### **POST** `/api/auth/mfa/backup-code`
**Purpose:** Use backup code when TOTP unavailable  
**Authentication:** Partial (temp token)  
**Request:**
```json
{
  "temp_token": "temporary_jwt_from_login",
  "backup_code": "12345678"
}
```
**Response:**
```json
{
  "success": true,
  "token": "full_jwt_token",
  "remaining_codes": 4
}
```

**Security:**
- Hash input with bcrypt and compare to stored hashes
- Mark used codes as consumed (append to JSON array or separate table)
- Each code usable only once
- Warn user when <2 codes remain
- Generate new codes via `/api/auth/mfa/regenerate-backup-codes`

---

### 3.3 Modified Existing Endpoints

#### **POST** `/api/auth/login` (modified)
**Response with MFA:**
```json
{
  "success": true,
  "requires_mfa": true,
  "temp_token": "temporary_jwt_with_limited_scope",
  "message": "Please enter your authentication code"
}
```

**Response without MFA:**
```json
{
  "success": true,
  "requires_mfa": false,
  "token": "full_jwt_token",
  "user": { ... }
}
```

**Logic:**
1. Verify username/password
2. Check if `users.mfa_enabled = TRUE`
3. If yes → return temp token (requires MFA verification)
4. If no → return full token (backward compatible)

---

#### **POST** `/api/auth/register` (modified)
**New fields:**
```json
{
  "username": "johndoe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "full_name": "John Doe",
  "accept_terms": true,
  "parent_consent": true  // Required if under 13 (COPPA compliance)
}
```

**Response:**
```json
{
  "success": true,
  "message": "Account created. Please verify your email.",
  "email_verification_required": true
}
```

**Changes:**
- Send email verification link (token valid 24 hours)
- Set `email_verified = FALSE` initially
- Restrict features until email verified (especially OAuth linking)

---

## 4. Recommended Libraries

### 4.1 PHP Dependencies (Composer)

```json
{
  "require": {
    "php": "^8.1",
    "firebase/php-jwt": "^6.10",
    "league/oauth2-google": "^4.0",
    "spomky-labs/otphp": "^11.2",
    "paragonie/constant_time_encoding": "^2.6",
    "symfony/mailer": "^6.4",
    "predis/predis": "^2.2"
  }
}
```

#### **league/oauth2-google** (Google OAuth)
- **Why:** Official OAuth 2.0 client for Google, RFC 6749 compliant
- **Stars:** 3.2k GitHub stars, actively maintained
- **Security:** Validates ID tokens, handles token refresh
- **Docs:** https://github.com/thephpleague/oauth2-google

#### **spomky-labs/otphp** (TOTP)
- **Why:** RFC 6238/4226 compliant, battle-tested
- **Stars:** 1.1k GitHub stars
- **Security:** Constant-time comparison, configurable time window
- **Docs:** https://github.com/Spomky-Labs/otphp

#### **paragonie/constant_time_encoding** (Encoding utilities)
- **Why:** Timing-attack resistant encoding (used by otphp)
- **Security:** Written by Paragon Initiative (security experts)
- **Docs:** https://github.com/paragonie/constant_time_encoding

#### **symfony/mailer** (Email verification)
- **Why:** Mature, secure email library with SMTP/SendGrid/AWS SES support
- **Docs:** https://symfony.com/doc/current/mailer.html

#### **predis/predis** (Redis client for rate limiting)
- **Why:** Pure PHP, no C extensions required
- **Use:** Store OAuth state tokens, rate limit MFA attempts
- **Docs:** https://github.com/predis/predis

### 4.2 Frontend Dependencies (JavaScript)

```json
{
  "dependencies": {
    "qrcode": "^1.5.3",
    "@google-signin/button": "^1.0.0"
  }
}
```

#### **qrcode** (QR code generation)
- **Why:** Generate QR codes for TOTP enrollment client-side
- **Docs:** https://github.com/soldair/node-qrcode

#### **@google-signin/button** (Google Sign-In button)
- **Why:** Official Google Sign-In web component
- **Docs:** https://developers.google.com/identity/gsi/web

---

## 5. Security Considerations

### 5.1 Critical Security Pitfalls to Avoid

#### ❌ **DO NOT: Roll your own crypto**
- ✅ Use proven libraries: `password_hash()`, `openssl_encrypt()`, otphp
- ❌ Never implement TOTP algorithm from scratch
- ❌ Never invent "custom" encryption schemes

#### ❌ **DO NOT: Store TOTP secrets in plaintext**
- ✅ Encrypt with AES-256-GCM using environment key
- ✅ Use unique IV (initialization vector) per secret
- ❌ Storing secrets in plaintext = total compromise if DB leaked

#### ❌ **DO NOT: Trust email addresses from OAuth without verification**
- ✅ Check `email_verified` claim from Google (always true for Google)
- ✅ Require email verification for local accounts before OAuth linking
- ❌ Auto-linking accounts based on email alone = account takeover vulnerability

#### ❌ **DO NOT: Reuse OAuth state tokens**
- ✅ Generate new CSRF token per OAuth flow
- ✅ Expire state tokens after 10 minutes
- ✅ One-time use only (delete after validation)
- ❌ Reused state = CSRF vulnerability

#### ❌ **DO NOT: Accept TOTP codes without rate limiting**
- ✅ Limit to 5 attempts per 10 minutes per user
- ✅ Use Redis or database-based rate limiter
- ❌ Unlimited attempts = brute force 6-digit codes (1 million possibilities)

#### ❌ **DO NOT: Log sensitive data**
- ✅ Log authentication events (user_id, timestamp, IP, success/failure)
- ❌ Never log passwords, TOTP secrets, OAuth tokens, backup codes
- ❌ Sanitize user input before logging (prevent log injection)

#### ❌ **DO NOT: Allow OAuth linking without current authentication**
- ✅ Require valid JWT to link Google account
- ✅ Require password confirmation to unlink
- ❌ Session hijacking → attacker links their Google account to victim

#### ❌ **DO NOT: Use client-side JWT validation only**
- ✅ Always validate JWT signature on server
- ✅ Check expiration, issuer, audience
- ❌ Client can modify unsigned claims (if you forget to verify signature)

#### ❌ **DO NOT: Store JWT in localStorage (XSS risk)**
- ✅ Use httpOnly cookies for web apps
- ✅ OR sessionStorage (cleared on tab close)
- ❌ localStorage persists, accessible to all scripts (XSS = full compromise)

#### ❌ **DO NOT: Allow users to disable MFA without verification**
- ✅ Require current password OR valid TOTP code
- ✅ Send email notification when MFA disabled
- ❌ Hijacked session → attacker disables MFA → full account takeover

---

### 5.2 COPPA Compliance (Children's Privacy)

**Legal Requirement:** Platform serves minors (under 13 in US, under 16 in EU)

#### **Parental Consent for OAuth**
- Users under 13 MUST have parent/guardian consent
- Add checkbox: "I am a parent/guardian and consent to Google account usage"
- Store consent in `users` table: `parent_consent_date TIMESTAMP`
- Log consent in `auth_events`

#### **Data Minimization**
- Request minimal OAuth scopes: `openid email profile` (no calendar, contacts, etc.)
- Do NOT request access to Google Drive, Photos, etc.
- Only store: Google sub (identifier), email, name
- Do NOT store profile photo URLs (privacy risk)

#### **Email Verification**
- ALWAYS verify email before allowing OAuth linking
- Prevents child from linking parent's Google account without permission
- Send verification email with clear explanation

#### **Account Deletion**
- Provide easy account deletion in settings
- Delete all auth_providers, auth_totp, auth_events records
- Comply with GDPR "right to be forgotten"

---

### 5.3 Production Deployment Checklist

- [ ] Move JWT_SECRET to environment variable (not config file)
- [ ] Generate strong JWT_SECRET (64+ random bytes, base64 encoded)
- [ ] Move TOTP encryption key to environment variable
- [ ] Use Redis for OAuth state tokens (not database)
- [ ] Configure rate limiting (5 failed MFA per 10 min, 10 failed logins per hour)
- [ ] Enable HTTPS (TLS 1.2+, Let's Encrypt)
- [ ] Set `Secure` and `HttpOnly` flags on cookies
- [ ] Add Content-Security-Policy header (prevent XSS)
- [ ] Configure Google OAuth redirect URI whitelist
- [ ] Set up email alerts for suspicious activity (10+ failed logins)
- [ ] Implement IP-based anomaly detection (login from new country)
- [ ] Add password strength requirements (zxcvbn library)
- [ ] Configure backup code regeneration flow
- [ ] Test account recovery flow (lost TOTP device)
- [ ] Document MFA enrollment instructions for students/parents
- [ ] Train support staff on MFA troubleshooting
- [ ] Set up monitoring/alerting for auth failures (Prometheus/Grafana)

---

## 6. Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
- [ ] Install Composer dependencies
- [ ] Create database migration scripts
- [ ] Add new tables: `auth_providers`, `auth_totp`, `auth_events`
- [ ] Modify `users` table (nullable password_hash, email verification)
- [ ] Implement encryption/decryption helpers for TOTP secrets
- [ ] Set up Redis for OAuth state tokens

### Phase 2: Google OAuth (Week 3-4)
- [ ] Implement `/api/auth/oauth/google/initiate`
- [ ] Implement `/api/auth/oauth/google/callback`
- [ ] Implement `/api/auth/oauth/google/unlink`
- [ ] Add Google Sign-In button to login page
- [ ] Test account linking scenarios
- [ ] Add OAuth event logging

### Phase 3: TOTP MFA (Week 5-6)
- [ ] Implement `/api/auth/mfa/enroll`
- [ ] Implement `/api/auth/mfa/verify-enrollment`
- [ ] Implement `/api/auth/mfa/verify`
- [ ] Implement `/api/auth/mfa/disable`
- [ ] Implement backup code system
- [ ] Add MFA enrollment UI (QR code display)
- [ ] Add TOTP verification screen

### Phase 4: Integration (Week 7)
- [ ] Modify `/api/auth/login` for MFA flow
- [ ] Modify `/api/auth/register` for email verification
- [ ] Add rate limiting to all auth endpoints
- [ ] Implement email notifications (MFA enabled/disabled)
- [ ] Add user security dashboard (view devices, auth events)

### Phase 5: Testing & Security Audit (Week 8)
- [ ] Unit tests for all auth endpoints
- [ ] Integration tests for OAuth flow
- [ ] Penetration testing (CSRF, XSS, SQL injection)
- [ ] Test rate limiting and brute force protection
- [ ] Verify TOTP secret encryption
- [ ] Test account linking edge cases
- [ ] Load testing (1000+ concurrent logins)

### Phase 6: Documentation & Rollout (Week 9-10)
- [ ] Write user documentation (how to enable MFA)
- [ ] Write admin documentation (troubleshooting)
- [ ] Create video tutorials for students/parents
- [ ] Gradual rollout: 10% users → 50% → 100%
- [ ] Monitor error rates and authentication latency
- [ ] Collect feedback and iterate

---

## 7. Cost Analysis

### 7.1 Infrastructure Costs

| Component | Cost | Notes |
|-----------|------|-------|
| Google OAuth | $0 | Free for up to 10 requests/sec |
| Redis (rate limiting) | $10-30/month | AWS ElastiCache or self-hosted |
| Email service (verification) | $0-10/month | SendGrid free tier: 100 emails/day |
| SSL certificate | $0 | Let's Encrypt |
| **Total** | **$10-40/month** | Minimal incremental cost |

### 7.2 Development Costs (Estimated)

| Phase | Hours | Cost @ $100/hr |
|-------|-------|----------------|
| Planning & Design | 20 | $2,000 |
| Database & API Development | 80 | $8,000 |
| Frontend Integration | 40 | $4,000 |
| Testing & QA | 40 | $4,000 |
| Security Audit | 20 | $2,000 |
| Documentation | 20 | $2,000 |
| **Total** | **220** | **$22,000** |

**Note:** Costs vary based on team experience and existing codebase familiarity.

---

## 8. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Google OAuth downtime | Low | High | Fallback to local auth, monitor Google status |
| User loses TOTP device | Medium | Medium | Backup codes, admin recovery process |
| TOTP secret leak | Low | High | Encrypt at rest, rotate encryption key annually |
| Credential stuffing | High | Medium | Rate limiting, IP blocking, breach monitoring |
| Account takeover (email) | Medium | High | Email verification, OAuth linking protection |
| XSS attack (JWT theft) | Low | High | CSP headers, input sanitization, JWT in httpOnly cookies |
| SQL injection | Low | Critical | Prepared statements (already in place) |
| CSRF attack (OAuth) | Low | High | State token validation, SameSite cookies |

---

## 9. Alternatives Considered

### 9.1 Why Not Other OAuth Providers?

| Provider | Pros | Cons | Decision |
|----------|------|------|----------|
| Microsoft | Enterprise SSO | Less common for students | Maybe Phase 2 |
| Apple | Privacy-focused | iOS/macOS only | Maybe Phase 2 |
| Facebook | Wide adoption | Privacy concerns, under 13 restrictions | ❌ No |
| GitHub | Developer-friendly | Not for educational users | ❌ No |

**Decision:** Start with Google OAuth (most common for students/educators)

### 9.2 Why Not SMS-based MFA?

| Method | Pros | Cons | Decision |
|--------|------|------|----------|
| SMS OTP | No app required | SIM swapping attacks, costs $0.01-0.05/SMS | ❌ No |
| TOTP (RFC 6238) | Free, secure, offline | Requires authenticator app | ✅ Yes |
| WebAuthn/FIDO2 | Phishing-resistant | Requires hardware security key | Future Phase 3 |

**Decision:** TOTP for Phase 1, consider WebAuthn for Phase 3

---

## 10. Success Metrics

### 10.1 Adoption Metrics
- % of users with Google OAuth linked (target: 40% within 3 months)
- % of users with MFA enabled (target: 20% within 6 months)
- % of logins via Google OAuth vs local auth

### 10.2 Security Metrics
- Reduction in credential stuffing attacks (failed logins from known breach IPs)
- Reduction in account takeover incidents
- Time to detect and respond to suspicious login (target: <1 hour)

### 10.3 Performance Metrics
- OAuth login latency (target: <2 seconds end-to-end)
- MFA verification latency (target: <500ms)
- API error rate for auth endpoints (target: <0.1%)

### 10.4 User Satisfaction
- Support tickets related to authentication (target: <5% of total tickets)
- User survey: "How satisfied are you with login security?" (target: 4.5/5)

---

## 11. Conclusion

This proposal provides a **secure, scalable, and user-friendly** upgrade to the Professor Hawkeinstein authentication system. By leveraging proven libraries and following security best practices, we can:

1. ✅ Reduce credential stuffing risk (Google OAuth)
2. ✅ Add optional multi-factor authentication (TOTP)
3. ✅ Maintain backward compatibility (local auth still works)
4. ✅ Comply with COPPA and GDPR requirements
5. ✅ Provide audit trail for security incidents

**Recommended Next Steps:**
1. Review this proposal with security team
2. Approve budget and timeline
3. Begin Phase 1 (database migrations)
4. Register Google OAuth application
5. Set up development environment with Redis

**Questions or Concerns?**
Contact: Platform Security Team
