# Admin Migration Guide
## Migrating Existing Admins to Google SSO

This guide explains how to migrate existing admin/root users to Google SSO enforcement.

---

## Overview

After implementing the admin invitation system, you have two types of admin accounts:

1. **Legacy Admins** - Created before invitation system
   - `auth_provider_required` = NULL
   - Can still use password login
   - No Google SSO enforcement (grandfathered in)

2. **Invited Admins** - Created via invitation
   - `auth_provider_required` = 'google'
   - MUST use Google SSO
   - Password login blocked

---

## Migration Strategy

### Option 1: Voluntary Migration (Recommended)

Allow legacy admins to migrate themselves by accepting an invitation:

1. Root creates invitation for existing admin's email
2. Admin receives invitation link
3. Admin completes Google OAuth
4. System links Google account to existing user
5. System sets `auth_provider_required = 'google'`
6. Future logins require Google SSO

**Advantages:**
- No disruption to admin's workflow
- Admin controls timing
- Validates email access
- Tests Google OAuth works for that admin

**Steps:**
```bash
# As root user, create invitation for existing admin
curl -X POST http://localhost/api/admin/invite_admin.php \
  -H "Authorization: Bearer <ROOT_JWT>" \
  -H "Content-Type: application/json" \
  -d '{"email":"existing.admin@company.com","role":"admin"}'

# Send invite_url to admin
# Admin clicks link, completes Google OAuth
# System automatically upgrades their account
```

### Option 2: Bulk Migration (Database)

Force migration for all admins at once:

```sql
-- CAUTION: This immediately requires Google SSO for all admin/staff/root users
-- Only run this if:
-- 1. All admins have Google accounts with their registered email
-- 2. Google OAuth is tested and working
-- 3. You have communicated the change to all admins
-- 4. You have a rollback plan

UPDATE users 
SET auth_provider_required = 'google'
WHERE role IN ('admin', 'staff', 'root')
  AND auth_provider_required IS NULL;

-- Verify changes
SELECT username, email, role, auth_provider_required 
FROM users 
WHERE role IN ('admin', 'staff', 'root');
```

**IMPORTANT:** After running this, all affected users MUST use Google SSO immediately.

### Option 3: Selective Migration

Migrate specific admins by username or role:

```sql
-- Migrate specific admin
UPDATE users 
SET auth_provider_required = 'google'
WHERE username = 'admin_username';

-- Migrate all staff but not root
UPDATE users 
SET auth_provider_required = 'google'
WHERE role = 'staff' 
  AND auth_provider_required IS NULL;
```

---

## Rollback Instructions

If an admin is locked out or migration causes issues:

```sql
-- Remove Google SSO requirement for specific user
UPDATE users 
SET auth_provider_required = NULL
WHERE username = 'locked_out_admin';

-- Remove for all users (emergency rollback)
UPDATE users 
SET auth_provider_required = NULL
WHERE auth_provider_required = 'google';
```

After rollback, users can log in with passwords again.

---

## Communication Template

Before migrating admins, send this email:

```
Subject: Action Required: Migrate to Google Sign-In

Dear [Admin Name],

We're enhancing security by implementing Google Sign-In for all admin accounts.

WHAT THIS MEANS:
- You'll use "Sign in with Google" instead of password login
- Your existing account will be linked to your Google account
- More secure (2FA, Google's security features)

ACTION REQUIRED:
1. Click this link: [INVITE_URL]
2. Sign in with Google using your work email
3. Your account will be upgraded automatically

DEADLINE: [Date]
After this date, password login will be disabled for admin accounts.

QUESTIONS:
Contact IT support at [email/phone]

Thank you,
IT Security Team
```

---

## Testing Migration

Test with a single admin first:

1. Create test admin account
2. Test password login works
3. Send invitation
4. Complete Google OAuth
5. Verify password login blocked
6. Verify Google SSO works
7. If successful, proceed with other admins

---

## Student Accounts

**IMPORTANT:** Students are NOT affected by this change.

- Students never have `auth_provider_required` set
- Students can always use password login
- Optional: Students CAN use Google SSO if they want (not required)

---

## Monitoring

After migration, monitor these:

1. **Failed login attempts**
   ```sql
   SELECT * FROM auth_events 
   WHERE event_type = 'login_failed' 
     AND metadata LIKE '%wrong_auth_provider%'
   ORDER BY created_at DESC 
   LIMIT 20;
   ```

2. **Admins without Google linked**
   ```sql
   SELECT u.username, u.email, u.role 
   FROM users u
   LEFT JOIN auth_providers ap ON u.user_id = ap.user_id AND ap.provider_type = 'google'
   WHERE u.role IN ('admin', 'staff', 'root')
     AND u.auth_provider_required = 'google'
     AND ap.auth_provider_id IS NULL;
   ```

3. **Unused invitations**
   ```sql
   SELECT email, role, created_at, expires_at 
   FROM admin_invitations 
   WHERE used_at IS NULL 
     AND expires_at > NOW()
   ORDER BY expires_at;
   ```

---

## Troubleshooting

### Problem: Admin locked out after migration

**Solution:** Temporarily remove requirement:
```sql
UPDATE users SET auth_provider_required = NULL WHERE username = 'admin_name';
```

### Problem: Google email doesn't match registered email

**Options:**
1. Update user's email in database to match Google
2. Admin creates new Google account with work email
3. Use invitation system to link properly

### Problem: Google OAuth not working

**Check:**
- GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env
- Redirect URI matches Google Cloud Console
- Certificates valid (if using HTTPS)

---

## Best Practices

1. **Phased rollout**: Migrate staff first, then admins, then root last
2. **Communication**: Notify users 1 week before migration
3. **Support**: Have IT available during migration window
4. **Backup**: Ensure root account accessible (email + Google)
5. **Testing**: Test with non-critical account first
6. **Documentation**: Keep this guide accessible to all admins

---

## Future: Adding More Providers

To add Microsoft, Clever, etc.:

1. Add to `auth_provider_required` ENUM:
   ```sql
   ALTER TABLE users MODIFY COLUMN auth_provider_required 
   ENUM('local', 'google', 'microsoft', 'clever') NULL;
   ```

2. Create OAuth endpoints (similar to google/)
3. Update enforcement logic in login.php
4. Update invitation system to support new providers

---

## Questions?

Refer to main documentation or contact system administrator.
