# Google OAuth 2.0 - Localhost Setup for Professor Hawkeinstein

## Critical Google OAuth Requirement

**Google OAuth REJECTS `.local` domains.** You MUST use `localhost` or a real public domain.

## Configuration

### 1. Environment Variables (`.env`)

```env
GOOGLE_CLIENT_ID=<YOUR_GOOGLE_CLIENT_ID>
GOOGLE_CLIENT_SECRET=<YOUR_GOOGLE_CLIENT_SECRET>
GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback.php
```

**MUST be exactly:** `http://localhost/api/auth/google/callback.php`

### 2. Google Cloud Console Setup

1. Go to: https://console.cloud.google.com/apis/credentials
2. Click your OAuth 2.0 Client ID
3. Under **Authorized redirect URIs**, add:
   - `http://localhost/api/auth/google/callback.php`
4. Click **SAVE**
5. Add test user at: https://console.cloud.google.com/apis/credentials/consent
   - Click **+ ADD USERS**
   - Add: `stevemakesachannel@gmail.com`
   - Click **SAVE**

### 3. Apache Virtual Host Configuration

**File:** `/etc/apache2/sites-enabled/localhost.conf`

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/basic_educational
    
    <Directory /var/www/html/basic_educational>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/localhost_error.log
    CustomLog ${APACHE_LOG_DIR}/localhost_access.log combined
</VirtualHost>
```

**Enable and restart:**
```bash
sudo a2ensite localhost.conf
sudo systemctl restart apache2
```

## Testing OAuth Flow

### Step 1: Access via localhost

**Student Portal:** http://localhost/login.html  
**Admin Portal:** http://localhost/course_factory/admin_login.html

### Step 2: Click "Sign in with Google"

JavaScript calls: `http://localhost/api/auth/google/login.php`

### Step 3: Authenticate with Google

Google redirects to: `http://localhost/api/auth/google/callback.php?state=...&code=...`

### Step 4: Callback processes authentication

**Callback logic:**
1. Validates state token (CSRF protection)
2. Exchanges code for access token
3. Fetches user profile (email, name, Google ID)
4. Links to existing user OR creates new user
5. Issues JWT token
6. Redirects to dashboard with token in URL hash

**Role-based redirect:**
- Admin/Root → http://localhost/course_factory/admin_dashboard.html#token=...
- Student → http://localhost/student_portal/index.html#token=...

### Step 5: Frontend extracts JWT

JavaScript extracts token from URL hash and stores in `sessionStorage`.

## Architecture Notes

### Why localhost for .local domains?

The platform uses:
- `professorhawkeinstein.local` - Main student portal
- `factory.professorhawkeinstein.local` - Admin/course factory

**Problem:** Google OAuth rejects `.local` TLD as insecure.

**Solution:** 
- All sites accessible via `localhost` (Apache ServerAlias)
- OAuth uses `localhost` redirect URI
- `/api` directory aliased in factory subdomain virtual host
- Users can access either domain, OAuth works on localhost

### Apache Virtual Host Routing

```
http://localhost/* → /var/www/html/basic_educational/
http://professorhawkeinstein.local/* → /var/www/html/basic_educational/
http://factory.professorhawkeinstein.local/* → /var/www/html/basic_educational/course_factory/
http://factory.professorhawkeinstein.local/api/* → /var/www/html/basic_educational/api/ (Alias)
```

## Implementation Files

### OAuth Endpoints

**[api/auth/google/login.php](../api/auth/google/login.php)**
- Generates state token
- Stores in `oauth_states` table (10-minute expiry)
- Returns Google authorization URL
- Validates redirect URI matches `http://localhost/api/auth/google/callback.php`

**[api/auth/google/callback.php](../api/auth/google/callback.php)**
- Validates state token (one-time use)
- Exchanges authorization code for access token
- Fetches Google profile
- Links account or creates new user
- Issues JWT token
- Redirects to role-appropriate dashboard

### Database Tables

