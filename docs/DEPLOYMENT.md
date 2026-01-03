# Deployment Guide

**Consolidated from:** DEPLOYMENT_CHECKLIST.md, AGENT_SYSTEM_PROMPT_RESTORATION.md  
**Last Updated:** January 3, 2026

---

## 🚨 Critical Rules

**READ FIRST:** [DEPLOYMENT_ENVIRONMENT_CONTRACT.md](DEPLOYMENT_ENVIRONMENT_CONTRACT.md)

### Dev vs Production

- **DEV:** `/home/steve/Professor_Hawkeinstein` (coding, testing - NOT web-accessible)
- **PROD:** `/var/www/html/basic_educational` (live site - ONLY web-accessible directory)

### Never:
- ❌ Edit PROD files directly (changes lost on next deploy)
- ❌ Assume DEV changes auto-sync to PROD (they don't)
- ❌ Create new directories in `/var/www/html` without confirmation
- ❌ Mix up DocumentRoot paths (always `/var/www/html/basic_educational`)

### Always:
- ✅ Edit files in DEV first
- ✅ Deploy to PROD explicitly (`make sync-web` or `cp`)
- ✅ Check logs before assuming file sync issues
- ✅ Ask before touching PROD filesystem

---

## 📋 Pre-Deployment Checklist

- [ ] Code changes tested in development workspace
- [ ] All new files added to git (if using version control)
- [ ] Database schema changes documented
- [ ] Breaking changes identified and mitigation planned
- [ ] Backup database: `docker exec phef-database mysqldump -u root -p professorhawkeinstein_platform > backup.sql`

---

## 🔧 Code Update Workflows

### 1. PHP/API Changes

**Files affected:** `api/`, `config/`, `*.php`

**Workflow:**
```bash
# 1. Edit files in workspace
cd /home/steve/Professor_Hawkeinstein/

# 2. Test locally
php /home/steve/Professor_Hawkeinstein/api/admin/your_file.php

# 3. Sync to production
make sync-web

# Or manually:
cp api/admin/your_file.php /var/www/html/basic_educational/api/admin/

# 4. Verify Apache logs
tail -f /var/log/apache2/course_factory_error.log

# 5. Test in browser
http://factory.professorhawkeinstein.local/api/admin/your_file.php
```

**Critical files to sync:**
- `config/database.php` - Database connection settings
- `api/admin/*.php` - Admin endpoints
- `api/student/*.php` - Student endpoints
- `api/helpers/*.php` - Helper functions

**Important:** Use `__DIR__` for all relative paths:
```php
// ✅ CORRECT - works from any caller
require_once __DIR__ . '/../helpers/system_agent_helper.php';

// ❌ WRONG - breaks when called from different directory
require_once '../helpers/system_agent_helper.php';
```

### 2. C++ Agent Service Changes

**Files affected:** `cpp_agent/src/`, `cpp_agent/include/`, `cpp_agent/config.docker.json`

**Workflow:**
```bash
# 1. Edit source files in workspace
cd /home/steve/Professor_Hawkeinstein/cpp_agent/

# 2. Update config if needed
nano config.docker.json

# 3. Rebuild container
docker-compose build --no-cache agent-service

# 4. Restart service
docker-compose up -d agent-service

# 5. Verify logs
docker logs phef-agent 2>&1 | tail -30

# 6. Check database connection
docker logs phef-agent 2>&1 | grep "Connected to database"

# 7. Test endpoint
curl http://localhost:8080/health

# 8. Check agent loading
curl http://localhost:8080/agent/list | jq '.[] | {id, name}'
```

**Time estimate:** ~30 seconds build, ~5 seconds startup

**Common issues:**
- Database connection errors → Check `config.docker.json` has correct host
- Agents not loading → Check model_name includes full filename with `.gguf`
- Compilation errors → Check all dependencies installed in Dockerfile

### 3. Frontend Changes

**Files affected:** `*.html`, `*.js`, `*.css`

**Workflow:**
```bash
# 1. Edit files in workspace
cd /home/steve/Professor_Hawkeinstein/

# 2. Cache-bust JavaScript (update timestamp)
TIMESTAMP=$(date +%s)
sed -i "s/admin_auth.js?v=[0-9]*/admin_auth.js?v=$TIMESTAMP/" admin_dashboard.html

# 3. Sync to production
make sync-web

# Or manually:
cp admin_dashboard.html /var/www/html/basic_educational/
cp admin_auth.js /var/www/html/basic_educational/

# 4. Test in browser (hard refresh: Ctrl+Shift+R)
http://factory.professorhawkeinstein.local/admin_dashboard.html

# 5. Check browser console for errors (F12)
```

**Cache-busting example:**
```html
<!-- OLD -->
<script src="admin_auth.js?v=1"></script>

<!-- NEW -->
<script src="admin_auth.js?v=1735828800"></script>
```

**Generate timestamp:** `date +%s`

### 4. Docker Compose Changes

**Files affected:** `docker-compose.yml`

**Workflow:**
```bash
# 1. Edit docker-compose.yml
nano /home/steve/Professor_Hawkeinstein/docker-compose.yml

# 2. Validate syntax
docker-compose config

# 3. IMPORTANT: Restart won't pick up env vars - must recreate
docker-compose up -d --force-recreate SERVICE_NAME

# 4. Verify environment variables
docker exec CONTAINER_NAME env | grep VAR_PREFIX

# 5. Verify all services
docker-compose ps

# 6. Check service logs
docker-compose logs SERVICE_NAME
```

**Critical Docker Networking:**
- **Host machine → Database**: Port 3307 (external), `DB_HOST=localhost, DB_PORT=3307`
- **Docker containers → Database**: Port 3306 (internal), `DB_HOST=database, DB_PORT=3306`
- API container MUST have `DB_PORT=3306` in environment (not 3307)

**Example - Adding DB_PORT environment variable:**
```yaml
api:
  environment:
    DB_HOST: database
    DB_PORT: 3306  # Internal Docker port, not external 3307
    DB_USER: professorhawkeinstein_user
    DB_PASS: ${DB_PASSWORD:-BT1716lit}
    DB_NAME: professorhawkeinstein_platform
```

Apply with:
```bash
docker-compose up -d --force-recreate api
docker exec phef-api env | grep DB_  # Verify DB_PORT=3306 appears
```

---

## 🗄️ Database Changes

### Schema Updates

```bash
# 1. Write SQL in migration file
nano /home/steve/Professor_Hawkeinstein/migrations/add_new_column.sql

# 2. Apply to Docker database ONLY
docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform < migrations/add_new_column.sql

# 3. Update schema.sql for future deployments
nano schema.sql

# 4. Test PHP access
php test_new_column.php

# 5. Test C++ agent access if applicable
docker logs phef-agent 2>&1 | grep "new_column"

# 6. Document in migration notes
echo "$(date): Added new_column to table_name" >> migrations/CHANGELOG.md
```

### Adding Agents

```bash
# 1. Insert into Docker database
docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform << 'EOF'
INSERT INTO agents (agent_id, agent_name, agent_type, specialization, 
                    system_prompt, model_name, temperature, max_tokens) 
VALUES (
  23, 
  'New Agent Name',
  'system',
  'specialization',
  'System prompt with explicit output format requirements...',
  'qwen2.5-1.5b-instruct-q4_k_m.gguf',  -- FULL filename required
  0.5,
  2048
);
EOF

# 2. Verify agent exists
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e "SELECT agent_id, agent_name FROM agents WHERE agent_id = 23;"

# 3. Restart agent service to reload
docker-compose restart agent-service

# 4. Verify agent loaded
curl http://localhost:8080/agent/list | jq '.[] | select(.id==23)'

# 5. Test agent call
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"agentId":23,"message":"Test"}'
```

**Critical:** Model name MUST include full filename with quantization suffix:
- ✅ `qwen2.5-1.5b-instruct-q4_k_m.gguf` (CORRECT)
- ❌ `qwen2.5-1.5b-instruct` (WRONG - agent won't load)

### Adding Tables

```bash
# 1. Create migration SQL
cat > migrations/create_new_table.sql << 'EOF'
CREATE TABLE new_table (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  FOREIGN KEY (related_id) REFERENCES existing_table(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF

# 2. Apply to database
docker exec -i phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform < migrations/create_new_table.sql

# 3. Verify table exists
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e "SHOW CREATE TABLE new_table;"

# 4. Test insert/select operations
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e "INSERT INTO new_table (name) VALUES ('test');"

# 5. Test foreign key constraints
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e "DELETE FROM existing_table WHERE id = 1;"
# Should cascade delete related rows in new_table
```

---

## ✅ Verification Steps

### After ANY Change

**1. Check service status:**
```bash
docker-compose ps
# All should be "Up" and "healthy"
```

**2. Check logs for errors:**
```bash
# Apache/PHP
tail -20 /var/log/apache2/course_factory_error.log

# C++ Agent
docker logs phef-agent 2>&1 | tail -20

# LLM Server
docker logs phef-llama 2>&1 | tail -20

# Database
docker logs phef-database 2>&1 | tail -20
```

**3. Test critical paths:**
- [ ] Login works: `http://factory.professorhawkeinstein.local/admin_login.html`
- [ ] Course creation works: Create new course wizard
- [ ] Agent chat works: Test chat with Professor Hawkeinstein
- [ ] Content generation works: Generate lesson content

### Database Verification

```bash
# PHP connects to Docker MySQL
tail -5 /var/log/apache2/course_factory_error.log | grep "ACTUAL connected to"

# C++ agent connects to Docker MySQL
docker logs phef-agent 2>&1 | grep "Connected to database"

# Table exists
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e "SHOW TABLES LIKE 'table_name';"

# Count agents by type
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e "SELECT agent_type, COUNT(*) FROM agents GROUP BY agent_type;"
```

### Frontend Verification

```bash
# Check file permissions
ls -la /var/www/html/basic_educational/*.html

# Test admin auth
curl -v http://factory.professorhawkeinstein.local/admin_auth.js

# Check browser cache (should see new timestamp)
curl -I http://factory.professorhawkeinstein.local/admin_auth.js?v=1735828800
```

---

## 🤖 Agent System Prompt Requirements

**All system agents MUST have explicit output format specifications.**

### Standards Analyzer (Agent 5)

**Output Format:** JSON array
```json
[
  {
    "id": "S1",
    "statement": "Students will understand...",
    "skills": ["identify", "describe"]
  }
]
```

**System Prompt Template:**
```
You are a standards generator for educational content.

Grade Level: {grade_level}
Subject: {subject}

Generate 10-15 educational standards that are:
- Age-appropriate for grade {grade_level}
- Aligned with {subject} curriculum
- Specific and measurable
- Include 2-3 key skills per standard

Output ONLY valid JSON array. No explanations. No markdown.
```

**Parameters:** `temp=0.3, max_tokens=2048` (factual, concise)

### Outline Generator (Agent 6)

**Output Format:** JSON object with units/lessons structure
```json
{
  "units": [
    {
      "title": "Unit 1",
      "description": "...",
      "lessons": [
        {
          "title": "Lesson 1",
          "description": "...",
          "standard_code": "S1",
          "estimated_duration": "30 minutes"
        }
      ]
    }
  ]
}
```

**Parameters:** `temp=0.4, max_tokens=2048` (structured, logical)

### Content Creator (Agent 18)

**Output Format:** Plain text (8-10 paragraphs, 800-1000 words)

**Requirements:**
- Age-appropriate vocabulary
- Real-world examples
- Key terms defined
- Engaging explanations
- No markdown formatting

**Parameters:** `temp=0.6, max_tokens=4096` (creative, detailed)

### Question Generator (Agent 19)

**Output Format:** Structured text format (NOT JSON)
```
QUESTION: What is the main idea?
ANSWER: The answer here
EXPLANATION: Why this is correct

QUESTION: Next question?
ANSWER: The answer
EXPLANATION: Brief explanation
```

**Note:** Questions are inherently appropriate for the course's grade level. No additional difficulty labeling needed.

**Parameters:** `temp=0.5, max_tokens=2048` (varied, educational)

### Quiz Creator (Agent 20)

**Requirements:**
- Mix question types (fill-in-blank, multiple choice, short essay)
- Logical ordering (simple concepts first, building to complex)
- Appropriate for course grade level

**Parameters:** `temp=0.4, max_tokens=1024` (organized)

### Unit Test Creator (Agent 21)

**Requirements:**
- 20-30 questions covering all lessons
- Multiple question types
- Comprehensive coverage of unit concepts

**Parameters:** `temp=0.4, max_tokens=2048` (thorough)

### Content Validator (Agent 22)

**Checks:**
- Factual accuracy
- Age-appropriateness
- Standards alignment
- Completeness
- No hallucinations

**Parameters:** `temp=0.3, max_tokens=1024` (critical, precise)

---

## 🛠️ Common Deployment Issues

### Issue 1: Database Connection Refused

**Symptom:** PHP or C++ agent can't connect to database

**Cause:** Wrong port configuration (3306 vs 3307)

**Fix:**
```bash
# Check which port service is using
docker logs phef-agent 2>&1 | grep "port"

# For Docker containers, use port 3306
# For host machine, use port 3307

# Update docker-compose.yml if needed
docker-compose up -d --force-recreate api
```

### Issue 2: Agents Not Loading

**Symptom:** Agent list empty or missing agents

**Cause:** Incomplete model name in database (missing `.gguf` suffix)

**Fix:**
```sql
UPDATE agents 
SET model_name = 'qwen2.5-1.5b-instruct-q4_k_m.gguf'
WHERE model_name = 'qwen2.5-1.5b-instruct';
```

Then restart agent service:
```bash
docker-compose restart agent-service
```

### Issue 3: Relative Path Errors

**Symptom:** "Failed to open stream" errors in PHP

**Cause:** Using relative paths without `__DIR__`

**Fix:**
```php
// Change this:
require_once '../helpers/system_agent_helper.php';

// To this:
require_once __DIR__ . '/../helpers/system_agent_helper.php';
```

### Issue 4: Frontend Cache Issues

**Symptom:** Changes not appearing in browser

**Fix:**
```bash
# Update timestamp in HTML
TIMESTAMP=$(date +%s)
sed -i "s/admin_auth.js?v=[0-9]*/admin_auth.js?v=$TIMESTAMP/" admin_dashboard.html

# Hard refresh in browser (Ctrl+Shift+R)
# Or clear cache in DevTools
```

### Issue 5: Docker Environment Variables Not Applied

**Symptom:** Changes to `docker-compose.yml` not taking effect

**Cause:** `docker-compose restart` doesn't reload environment variables

**Fix:**
```bash
# Must use --force-recreate
docker-compose up -d --force-recreate SERVICE_NAME

# Verify variables loaded
docker exec SERVICE_NAME env | grep VAR_NAME
```

---

## 📊 Deployment Checklist Summary

### PHP/API Changes
- [ ] Edit in DEV
- [ ] Test locally
- [ ] Sync to PROD (`make sync-web`)
- [ ] Check Apache logs
- [ ] Test in browser

### C++ Agent Changes
- [ ] Edit source files
- [ ] Update config if needed
- [ ] Rebuild container (`docker-compose build --no-cache`)
- [ ] Restart service (`docker-compose up -d`)
- [ ] Check logs and test endpoints

### Frontend Changes
- [ ] Edit HTML/JS/CSS
- [ ] Cache-bust with timestamp
- [ ] Sync to PROD
- [ ] Hard refresh browser
- [ ] Check console for errors

### Database Changes
- [ ] Write migration SQL
- [ ] Apply to Docker database
- [ ] Update schema.sql
- [ ] Test operations
- [ ] Document changes

### Docker Compose Changes
- [ ] Edit docker-compose.yml
- [ ] Validate syntax
- [ ] Force recreate service
- [ ] Verify environment variables
- [ ] Check service status

---

## 📚 Related Documentation

- [DEPLOYMENT_ENVIRONMENT_CONTRACT.md](DEPLOYMENT_ENVIRONMENT_CONTRACT.md) - Dev vs Prod rules
- [FILE_SYNC_GUIDE.md](FILE_SYNC_GUIDE.md) - Automated sync procedures
- [DEBUG_TROUBLESHOOTING.md](DEBUG_TROUBLESHOOTING.md) - Debugging guide
- [ERROR_HANDLING_GUIDE.md](ERROR_HANDLING_GUIDE.md) - Error handling patterns
- [SETUP_STATUS.md](SETUP_STATUS.md) - Current system status
