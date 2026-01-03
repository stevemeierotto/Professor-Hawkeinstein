# System Setup & Status Reference

**Consolidated from:** SETUP_COMPLETE.md, SYSTEM_STATUS_2026-01-03.md, COURSE_PIPELINE_STATUS.md, SESSION_LOG_2024-12-14.md  
**Last Updated:** January 3, 2026

---

## 🎉 Current System Status

**All systems operational!** The 5-agent course generation pipeline is running with proper system prompts and database connectivity.

---

## 🌐 Quick Access

### URLs

**Admin Dashboard:**
```
http://localhost/Professor_Hawkeinstein/admin_dashboard.html
http://factory.professorhawkeinstein.local/admin_dashboard.html
```

**Student Portal:**
```
http://localhost/Professor_Hawkeinstein/student_dashboard.html
http://professorhawkeinstein.local/student_dashboard.html
```

**Course Factory:**
```
http://factory.professorhawkeinstein.local/admin_courses.html
```

### Root Credentials

```
Username: root
Password: Root1234
```

**Root Privileges:**
- Create/delete admin accounts
- View all system activity
- All admin capabilities
- Direct database access

---

## ✅ Components Status

### Database (MariaDB 10.11 Docker)
- **Status:** ✅ OPERATIONAL
- **Port:** 3307 external, 3306 internal Docker network
- **Database:** `professorhawkeinstein_platform` (CANONICAL - single source of truth)
- **Connections:** 
  - ✅ PHP API container (database:3306)
  - ✅ C++ Agent container (database:3306)
  - ✅ Host machine CLI (localhost:3307)

### LLM Server (llama.cpp)
- **Status:** ✅ HEALTHY
- **Model:** Qwen 2.5-1.5B Instruct Q4_K_M (ONLY model)
- **Port:** 8090
- **Performance:** 4-9 seconds per agent response
- **Context Size:** 4096 tokens
- **Flags:** `--threads 4 --parallel 2 --cont-batching`

### C++ Agent Service
- **Status:** ✅ OPERATIONAL
- **Port:** 8080
- **Agents Loaded:** 12 agents (7 system, 5 user-facing)
- **Database Connection:** ✅ Connected to professorhawkeinstein_platform
- **Logs:** `/tmp/agent_service_full.log`

### PHP API (Apache)
- **Status:** ✅ OPERATIONAL
- **Port:** 8081 (internal), 80/443 (external via proxy)
- **Database Connection:** ✅ Fixed - connects to database:3306
- **Authentication:** ✅ JWT working (root/Root1234)
- **Logs:** `/var/log/apache2/course_factory_error.log`

---

## 🤖 System Agents

**7 Active System Agents** (agent_type='system'):

| ID | Agent Name | Purpose | Max Tokens | Temp | Status |
|----|------------|---------|------------|------|--------|
| 5 | Standards Analyzer | Generate standards from grade/subject | 2048 | 0.30 | ✅ Tested |
| 6 | Outline Generator | Convert standards to course outline | 2048 | 0.40 | ✅ Ready |
| 18 | Content Creator | Generate lesson content (800-1000 words) | 4096 | 0.60 | ✅ Ready |
| 19 | Question Generator | Create quiz questions | 2048 | 0.50 | ✅ Ready |
| 20 | Quiz Creator | Assemble balanced quizzes | 1024 | 0.40 | ✅ Ready |
| 21 | Unit Test Creator | Generate comprehensive assessments | 2048 | 0.40 | ✅ Ready |
| 22 | Content Validator | QA check for content quality | 1024 | 0.30 | ✅ Ready |

**Note:** Old duplicate agents (IDs 7-11) with minimal prompts were removed.

---

## 🎓 User-Facing Agents

| ID | Agent Name | Type | Purpose | Status |
|----|------------|------|---------|--------|
| 1 | Professor Hawkeinstein | student_advisor | General academic advisor | ✅ Active |
| 2 | Summary Agent | summary_agent | Content summarization | ✅ Active |
| 5 | Ms. Jackson | math_tutor | Math tutoring | ✅ Active |
| 14 | Admin Advisor | admin_advisor | Admin guidance | ✅ Active |
| 15 | Grading Agent | grading_agent | Quiz grading (short answer) | ✅ Active |

