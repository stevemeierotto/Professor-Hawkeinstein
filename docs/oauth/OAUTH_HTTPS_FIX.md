# Quick Fix: Google OAuth HTTPS Setup

## Problem
OAuth redirecting back to login page when using HTTPS because Google Console only has HTTP redirect URI registered.

## Solution

### Step 1: Add HTTPS Redirect URI to Google Console

1. Go to: https://console.cloud.google.com/apis/credentials
2. Click on your OAuth 2.0 Client ID
3. Under "Authorized redirect URIs", **ADD** (don't replace):
   ```
   https://localhost/api/auth/google/callback.php
   ```
4. Keep the existing HTTP one too:
   ```
   http://localhost/api/auth/google/callback.php
   ```
5. Click **SAVE**

### Step 2: Test Login

**Over HTTPS:**
1. Navigate to: `https://localhost/course_factory/admin_login.html`
2. Click "Sign in with Google"
3. Complete OAuth flow
4. Should redirect to: `https://localhost/course_factory/admin_dashboard.html`

**Over HTTP (still works):**
1. Navigate to: `http://localhost/course_factory/admin_login.html`
2. Should work as before

## What Changed

✅ Removed hardcoded `http://` validation from OAuth endpoints  
✅ Auto-detects protocol (HTTP or HTTPS) from request  
✅ OAuth callback now supports both protocols  
✅ Synced to production: `/var/www/html/basic_educational/`

## Verification

Check which protocol is being used:
```bash
# Check OAuth logs
tail -f /var/log/apache2/error.log | grep OAuth
```

You should see:
```
[OAuth Login] Using redirect URI: https://localhost/api/auth/google/callback.php
[OAuth Callback] Using redirect URI: https://localhost/api/auth/google/callback.php
```

## Troubleshooting

**Still redirecting to login?**
- Wait 1-2 minutes after saving Google Console (cache)
- Clear browser cookies: DevTools → Application → Cookies → Delete all
- Check error logs: `tail -50 /var/log/apache2/error.log`

**"redirect_uri_mismatch" error?**
- Verify HTTPS URI added to Google Console exactly as shown above
- Must be `https://localhost` (not `https://localhost:443`)
- Case-sensitive - must match exactly

**Mixed content warnings?**
- All API endpoints now support HTTPS
- Secure cookies auto-detect protocol
- No hardcoded HTTP URLs in OAuth flow
