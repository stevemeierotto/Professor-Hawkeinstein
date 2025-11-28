# Content Review System Debug Log
**Date:** November 23, 2025  
**Issue:** Content review submission failing with multiple database errors

---

## Error Timeline

### Error 1: Missing columns in content_reviews table
**Time:** 12:43 PM  
**Error:** `Unknown column 'strengths' in 'INSERT INTO'`  
**Root Cause:** PHP trying to insert strengths, weaknesses, fact_check_notes but columns didn't exist  
**Fix Applied:** 
```sql
ALTER TABLE content_reviews 
ADD COLUMN strengths TEXT AFTER quality_score,
ADD COLUMN weaknesses TEXT AFTER strengths,
ADD COLUMN fact_check_notes TEXT AFTER weaknesses;
```
**Status:** ✅ FIXED

---

### Error 2: Wrong array key for user_id
**Time:** 12:48 PM  
**Error:** `Undefined array key "user_id"` → `Column 'reviewer_id' cannot be null`  
**Root Cause:** auth_check.php returns `userId` (camelCase) but review_content.php used `user_id` (snake_case)  
**Fix Applied:** Changed `$admin['user_id']` → `$admin['userId']` in review_content.php (lines 54, 73)  
**Status:** ✅ FIXED

---

### Error 3: Missing reviewed_by column
**Time:** 12:51 PM  
**Error:** `Unknown column 'reviewed_by' in 'SET'`  
**Root Cause:** scraped_content table missing reviewed_by column  
**Fix Applied:**
```sql
ALTER TABLE scraped_content 
ADD COLUMN reviewed_by INT AFTER review_status,
ADD CONSTRAINT fk_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL;
```
**Status:** ✅ FIXED

---

## Database Schema Updates

### content_reviews table
```
✅ strengths TEXT
✅ weaknesses TEXT  
✅ fact_check_notes TEXT
```

### scraped_content table
```
✅ reviewed_by INT (FK to users.user_id)
✅ reviewed_at TIMESTAMP (already existed)
✅ review_status ENUM (already existed)
```

---

## Files Modified

1. **api/admin/review_content.php**
   - Changed `$admin['user_id']` → `$admin['userId']` (2 locations)
   - Synced to /var/www/html/Professor_Hawkeinstein/

2. **Database Schema**
   - Added 3 columns to content_reviews
   - Added 1 column to scraped_content with FK constraint

---

## Testing Checklist

- [ ] Submit review with approve recommendation
- [ ] Submit review with reject recommendation  
- [ ] Submit review with revise recommendation
- [ ] Verify reviewer_id is populated correctly
- [ ] Verify reviewed_by and reviewed_at are updated in scraped_content
- [ ] Check that strengths/weaknesses/fact_check_notes are saved

---

## Next Steps if Still Failing

1. Check Apache error log: `tail -20 /var/log/apache2/error.log`
2. Verify user is logged in: Check localStorage.getItem('token')
3. Test API directly: 
```bash
curl -X POST http://localhost/Professor_Hawkeinstein/api/admin/review_content.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content_id":1,"recommendation":"approve","accuracy_score":4.5}'
```
4. Check database:
```sql
SELECT * FROM content_reviews ORDER BY review_id DESC LIMIT 1;
SELECT content_id, review_status, reviewed_by, reviewed_at FROM scraped_content WHERE content_id = 1;
```

---

## System Context

- **Database:** professorhawkeinstein_platform
- **DB User:** professorhawkeinstein_user
- **Auth Method:** JWT tokens in Authorization header
- **Admin User:** root (user_id=5, role=root)
- **Review Endpoint:** /api/admin/review_content.php
- **Auth Check:** /api/admin/auth_check.php (returns userId, username, role)

---

**Status:** All known errors fixed. Ready for testing.

---

## Agent Factory Model List Fix (Nov 23, 12:55 PM)

**Issue:** Dropdown in agent factory showing Ollama model names (llama2, mistral, qwen2.5:3b, phi)  
**Fix Applied:** Updated admin_agent_factory.html model dropdown to use actual llama.cpp models:
- `qwen2.5-1.5b-instruct-q4_k_m.gguf` (default)
- `llama-2-7b-chat`

**File Modified:** admin_agent_factory.html lines 295-301  
**Status:** ✅ FIXED

---

## Create Agent API Fixes (Nov 23, 1:16 PM)

**Issues:** Multiple database column mismatches in create_agent.php
1. Using old column names: `page_title`, `source_url`, `extracted_text`, `subject_area`, `content_type`, `domain`
2. Trying to update non-existent column: `is_added_to_rag`
3. Using wrong auth key: `$admin['user_id']` instead of `$admin['userId']`

**Fixes Applied:**
1. Updated to correct column names: `title`, `url`, `cleaned_text`/`content_text`, `subject`
2. Removed `is_added_to_rag` UPDATE statement (column doesn't exist, content can be used by multiple agents)
3. Changed `$admin['user_id']` → `$admin['userId']` in logAdminAction call (line 133)
4. Added fallback: use `content_text` if `cleaned_text` is empty
5. Parse domain from URL instead of expecting it as a column

**File Modified:** api/admin/create_agent.php  
**Status:** ✅ FIXED