**`auth_providers`** - Links users to OAuth providers
```sql
user_id, provider_type, provider_user_id, email, linked_at, last_used
```

**`auth_events`** - Audit log
```sql
user_id, event_type, auth_method, ip_address, user_agent, metadata, created_at
```

**`oauth_states`** - CSRF state tokens
```sql
state, user_id, expires_at, created_at
```

### Helper Functions (config/database.php)

- `generateOAuthState()` - Generates 64-char hex token
- `storeOAuthState($state)` - Stores with 10-min expiry
- `validateOAuthState($state)` - One-time validation
- `findUserByGoogleId($googleId)` - Lookup by Google sub claim
- `linkGoogleAccount($userId, $googleId, $email)` - Link to existing user
- `createUserFromGoogle($googleId, $email, $fullName)` - Create new OAuth user
- `logAuthEvent($userId, $eventType, $authMethod, $metadata)` - Audit logging

## Security Features

1. **Authorization Code Flow (RFC 6749)** - Most secure OAuth flow
2. **State Parameter** - CSRF protection, one-time use, 10-minute expiry
3. **Email Verification** - Google accounts treated as verified
4. **Account Linking** - Email match links Google to existing accounts
5. **No Passwords for OAuth** - `password_hash` is NULL for Google-only users
6. **Audit Logging** - All auth events logged in `auth_events` table
7. **Role Security** - Backend determines role, never from frontend
8. **JWT Tokens** - HS256, 24-hour expiry

## MFA Integration Point (Future)

In [callback.php](../api/auth/google/callback.php) after user lookup:

```php
// TODO: MFA Integration Point
if ($user['mfa_enabled']) {
    $tempToken = generateToken($user['user_id'], $user['username'], $user['role'], ['mfa_pending' => true]);
    redirectToFrontendWithMFA($tempToken);
}
```

Will redirect to TOTP verification page instead of completing login.

See [AUTH_UPGRADE_PROPOSAL.md](./AUTH_UPGRADE_PROPOSAL.md) for complete MFA architecture.

## Production Deployment

For production with real domain:

1. **Register domain:** professorhawkeinstein.com
2. **Install SSL certificate:** Use Let's Encrypt (free)
3. **Update .env:**
   ```env
   GOOGLE_REDIRECT_URI=https://professorhawkeinstein.com/api/auth/google/callback.php
   ```
4. **Update Google Console:** Add HTTPS redirect URI
5. **Update callback.php:** Change `$baseUrl` to production domain
6. **Verify app** or **Publish app** in Google Console

## Troubleshooting

### Error: "Invalid redirect_uri"
- Check .env has exactly: `http://localhost/api/auth/google/callback.php`
- Check Google Console has same URI in Authorized redirect URIs
- Restart Apache after config changes

### Error: "Access blocked: Authorization Error"
- App is in Testing mode
- Add your email as test user in Google Console
- OR publish the app

### Error: "404 Not Found" on callback
- Check Apache virtual host has `localhost` ServerName/ServerAlias
- For factory subdomain, check `/api` Alias is configured
- Verify files exist: `ls /var/www/html/basic_educational/api/auth/google/`

### OAuth works on localhost but not .local domains
- **Expected behavior** - Google rejects .local
- Access site via `http://localhost/` instead
- OR use production setup with real domain + HTTPS

## Testing Checklist

- [ ] `.env` has localhost redirect URI
- [ ] Google Console has localhost redirect URI
- [ ] Test user added in Google Console
- [ ] Apache restarted after config changes
- [ ] Files synced to `/var/www/html/basic_educational/`
- [ ] Endpoint test: `curl http://localhost/api/auth/google/login.php`
- [ ] Browser test: Click "Sign in with Google" button
- [ ] Verify redirect to Google authorization page
- [ ] Authenticate with Google account
- [ ] Verify redirect to dashboard with JWT in URL hash
- [ ] Check database: `auth_providers`, `auth_events` tables populated
