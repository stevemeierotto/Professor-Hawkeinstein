# Google OAuth Quick Test Guide

## Prerequisites Complete ✅
- ✅ `league/oauth2-google` library installed
- ✅ Database tables created (`auth_providers`, `auth_events`, `oauth_states`)
- ✅ API endpoints created (`/api/auth/google/login.php`, `/api/auth/google/callback.php`)
- ✅ Helper functions added to `config/database.php`
- ✅ Files synced to production web directory

## Next Steps to Test

### 1. Configure Google OAuth Credentials

Edit `.env` file and replace placeholders:
```bash
GOOGLE_CLIENT_ID=your_actual_client_id_here
GOOGLE_CLIENT_SECRET=your_actual_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback.php
```

**Get credentials from:** https://console.cloud.google.com/apis/credentials

### 2. Add "Sign in with Google" Button

Add to `login.html` (or `student_portal/index.html`):

```html
<!-- Add this button next to existing login form -->
<button id="google-signin-btn" style="padding: 10px 20px; background: #4285f4; color: white; border: none; border-radius: 4px; cursor: pointer;">
    Sign in with Google
</button>

<script>
document.getElementById('google-signin-btn').addEventListener('click', async () => {
    try {
        const response = await fetch('/api/auth/google/login.php');
        const data = await response.json();
        
        if (data.success) {
            window.location.href = data.authorization_url;
        } else {
            alert('OAuth error: ' + data.message);
        }
    } catch (error) {
        console.error('OAuth error:', error);
        alert('Failed to start Google sign-in');
    }
});
</script>
```

### 3. Add OAuth Callback Handler

Add to dashboard pages (runs on page load):

```javascript
// Handle OAuth callback
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.substring(1);
    const params = new URLSearchParams(hash);
    
    if (params.get('oauth_success') === 'true') {
        const token = params.get('token');
        const userId = params.get('user_id');
        const username = params.get('username');
        
        sessionStorage.setItem('authToken', token);
        sessionStorage.setItem('userId', userId);
        sessionStorage.setItem('username', username);
        
        window.location.hash = '';
        console.log('OAuth login successful!');
        window.location.reload();
    }
});
```

### 4. Test Flow

1. Click "Sign in with Google" button
2. Authenticate with Google account
3. Should redirect back to dashboard with JWT in URL hash
4. Check browser console for "OAuth login successful!"
5. Verify `sessionStorage` has `authToken`

### 5. Verify in Database

```sql
-- Check user was created/linked
SELECT u.*, ap.provider_type, ap.provider_user_id 
FROM users u
LEFT JOIN auth_providers ap ON u.user_id = ap.user_id
WHERE u.email = 'your_google_email@gmail.com';

-- Check auth events
SELECT * FROM auth_events 
WHERE auth_method = 'google' 
ORDER BY created_at DESC 
LIMIT 5;
```

## Troubleshooting

### "Google OAuth not configured"
- Check `.env` has real credentials (not placeholders)
- Restart Apache if needed

### "redirect_uri_mismatch"
- Verify `GOOGLE_REDIRECT_URI` exactly matches Google Cloud Console
- Must be: `http://localhost/api/auth/google/callback.php`

### Button does nothing
- Check browser console for errors
- Verify `/api/auth/google/login.php` is accessible

## Quick Test Command

```bash
# Test login endpoint directly
curl http://localhost/api/auth/google/login.php

# Should return JSON with authorization_url
```

## Documentation

- Full guide: `docs/GOOGLE_OAUTH_IMPLEMENTATION.md`
- Architecture proposal: `docs/AUTH_UPGRADE_PROPOSAL.md`