---

## 📊 Course Generation Pipeline

**5-Agent Workflow:**

```
Standards (CSP) → Agent 5 → Agent 6 → Agent 18 → Agent 19 → Agent 22
                  Standards Outline  Content  Questions  Validator
```

### Pipeline Status

| Stage | Agent | ID | Status | Output Format | Tested |
|-------|-------|----|---------|--------------| -------|
| 1 | Standards Analyzer | 5 | ✅ Working | JSON array with id/statement/skills | ✅ Yes |
| 2 | Outline Generator | 6 | ✅ Working | JSON units/lessons structure | ✅ Yes |
| 3 | Content Creator | 18 | ✅ Working | Plain text 800-1000 words | ✅ Yes |
| 4 | Question Generator | 19 | ⏭️ Ready | Structured text format | ⏸️ Not yet |
| 5 | Content Validator | 22 | ⏭️ Ready | QA validation report | ⏸️ Not yet |

**Progress:** 3/5 agents tested and operational

### Standards Generation (Agent 5)

**Test Results:**
- ✅ Authentication successful
- ✅ Generated 12 standards (S1-S12)
- ✅ Perfect JSON format
- ✅ Each standard has: id, statement, skills array
- ✅ Age-appropriate 3rd Grade Science content
- ⏱️ Response time: ~5-8 seconds

**Sample Output:**
```json
{
  "id": "S1",
  "statement": "Students will understand the basic structure of living organisms.",
  "skills": ["identify", "describe"]
}
```

---

## 📋 Database Tables

### Core Tables
- `users` - Updated with 'root' role, JWT tokens
- `agents` - Agent configurations (12 active)
- `student_advisors` - Per-student advisor instances (1:1 mapping)

### Course Content
- `course_drafts` - Course outlines in draft state
- `draft_lesson_content` - Lessons before approval
- `units` - Approved course units
- `lessons` - Approved individual lessons
- `educational_content` - AI-generated content store

### Assessment
- `quiz_questions` - Question pool (lesson/unit/final)
- `quiz_configurations` - Admin-configurable quiz settings
- `progress_tracking` - Student quiz attempts and scores

### Standards & Reference
- `scraped_content` - Educational standards from CSP API (legacy table)
- `educational_content` - All AI-generated lesson content

---

## 🔧 Recent Fixes Applied

### 1. Agent System Prompts Restored (Jan 3, 2026)

**Problem:** Agents generated content in inconsistent formats, breaking the pipeline.

**Solution:** Created comprehensive system prompts for all 7 agents with explicit output formats.

**File:** `insert_system_agents.sql`

**Agents Updated:**
- Agent 5: Standards Analyzer → JSON array output
- Agent 6: Outline Generator → JSON units/lessons structure  
- Agent 18: Content Creator → Plain text 800-1000 words
- Agent 19: Question Generator → Structured text format
- Agent 20: Quiz Creator → Balanced quiz assembly
- Agent 21: Unit Test Creator → Comprehensive assessments
- Agent 22: Content Validator → QA validation report

**Status:** ✅ DEPLOYED, agents generating proper output

### 2. Docker Database Port Configuration (Jan 2, 2026)

**Problem:** API container couldn't connect - "Connection refused"

**Root Cause:** 
- Host uses port 3307 (external mapping)
- Docker containers use port 3306 (internal network)
- Environment variable DB_PORT was missing

**Solution:** Added `DB_PORT: 3306` to API service in docker-compose.yml

**Verification:**
```bash
docker exec phef-api env | grep DB_PORT
# Output: DB_PORT=3306
```

**Status:** ✅ FIXED, all containers connecting properly

### 3. Single Database Consolidation (Jan 2, 2026)

**Problem:** PHP and C++ services using different databases (dual database syndrome)

**Solution:** Standardized on Docker MySQL (port 3307) as single source of truth

**Files Changed:**
- `config/database.php` - Changed to TCP connection on port 3307
- `cpp_agent/config.docker.json` - Connect to database:3306 (internal)

