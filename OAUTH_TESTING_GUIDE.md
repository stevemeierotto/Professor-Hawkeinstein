# Google OAuth Testing - Quick Start

## ✅ Pre-Flight Checklist

### 1. Google Cloud Console Configuration

Go to: https://console.cloud.google.com/apis/credentials

**OAuth 2.0 Client ID Settings:**
- Click your Client ID: `634499304900-l65d1cdhl8fv3vofe4fictkrlrakk2re`
- Under **Authorized redirect URIs**, verify:
  - `http://localhost/api/auth/google/callback.php`
- Click **SAVE**

**Test Users (if app is in Testing mode):**
- Go to: https://console.cloud.google.com/apis/credentials/consent
- Under **Test users**, verify your email is added:
  - `stevemakesachannel@gmail.com`
- If not, click **+ ADD USERS** and add it

### 2. Local Configuration

**Verify .env file:**
```bash
grep GOOGLE_ /var/www/html/basic_educational/.env
```

**Expected output:**
```
GOOGLE_CLIENT_ID=<YOUR_GOOGLE_CLIENT_ID>
GOOGLE_CLIENT_SECRET=<YOUR_GOOGLE_CLIENT_SECRET>
GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback.php
```

**Test OAuth endpoint:**
```bash
curl -s http://localhost/api/auth/google/login.php | grep success
```

**Expected:** `"success":true`

## 🧪 Testing Steps

### Test 1: Student Login via localhost

1. **Open browser:** http://localhost/login.html
2. **Click:** "Sign in with Google" button (white button with Google logo)
3. **Google page:** Select your Google account (stevemakesachannel@gmail.com)
4. **Google consent:** Click "Continue" or "Allow"
5. **Redirected to:** http://localhost/student_portal/index.html#oauth_success=true&token=...
6. **Expected:** Automatic login, dashboard loads

**Verify in database:**
```bash
docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT user_id, username, email, role FROM users WHERE email = 'stevemakesachannel@gmail.com';"

docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT * FROM auth_providers WHERE provider_type = 'google' ORDER BY linked_at DESC LIMIT 1;"

docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT * FROM auth_events WHERE auth_method = 'google' ORDER BY created_at DESC LIMIT 5;"
```

### Test 2: Admin Login via localhost

1. **Open browser:** http://localhost/course_factory/admin_login.html
2. **Click:** "Sign in with Google" button
3. **Google page:** Select your Google account
4. **Expected behavior depends on account role:**

**If Google account is NEW (not linked to admin account):**
- Creates new user with role = 'student' (default)
- Redirects to: http://localhost/student_portal/index.html
- Shows: "Access Denied: This Google account does not have admin privileges"

**If Google account linked to admin account:**
- Redirects to: http://localhost/course_factory/admin_dashboard.html
- Dashboard loads successfully

**To link Google to admin account:**
```bash
# First, create Google account via student login
# Then, update role in database:
docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "UPDATE users SET role = 'admin' WHERE email = 'stevemakesachannel@gmail.com';"
```

### Test 3: Account Linking

**Scenario:** User has local account, then signs in with Google using same email.

1. **Create local account:**
   - Go to: http://localhost/register.html
   - Register with email: `test@example.com`
   - Set password: `Test1234!`

2. **Sign in with Google:**
   - Go to: http://localhost/login.html
   - Click "Sign in with Google"
   - Use Google account with email: `test@example.com`

3. **Expected:**
   - Google account linked to existing user
   - Both auth methods work (local password + Google OAuth)

**Verify:**
```bash
docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT user_id, username, email, password_hash IS NOT NULL AS has_password FROM users WHERE email = 'test@example.com';"

docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT provider_type FROM auth_providers WHERE user_id = (SELECT user_id FROM users WHERE email = 'test@example.com');"
```

**Expected:** 
- `has_password = 1` (local password exists)
- `provider_type = google` (Google linked)

## 🐛 Troubleshooting

### Error: "invalid_request" or "redirect_uri_mismatch"

