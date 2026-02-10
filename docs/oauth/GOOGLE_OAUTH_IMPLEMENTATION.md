# Google OAuth 2.0 Implementation Guide

## Overview

Google OAuth 2.0 login has been implemented using the **Authorization Code Flow** (server-side only). This provides secure authentication without relying on Google's JavaScript SDK.

---

## Files Created

### API Endpoints
- **`/api/auth/google/login.php`** - Initiates OAuth flow, redirects to Google
- **`/api/auth/google/callback.php`** - Handles OAuth callback, issues JWT

### Database
- **`migrations/add_oauth_support.sql`** - Database schema for OAuth
- **`migrations/run_oauth_migration.php`** - Migration runner script

### Configuration
- **`config/database.php`** - Added OAuth helper functions and Google config

---

## Database Schema

### Tables Created

#### `auth_providers`
Tracks authentication methods for each user:
```sql
- auth_provider_id (PK)
- user_id (FK to users)
- provider_type (ENUM: 'local', 'google')
- provider_user_id (Google sub claim)
- provider_email (email from Google)
- is_primary (boolean)
- linked_at, last_used (timestamps)
```

#### `auth_events`
Audit log for all authentication events:
```sql
- event_id (PK)
- user_id (nullable for failed attempts)
- event_type (login_success, login_failed, oauth_link, etc.)
- auth_method (local, google)
- ip_address, user_agent
- metadata (JSON for additional context)
- created_at (timestamp)
```

#### `oauth_states`
Stores CSRF state tokens (10-minute expiration):
```sql
- state_token (PK, 64-char hex)
- created_at, expires_at (timestamps)
```

### Modified Tables