**Status:** ✅ RESOLVED, all services query same database

### 4. Quiz Grading System (Dec 13-14, 2025)

**Features:**
- ✅ Auto-grading for multiple choice (case-insensitive)
- ✅ AI-powered grading for short answer (Grading Agent, agent_id 15)
- ✅ Database persistence to `progress_tracking` table
- ✅ JWT authentication required
- ✅ Detailed scoring and feedback

**Status:** ✅ FULLY OPERATIONAL

### 5. Lesson Generation Fixes (Dec 30, 2025)

**Issues Fixed:**
- Relative path errors in course factory refactoring
- Table schema mismatch (content table column names)
- Agent service not finding content in `educational_content` table

**Status:** ✅ DEPLOYED, lessons saving correctly

---

## 🎯 System Capabilities

### Root Can:
✅ Create/edit/delete admin accounts  
✅ View all system activity  
✅ Direct database access  
✅ All admin capabilities  

### Admins Can:
✅ Create/modify expert agents  
✅ Retrieve educational standards (CSP API)  
✅ Review content for accuracy  
✅ Configure quiz settings  
✅ Generate course content with AI agents  
✅ Export training data for fine-tuning  
✅ Approve/reject course drafts  

### Students Can:
✅ Enroll in courses  
✅ Interact with expert agents  
✅ Take quizzes (randomized questions)  
✅ Track progress  
✅ Get personalized advisor (1:1 instance)  

---

## 🚀 Quick Start Workflow