**Problem:** Google Console redirect URI doesn't match exactly.

**Solution:**
1. Go to Google Console: https://console.cloud.google.com/apis/credentials
2. Click your OAuth Client ID
3. Delete any incorrect URIs (*.local domains)
4. Add ONLY: `http://localhost/api/auth/google/callback.php`
5. Save and wait 5 minutes for Google to propagate changes

### Error: "Access blocked: Authorization Error"

**Problem:** App is in Testing mode and your email isn't added as test user.

**Solution:**
1. Go to: https://console.cloud.google.com/apis/credentials/consent
2. Scroll to **Test users**
3. Click **+ ADD USERS**
4. Add: `stevemakesachannel@gmail.com`
5. Click **SAVE**

### Error: "OAuth initialization failed: OAuth redirect URI misconfigured"

**Problem:** .env file has wrong redirect URI.

**Solution:**
```bash
# Update .env
echo 'GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback.php' >> /var/www/html/basic_educational/.env

# Verify
grep GOOGLE_REDIRECT_URI /var/www/html/basic_educational/.env
```

### Error: 404 Not Found on callback

**Problem:** Apache virtual host not configured for localhost.

**Solution:**
```bash
# Check if localhost.conf exists
ls /etc/apache2/sites-enabled/localhost.conf

# If not, check main config has localhost alias
grep -A 5 "ServerName" /etc/apache2/sites-enabled/professorhawkeinstein.conf | grep localhost

# Restart Apache
sudo systemctl restart apache2
```

### Login button does nothing

**Problem:** JavaScript error or endpoint unreachable.

**Debug:**
1. Open browser console (F12)
2. Click "Sign in with Google"
3. Look for errors in console
4. Check Network tab for failed requests

**Common causes:**
- OAuth endpoint 500 error: Check .env file exists
- CORS error: Check Apache allows origin
- Network error: Check endpoint with curl

## 📊 Database Schema Reference

**users table:**
- `user_id` - Primary key
- `username` - Unique username
- `email` - Unique email
- `password_hash` - NULL for Google-only accounts
- `role` - 'student', 'admin', or 'root'
- `email_verified` - TRUE for Google accounts

**auth_providers table:**
- `provider_id` - Primary key
- `user_id` - Foreign key to users
- `provider_type` - 'google' or 'local'
- `provider_user_id` - Google's sub claim
- `email` - Email from provider
- `linked_at` - Timestamp
- `last_used` - Last login via this provider

**auth_events table:**
- `event_id` - Primary key
- `user_id` - Foreign key to users (NULL for failed logins)
- `event_type` - 'oauth_login', 'login_failed', etc.
- `auth_method` - 'google', 'local', etc.
- `ip_address` - User's IP
- `user_agent` - Browser info
- `metadata` - JSON additional data
- `created_at` - Timestamp

**oauth_states table:**
- `state` - Primary key (64-char hex)
- `user_id` - NULL (reserved for future use)
- `expires_at` - 10 minutes from creation
- `created_at` - Timestamp

## ✅ Success Indicators

After successful OAuth login, you should see:

1. **Browser:**
   - Redirected to dashboard (student_portal or course_factory)
   - URL hash contains token: `#oauth_success=true&token=...`
   - Dashboard loads without showing login page

2. **Database:**
   - New row in `users` table (or existing user found)
   - New row in `auth_providers` with provider_type='google'
   - New row in `auth_events` with event_type='oauth_login'

3. **Session:**
   - `sessionStorage.authToken` contains JWT
   - `sessionStorage.userId` contains user ID
   - `sessionStorage.username` contains username
   - `sessionStorage.userRole` contains role

## 📝 Notes

- **Google-only accounts** have `password_hash = NULL` in database
- **Account linking** happens automatically if email matches
- **Role assignment** is backend-only, never from frontend
- **OAuth state tokens** expire after 10 minutes
- **JWT tokens** expire after 24 hours
- **Admin role check** happens in callback, non-admins see error message