#### `users`
- `password_hash` - Now **nullable** (OAuth-only accounts don't have passwords)
- `email_verified` - Boolean flag for email verification
- `email_verification_token` - Token for email verification (future use)
- `email_verification_expires` - Token expiration timestamp

---

## Configuration

### Environment Variables (.env)

Add these to your `.env` file:

```dotenv
# Google OAuth 2.0 Configuration
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback.php
```

### Get Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable **Google+ API** or **People API**
4. Go to **Credentials** → **Create Credentials** → **OAuth 2.0 Client ID**
5. Application type: **Web application**
6. Authorized redirect URIs: `http://localhost/api/auth/google/callback.php`
7. Copy Client ID and Client Secret to `.env`

**Development Note:** For localhost testing, Google OAuth does NOT require app verification.

---

## OAuth Flow

### Step 1: User Clicks "Sign in with Google"

Frontend makes request to:
```javascript
GET /api/auth/google/login.php
```

Response:
```json
{
  "success": true,
  "authorization_url": "https://accounts.google.com/o/oauth2/v2/auth?...",
  "state": "random_64_char_token"
}
```

Frontend redirects user to `authorization_url`.

### Step 2: User Authenticates with Google

User logs in with Google account and grants permissions.

### Step 3: Google Redirects to Callback

Google redirects to:
```
http://localhost/api/auth/google/callback.php?code=...&state=...
```

### Step 4: Backend Processes Callback

Backend (`callback.php`):
1. ✅ Validates `state` parameter (CSRF protection)
2. ✅ Exchanges `code` for access token
3. ✅ Fetches user profile from Google
4. ✅ Links Google account to existing user OR creates new user
5. ✅ Generates JWT token
6. ✅ Redirects to frontend with token in URL hash

### Step 5: Frontend Extracts JWT

Frontend JavaScript:
```javascript
// Check for OAuth success in URL hash
const hash = window.location.hash.substring(1);
const params = new URLSearchParams(hash);

if (params.get('oauth_success') === 'true') {
    const token = params.get('token');
    const userId = params.get('user_id');
    const username = params.get('username');
    const role = params.get('role');
    
    // Store JWT in sessionStorage
    sessionStorage.setItem('authToken', token);
    sessionStorage.setItem('userId', userId);
    sessionStorage.setItem('username', username);
    sessionStorage.setItem('userRole', role);
    
    // Clear hash from URL
    window.location.hash = '';
    
    // Redirect to dashboard or show welcome message
    console.log('OAuth login successful!');
}

// Check for OAuth error
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('oauth_error')) {
    alert('Login failed: ' + urlParams.get('oauth_error'));
}
```

---

## Account Linking Logic

### Scenario 1: Google Account Already Linked
- User exists in `auth_providers` with this Google ID
- ✅ Log in immediately
- Update `last_used` timestamp

### Scenario 2: Email Match (Existing User)
- User exists with matching email
- ✅ Link Google account to existing user
- Mark email as verified
- Log in

### Scenario 3: New User
- No existing user with this email
- ✅ Create new user account
- Username generated from email (e.g., `john.doe` from `john.doe@gmail.com`)
- No password set (`password_hash` is NULL)
- Email automatically verified
- Link Google account as primary provider
- Log in

---

## Security Features

### ✅ CSRF Protection
- State parameter is cryptographically random (32 bytes)
- Stored in database with 10-minute expiration
- One-time use (deleted after validation)
- Expired states automatically cleaned up

### ✅ No Custom Crypto
- Uses `league/oauth2-google` library (battle-tested)
- PHP's `random_bytes()` for state generation
- Google handles ID token signature verification

### ✅ Backend-Controlled Authorization
- User role determined by database, NOT from frontend
- JWT payload includes role from `users.role` column
- Google accounts default to `student` role

### ✅ Audit Logging
- All authentication events logged in `auth_events`
- Includes IP address, user agent, timestamp
- Failed attempts logged with error details

### ✅ Minimal Scopes
- Only requests: `openid`, `email`, `profile`
- No access to Google Drive, Calendar, etc.

### ✅ Email Verification
- Google accounts are always email-verified
- Linked accounts inherit verification status

---

## Helper Functions (config/database.php)

### OAuth State Management
```php
generateOAuthState()              // Generate 64-char random token
storeOAuthState($state, $minutes) // Store with expiration
validateOAuthState($state)        // Validate and consume (one-time use)
```

### User Management
```php
findUserByGoogleId($googleId)                    // Find user by Google sub
linkGoogleAccount($userId, $googleId, $email)    // Link Google to existing user
createUserFromGoogle($googleId, $email, $name)   // Create new user from Google
```

### Audit Logging
```php
logAuthEvent($userId, $eventType, $authMethod, $metadata)
// Event types: login_success, login_failed, oauth_link, oauth_login
```

---

## Frontend Integration

### Add "Sign in with Google" Button

```html
<button id="google-signin-btn" class="btn btn-google">
    <img src="https://www.google.com/favicon.ico" alt="Google" width="16">
    Sign in with Google
</button>
```

```javascript
document.getElementById('google-signin-btn').addEventListener('click', async () => {
    try {
        const response = await fetch('/api/auth/google/login.php');
        const data = await response.json();
        
        if (data.success) {
            // Redirect to Google's authorization page
            window.location.href = data.authorization_url;
        } else {
            alert('OAuth initialization failed: ' + data.message);
        }
    } catch (error) {
        console.error('OAuth error:', error);
        alert('Failed to start Google sign-in');
    }
});
```

### Handle OAuth Callback (on dashboard/login page)

```javascript
// Run on page load
window.addEventListener('DOMContentLoaded', () => {
    // Check for OAuth success in URL hash
    const hash = window.location.hash.substring(1);
    const params = new URLSearchParams(hash);
    
    if (params.get('oauth_success') === 'true') {
        const token = params.get('token');
        const userId = params.get('user_id');
        const username = params.get('username');
        const role = params.get('role');
        
        // Store in sessionStorage (existing JWT storage pattern)
        sessionStorage.setItem('authToken', token);
        sessionStorage.setItem('userId', userId);
        sessionStorage.setItem('username', username);
        sessionStorage.setItem('userRole', role);
        
        // Clear hash
        history.replaceState(null, null, ' ');
        
        // Show welcome message or reload dashboard
        console.log('Welcome ' + username + '!');
        window.location.reload();
    }
    
    // Check for OAuth error
    const urlParams = new URLSearchParams(window.location.search);
    const oauthError = urlParams.get('oauth_error');
    if (oauthError) {
        alert('Google sign-in failed: ' + oauthError);
        // Clear error from URL
        history.replaceState(null, null, window.location.pathname);
    }
});
```

---

## MFA Integration Points

The implementation includes **commented placeholders** for future TOTP MFA integration:

### In callback.php (Line ~150)
```php
// TODO: MFA Integration Point
// If user has MFA enabled ($user['mfa_enabled']), issue temporary JWT
// and redirect to MFA verification page instead of completing login
// 
// Example:
// if ($user['mfa_enabled']) {
//     $tempToken = generateToken($user['user_id'], $user['username'], 
//                                $user['role'], ['mfa_pending' => true]);
//     redirectToFrontendWithMFA($tempToken);
// }
```

When implementing MFA:
1. Check `users.mfa_enabled` flag
2. Issue temporary JWT with `mfa_pending: true`
3. Redirect to `/mfa_verify.html` instead of dashboard
4. Require TOTP code verification
5. Exchange temp JWT for full JWT after successful verification

---

## Testing

### Test Login Flow

1. **Set Google OAuth credentials in `.env`**
2. **Start services:** `docker compose up -d`
3. **Add button to login page:** See "Frontend Integration" above
4. **Click "Sign in with Google"**
5. **Authenticate with Google account**
6. **Check redirect:** Should redirect to dashboard with token in URL hash
7. **Verify JWT:** Token should be in `sessionStorage.authToken`

### Test Account Linking

1. **Create local user:** Register with email `test@gmail.com`
2. **Sign in with Google:** Use same email `test@gmail.com`
3. **Check database:**
   ```sql
   SELECT * FROM auth_providers WHERE user_id = <user_id>;
   -- Should see both 'local' and 'google' providers
   ```

### Test New User Creation

1. **Sign in with Google** using email NOT in database
2. **Check database:**
   ```sql
   SELECT * FROM users WHERE email = '<new_email>';
   -- Should see new user with password_hash = NULL
   
   SELECT * FROM auth_providers WHERE user_id = <new_user_id>;
   -- Should see 'google' provider with is_primary = TRUE
   ```

### Test Audit Logging

```sql
-- View all OAuth events
SELECT * FROM auth_events 
WHERE auth_method = 'google' 
ORDER BY created_at DESC 
LIMIT 20;

-- View failed login attempts
SELECT * FROM auth_events 
WHERE event_type = 'login_failed' 
ORDER BY created_at DESC;
```

---

## Troubleshooting

### Error: "Google OAuth not configured"
- **Cause:** `GOOGLE_CLIENT_ID` or `GOOGLE_CLIENT_SECRET` not set
- **Fix:** Add credentials to `.env` file

### Error: "Invalid or expired OAuth state"
- **Cause:** State token expired (10-minute limit) or CSRF attack
- **Fix:** Try login again; if persistent, check `oauth_states` table

### Error: "Google authentication failed"
- **Cause:** Invalid authorization code or network error
- **Fix:** Check `/var/www/html/Professor_Hawkeinstein/logs/` for details

### Error: "redirect_uri_mismatch"
- **Cause:** Callback URL doesn't match Google Cloud Console configuration
- **Fix:** Ensure `GOOGLE_REDIRECT_URI` matches exactly (including http/https)

### User Created with Wrong Username
- **Cause:** Username conflict (email prefix already exists)
- **Fix:** System auto-appends numbers (e.g., `john1`, `john2`)

### Database Connection Error
- **Cause:** Docker containers not running
- **Fix:** `docker compose up -d` and wait for services to be healthy

---

## Production Deployment Notes

### ⚠️ Security Checklist

- [ ] Change `GOOGLE_REDIRECT_URI` to production URL (HTTPS required)
- [ ] Update authorized redirect URIs in Google Cloud Console
- [ ] Generate strong `JWT_SECRET` (64+ random bytes)
- [ ] Set `DEBUG_MODE=false` in `.env`
- [ ] Enable HTTPS (TLS 1.2+) - **Required by Google OAuth**
- [ ] Configure rate limiting (5 failed attempts per 10 min)
- [ ] Set up log rotation for `auth_events` table
- [ ] Monitor for suspicious OAuth activity
- [ ] Test account recovery flow (lost access to Google account)
- [ ] Document user instructions for linking/unlinking accounts

### HTTPS Requirement

Google OAuth **requires HTTPS** for production redirect URIs. Localhost is exempt, but production must use:
```
https://yourdomain.com/api/auth/google/callback.php
```

### Multiple Domains

If hosting on multiple domains (e.g., staging and production):
1. Add multiple redirect URIs in Google Cloud Console
2. Set `GOOGLE_REDIRECT_URI` dynamically based on environment

---

## Next Steps

### Immediate
1. ✅ Add Google OAuth credentials to `.env`
2. ✅ Add "Sign in with Google" button to login page
3. ✅ Test OAuth flow end-to-end

### Future Enhancements
- [ ] Implement TOTP MFA (see `docs/AUTH_UPGRADE_PROPOSAL.md`)
- [ ] Add account unlinking endpoint (`/api/auth/google/unlink.php`)
- [ ] Implement email verification for local accounts
- [ ] Add user security dashboard (view linked accounts, auth history)
- [ ] Implement password reset flow for users with both local + Google
- [ ] Add Microsoft OAuth (for organizational deployments)

---

## File Locations

```
Professor_Hawkeinstein/
├── api/auth/google/
│   ├── login.php          # OAuth initiation endpoint
│   └── callback.php       # OAuth callback handler
├── config/
│   └── database.php       # OAuth helper functions added
├── migrations/
│   ├── add_oauth_support.sql        # SQL schema
│   └── run_oauth_migration.php      # Migration runner
├── docs/
│   ├── AUTH_UPGRADE_PROPOSAL.md     # Full security architecture
│   └── GOOGLE_OAUTH_IMPLEMENTATION.md  # This file
└── .env                   # Google OAuth credentials
```

---

## Support

For questions or issues:
1. Check error logs: `/var/www/html/Professor_Hawkeinstein/logs/`
2. Review `auth_events` table for audit trail
3. Consult `docs/AUTH_UPGRADE_PROPOSAL.md` for architecture details
4. Verify Google OAuth configuration in Cloud Console

---

**Implementation Date:** January 17, 2026  
**Library:** `league/oauth2-google` v4.1.0  
**PHP Version:** 8.1+  
**Status:** ✅ Ready for Testing
