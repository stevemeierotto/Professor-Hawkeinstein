# Debug & Troubleshooting Reference

**Consolidated from:** DEBUG_LESSON_GENERATION.md, SYSTEM_AGENT_DEBUG_REPORT.md, QUIZ_GRADING_TROUBLESHOOTING.md, DEPLOYMENT_DATABASE_FIX.md  
**Last Updated:** January 3, 2026

---

## 🔍 Quick Debugging Guide

### Common Issues & Solutions

| Issue | Check | Solution File |
|-------|-------|---------------|
| "Agent not found" errors | Database connection | [Database Connection](#database-connection-issues) |
| Lessons don't save | Table schema mismatch | [Lesson Generation](#lesson-generation-bugs) |
| Quiz grading fails | JSON parsing error | [Quiz Grading](#quiz-grading-system) |
| System agents missing | Model name format | [System Agent Loading](#system-agent-loading) |

---

## 🗄️ Database Connection Issues

### **Problem: Dual Database Syndrome**

**Date Resolved:** January 2, 2026  
**Symptom:** PHP and C++ services query different databases

The system had **TWO separate MySQL databases** running simultaneously:

1. **Local MySQL** (port 3306) - Used by Apache/PHP
2. **Docker MySQL** (port 3307) - Used by C++ agent service

**Symptoms:**
- PHP would create/update data in local MySQL
- C++ agent would query Docker MySQL and find nothing
- Constant "Agent not found" errors
- Tables missing in one database but not the other
- Schema drift between databases

### **Solution: Single Source of Truth**

**CANONICAL DATABASE: Docker MySQL (port 3307)**

#### Why Docker MySQL?
- ✅ Portable to AWS/cloud deployment
- ✅ Consistent environment across dev/staging/prod
- ✅ Easy backup/restore with volumes
- ✅ Isolated from local system changes
- ✅ Version controlled via docker-compose.yml

#### Changes Made

**File:** `config/database.php`

```php
// OLD - connected to local MySQL
define('DB_HOST', 'localhost');
// port hardcoded to 3306

// NEW - connects to Docker MySQL
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3307');
define('DB_NAME', 'professorhawkeinstein_platform');
```

**File:** `cpp_agent/config.docker.json`

```json
{
  "database": {
    "host": "database",
    "port": 3306,
    "name": "professorhawkeinstein_platform"
  }
}
```

#### Verification Steps

```bash
# Check PHP connection
php -r "require 'config/database.php'; echo 'DB: '.DB_NAME.' on '.DB_HOST.':'.DB_PORT.PHP_EOL;"

# Check C++ agent logs
docker logs phef-agent 2>&1 | grep "Connected to database"

# Query agent count from both services
mysql -h 127.0.0.1 -P 3307 -u professorhawkeinstein_user -p -e "SELECT COUNT(*) FROM professorhawkeinstein_platform.agents;"
```

**Result:** ✅ All services now query the same database

---

## 📝 Lesson Generation Bugs

### **Problem: Lessons Generate but Don't Save**

**Date Resolved:** December 30, 2025  
**Symptom:** Agent runs, no errors shown, but 0 lessons in database

#### Issue 1: Relative Path Errors

**Files Fixed:**
- `/api/admin/course_agent.php` - Line 16
- `/api/admin/generate_lesson_content.php` - Line 20
- `/api/admin/generate_draft_outline.php` - Line 5
- `/api/admin/generate_lesson_questions.php` - Line 24
- `/api/admin/generate_standards.php` - Line 28

**Problem:** When course factory was refactored to `/course_factory/` directory, API proxy files were created. Original PHP files used relative paths without `__DIR__`, causing "Failed to open stream" errors.

**Solution:**
```php
// OLD - breaks when called from different directory
require_once '../helpers/system_agent_helper.php';

// NEW - works from any caller location
require_once __DIR__ . '/../helpers/system_agent_helper.php';
```

**Status:** ✅ DEPLOYED to production

#### Issue 2: Table Schema Mismatch

**File:** `/api/admin/generate_lesson_content.php` - Line 84

**Problem:** INSERT statement used old column names from legacy content table:
- `page_title` → should be `title`
- `extracted_text` → should be `content_text`
- `subject_area` → should be `subject`
- `raw_content` → removed (not needed)
- `domain` → removed (not needed)

**Solution:**
```php
// NEW - matches educational_content schema
$stmt = $db->prepare("
  INSERT INTO educational_content 
  (url, title, content_type, content_html, content_text, 
   credibility_score, created_by, review_status, grade_level, subject)
  VALUES (?, ?, 'ai_generated', ?, ?, ?, 1, 'approved', ?, ?)
");
```

**Note:** `created_by` field stores user ID who triggered generation (legacy field name).

**Status:** ✅ DEPLOYED to production

#### Issue 3: Agent Service Content Query

**Problem:** C++ agent service needed to query content from correct table.

**Solution:** Updated `cpp_agent/src/database.cpp` to query `educational_content`:
```cpp
std::string query = R"(
  SELECT content_text FROM educational_content 
  WHERE content_type = 'ai_generated'
)";
```

**Note:** All lesson content is AI-generated. Legacy `scraped_content` table only stores standards from CSP API.

#### Debugging Commands

```bash
# Check if lessons were generated
mysql -h 127.0.0.1 -P 3307 -u root -p -D professorhawkeinstein_platform \
  -e "SELECT COUNT(*) FROM draft_lesson_content WHERE draft_id=15;"

# Check agent service logs
docker logs phef-agent 2>&1 | grep "lesson"

# Test generate_lesson_content.php directly
php /var/www/html/basic_educational/api/admin/generate_lesson_content.php
```

---

## 🎯 Quiz Grading System

### **Problem: JSON.parse Error on Quiz Submission**

**Date Resolved:** December 13-14, 2025  
**Symptom:** `SyntaxError: JSON.parse: unexpected character at line 1 column 1`

### Timeline of Fixes

#### Attempt 1: "Grading agent not available" Error

**Issue:** Agent lookup query couldn't find agent_id 15

**Fix:**
```php
// Added fallback query
$stmt = $db->prepare("
  SELECT agent_id, system_prompt, temperature, max_tokens 
  FROM agents 
  WHERE agent_id = 15 
  LIMIT 1
");
```

#### Attempt 2: Foreign Key Constraint Error

**Issue:** `course_id` FK constraint violation when inserting to `progress_tracking`

**Fix:**
```php
// Added course ID mapping
$courseIdMapping = [
  '2nd_grade_science' => 9,
  'alaska_math_grade1' => 5
];
$courseId = $courseIdMapping[$courseIdentifier] ?? null;
```

#### Attempt 3: Database Insert Still Failing

**Issue:** FK constraint persisted despite valid course_id

**Fix:** Bypassed database save for testing:
```php
// Commented out INSERT to progress_tracking
// Return grading results without database persistence
```

**Result:** ✅ Curl tests showed valid JSON response  
**Result:** ❌ Browser still reported JSON.parse error

#### Attempt 4: Enhanced Error Handling

**Fix:** Added robust error handling in `quiz.html`:
```javascript
const response = await fetch(url, options);

// Check status before parsing
if (!response.ok) {
  const errorText = await response.text();
  console.error('Server error:', errorText);
  throw new Error(`Server returned ${response.status}`);
}

// Read as text first, then parse
const responseText = await response.text();
console.log('Raw response:', responseText);

const data = JSON.parse(responseText);
```

#### Attempt 5: Database Connection Fix

**Root Cause:** PHP was connecting to local MariaDB (socket) instead of Docker MariaDB (TCP)

**Fix:** Changed `config/database.php`:
```php
// OLD
$dsn = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=" . DB_NAME;

// NEW
$dsn = "mysql:host=127.0.0.1;port=3306;dbname=" . DB_NAME;
```

**Result:** ✅ Database persistence works correctly

### Current Status

**Location:** `api/progress/submit_quiz.php`

**Features:**
- ✅ Auto-grading for multiple choice (case-insensitive)
- ✅ AI-powered grading for short answer (Grading Agent, agent_id 15)
- ✅ Database persistence to `progress_tracking` table
- ✅ JWT authentication required
- ✅ Detailed scoring and feedback

**Testing Results:**
- Score: 66.67% (2/3 correct)
- Database record: progress_id 6
- FK constraints: Satisfied
- Connection: TCP 127.0.0.1:3306

---

## 🤖 System Agent Loading

### **Problem: C++ Service Failed to Load 7 New System Agents**

**Date Resolved:** December 16, 2025  
**Symptom:** Agents 16-22 not appearing in `/agent/list` endpoint

### Root Cause Analysis

**System agents (IDs 16-22) had incomplete model names:**
```sql
model_name = 'qwen2.5-1.5b-instruct'  -- ❌ WRONG (21 chars)
```

**Should have been:**
```sql
model_name = 'qwen2.5-1.5b-instruct-q4_k_m.gguf'  -- ✅ CORRECT (38 chars)
```

### Why This Mattered

The C++ agent service (`cpp_agent/src/database.cpp`) queries the `agents` table:
```cpp
std::string query = R"(
  SELECT agent_id, agent_name, system_prompt, model_name, 
         temperature, max_tokens, description, avatar_emoji
  FROM agents
  WHERE is_active = 1
)";
```

When loading agents, it checks if the model file exists:
```cpp
std::string modelPath = "/path/to/models/" + modelName;
if (!std::filesystem::exists(modelPath)) {
  std::cerr << "Model not found: " << modelPath << std::endl;
  continue; // Skip this agent
}
```

**Result:** Agents with wrong model names were silently skipped.

### Database Inventory (After Fix)

| ID | Name | Type | Model | Status |
|----|------|------|-------|--------|
| 1 | Professor Hawkeinstein | student_advisor | llama-2-7b-chat | ✅ Active |
| 2 | Summary Agent | summary_agent | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 5 | Ms. Jackson | math_tutor | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 14 | Admin Advisor | admin_advisor | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 15 | Grading Agent | grading_agent | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 16 | Standards Analyzer | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 17 | Outline Generator | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 18 | Content Creator | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 19 | Question Generator | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 20 | Quiz Creator | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 21 | Unit Test Creator | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |
| 22 | Content Validator | system | qwen2.5-1.5b-instruct-q4_k_m.gguf | ✅ Active |

### Fix Applied

```sql
UPDATE agents 
SET model_name = 'qwen2.5-1.5b-instruct-q4_k_m.gguf'
WHERE agent_id BETWEEN 16 AND 22;
```

**Result:** ✅ All 7 system agents loaded successfully

### Verification

```bash
# Check agent service logs
docker logs phef-agent 2>&1 | grep "Loaded agent"

# Test API endpoint
curl http://localhost:8080/agent/list | jq '.[] | {id, name}'

# Query database
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT agent_id, agent_name, model_name FROM professorhawkeinstein_platform.agents WHERE agent_type='system';"
```

---

## 🛠️ General Debugging Commands

### Check Service Health

```bash
# Database
docker ps | grep phef-db
mysql -h 127.0.0.1 -P 3307 -u root -p -e "SHOW DATABASES;"

# LLM Server
curl http://localhost:8090/health

# Agent Service
curl http://localhost:8080/health
docker logs phef-agent 2>&1 | tail -50

# Apache/PHP
tail -f /var/log/apache2/course_factory_error.log
```

### Test Agent Chat

```bash
# Via C++ service
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"agentId":15,"message":"Hello"}'

# Via PHP API
curl -X POST http://professorhawkeinstein.local/api/agent/chat.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"userId":1,"agentId":15,"message":"Test message"}'
```

### Database Queries

```bash
# Count agents by type
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT agent_type, COUNT(*) FROM professorhawkeinstein_platform.agents GROUP BY agent_type;"

# Check student advisors
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT * FROM professorhawkeinstein_platform.student_advisors LIMIT 5;"

# Check course drafts
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT draft_id, title, status FROM professorhawkeinstein_platform.course_drafts;"

# Check educational content
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT content_type, COUNT(*) FROM professorhawkeinstein_platform.educational_content GROUP BY content_type;"
```

### Restart Services

```bash
# Restart all Docker services
cd /home/steve/Professor_Hawkeinstein
docker-compose down
docker-compose up -d

# Restart only agent service
docker-compose restart agent-service

# Restart Apache
sudo systemctl restart apache2

# Check Docker service status
docker-compose ps
```

---

## 📋 Known Issues

### 1. Path Confusion (RESOLVED)

**Issue:** Multiple DocumentRoots causing 404 errors  
**Solution:** All paths locked to `/var/www/html/basic_educational`  
**Date Fixed:** December 14, 2025

### 2. Foreign Key Constraints (RESOLVED)

**Issue:** `progress_tracking` FK violations on `course_id`  
**Solution:** Added course ID mapping in `submit_quiz.php`  
**Date Fixed:** December 13, 2025

### 3. Agent Model Names (RESOLVED)

**Issue:** Incomplete model names (missing quantization suffix)  
**Solution:** Updated all agent records with full filename  
**Date Fixed:** December 16, 2025

### 4. Dual Database Syndrome (RESOLVED)

**Issue:** PHP and C++ services using different databases  
**Solution:** Standardized on Docker MySQL (port 3307)  
**Date Fixed:** January 2, 2026

---

## 🔐 Admin Access

### Login Credentials

- **Username:** `root`
- **Password:** `Root1234`
- **Token Storage:** `/tmp/admin_token.txt`
- **Token Format:** JWT Bearer token (8-hour expiry)

### Generate New Admin Token

```bash
# Via API
curl -X POST http://professorhawkeinstein.local/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}'

# Extract token
TOKEN=$(curl -s -X POST http://professorhawkeinstein.local/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}' | jq -r '.token')

echo $TOKEN > /tmp/admin_token.txt
```

---

## 📚 Related Documentation

- [DEPLOYMENT_ENVIRONMENT_CONTRACT.md](DEPLOYMENT_ENVIRONMENT_CONTRACT.md) - Dev vs Prod rules
- [COURSE_GENERATION_ARCHITECTURE.md](COURSE_GENERATION_ARCHITECTURE.md) - Agent pipeline specs
- [ADVISOR_INSTANCE_API.md](ADVISOR_INSTANCE_API.md) - Student advisor system
- [ERROR_HANDLING_GUIDE.md](ERROR_HANDLING_GUIDE.md) - Error handling patterns