### Step 1: Root Creates Admin
1. Login as root (http://localhost/Professor_Hawkeinstein/login.html)
2. Go to User Management
3. Create admin account
4. Provide admin credentials to team member

### Step 2: Admin Creates Course
1. Admin logs in
2. Go to Course Factory (http://factory.professorhawkeinstein.local)
3. Click "Create Course" → Start wizard
4. Enter grade level and subject
5. Agent 5 generates standards (5-8 seconds)
6. Agent 6 generates course outline (8-12 seconds)
7. Approve outline
8. Agent 18 generates lesson content (30-60 seconds per lesson)

### Step 3: Agent Generates Content
The agent will create:
- **Complete units** (broken into lessons)
- **Lesson content** (educational material, 800-1000 words)
- **100 quiz questions per lesson**
- **250 quiz questions per unit**
- **1000 quiz questions for final**

### Step 4: Configure Quizzes
Admin can adjust:
- Lesson quiz: **10 random from 100** (default)
- Unit test: **25 random from 250** (default)
- Final exam: **100 random from 1000** (default)

### Step 5: Students Enroll
1. Student creates account
2. Browse available courses
3. Enroll in course
4. Get assigned personal advisor (Professor Hawkeinstein)
5. Start lessons and take quizzes

---

## 📁 Key Files & Locations

### Configuration Files
- `/home/steve/Professor_Hawkeinstein/docker-compose.yml` - Container orchestration
- `/home/steve/Professor_Hawkeinstein/config/database.php` - PHP database config
- `/home/steve/Professor_Hawkeinstein/cpp_agent/config.docker.json` - C++ agent config
- `/home/steve/Professor_Hawkeinstein/.env` - Environment variables (CSP API key)

### SQL Scripts
- `/home/steve/Professor_Hawkeinstein/insert_system_agents.sql` - Agent prompt restoration
- `/home/steve/Professor_Hawkeinstein/schema.sql` - Full database schema
- `/home/steve/Professor_Hawkeinstein/create_student_advisors.sql` - Advisor system setup

### Service Scripts
- `/home/steve/Professor_Hawkeinstein/start_services.sh` - Start llama-server + agent_service
- `/home/steve/Professor_Hawkeinstein/complete_setup.sh` - Full system initialization
- `/home/steve/Professor_Hawkeinstein/Makefile` - Build commands (sync-web, test-sync)

### Documentation
- `/home/steve/Professor_Hawkeinstein/docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md` - Dev vs Prod rules
- `/home/steve/Professor_Hawkeinstein/docs/COURSE_GENERATION_ARCHITECTURE.md` - Agent pipeline specs
- `/home/steve/Professor_Hawkeinstein/docs/ADVISOR_INSTANCE_API.md` - Student advisor system
- `/home/steve/Professor_Hawkeinstein/docs/DEBUG_TROUBLESHOOTING.md` - Debugging guide
- `/home/steve/Professor_Hawkeinstein/docs/ERROR_HANDLING_GUIDE.md` - Error handling patterns

### Testing Scripts
- `/home/steve/Professor_Hawkeinstein/test_standards_generation.sh` - Test Agent 5
- `/home/steve/Professor_Hawkeinstein/test_outline_generation.sh` - Test Agent 6
- `/home/steve/Professor_Hawkeinstein/test_generate_lesson.php` - Test Agent 18
- `/home/steve/Professor_Hawkeinstein/test_db_connection.php` - Database connectivity

---

## 🔍 Health Checks

### Quick Status Check

```bash
# Check all services
docker-compose ps

# Database
mysql -h 127.0.0.1 -P 3307 -u root -p -e "SELECT VERSION();"

# LLM Server
curl http://localhost:8090/health

# Agent Service
curl http://localhost:8080/health

# PHP API
curl -X POST http://localhost:8081/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}'

# Apache logs
tail -f /var/log/apache2/course_factory_error.log
```

### Verify Agent Loading

```bash
# Check C++ agent logs
docker logs phef-agent 2>&1 | grep "Loaded agent"

# Test agent list endpoint
curl http://localhost:8080/agent/list | jq '.[] | {id, name}'

# Query database
mysql -h 127.0.0.1 -P 3307 -u root -p -e \
  "SELECT agent_id, agent_name, agent_type, is_active FROM professorhawkeinstein_platform.agents;"
```

---

## 🛠️ Restart Services

```bash
# Full restart (recommended)
cd /home/steve/Professor_Hawkeinstein
docker-compose down
docker-compose up -d

# Individual services
docker-compose restart agent-service
docker-compose restart llama-server
docker-compose restart api

# Apache (if not in Docker)
sudo systemctl restart apache2

# Check service status
docker-compose logs -f --tail=50
```

---

## 📊 Performance Metrics

### LLM Response Times
- **Standards Generation (Agent 5):** 5-8 seconds
- **Outline Generation (Agent 6):** 8-12 seconds
- **Lesson Content (Agent 18):** 30-60 seconds
- **Chat Messages:** 4-9 seconds

### System Resources
- **Database:** MariaDB 10.11 (~200MB RAM)
- **LLM Server:** llama.cpp with Qwen 2.5-1.5B (~2GB RAM)
- **Agent Service:** C++ (~50MB RAM)
- **PHP API:** Apache + PHP-FPM (~100MB RAM)

**Total:** ~2.5GB RAM for full stack

---

## 📚 Related Documentation

- [ARCHITECTURE.md](docs/ARCHITECTURE.md) - System architecture overview
- [COURSE_GENERATION_API.md](docs/COURSE_GENERATION_API.md) - Course API reference
- [AGENT_FACTORY_GUIDE.md](docs/AGENT_FACTORY_GUIDE.md) - Agent creation guide
- [FILE_SYNC_GUIDE.md](docs/FILE_SYNC_GUIDE.md) - Dev/Prod sync procedures
- [MEMORY_POLICY_QUICKREF.md](docs/MEMORY_POLICY_QUICKREF.md) - Memory management
- [ERROR_HANDLING_GUIDE.md](docs/ERROR_HANDLING_GUIDE.md) - Error handling patterns

---

## 🎯 Next Steps

### Immediate Priorities

1. ✅ Test Standards Analyzer (Agent 5) - COMPLETE
2. ✅ Test Outline Generator (Agent 6) - COMPLETE
3. ✅ Test Content Creator (Agent 18) - COMPLETE
4. ⏭️ Test Question Generator (Agent 19)
5. ⏭️ Test Content Validator (Agent 22)
6. ⏭️ End-to-end course creation test
7. ⏭️ Student enrollment and quiz flow test

### Future Improvements

See [FUTURE_IMPROVEMENTS.md](FUTURE_IMPROVEMENTS.md) for roadmap.
