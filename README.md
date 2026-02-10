# Professor Hawkeinstein Educational Platform

An AI-powered educational platform with automated course generation, personalized student advisors, and interactive workbooks. Built with local LLM inference using llama.cpp.

## Primary References

- [ARCHITECTURE.md](ARCHITECTURE.md) — Source of truth for subsystem boundaries and allowed responsibilities.
- [docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md) — Required dev/prod workflow and sync rules (read before editing files).
- [SECURITY.md](SECURITY.md) — Phase-by-phase record of the 2026 hardening work.
- [OAUTH_IMPLEMENTATION_COMPLETE.md](OAUTH_IMPLEMENTATION_COMPLETE.md) — Google OAuth configuration checklist and supporting docs.

## Development vs Production Environment

**Read before making changes:** [`docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md`](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md)

- **Development:** `/home/steve/Professor_Hawkeinstein` (not web-accessible)
- **Production:** `/var/www/html/basic_educational` (web-accessible)

Changes in DEV do not automatically sync to PROD. Deploy explicitly using `make sync-web`.

## Current Status (February 2026)

**Alpha Release** — Core features functional, tested, not production-ready. Admin onboarding now requires invitations plus Google SSO.

| Feature | Status |
|---------|--------|
| Two-subsystem architecture (Student Portal + Course Factory) | ✅ Working |
| C++ agent service with llama.cpp integration | ✅ Working |
| Live AI responses from LLM | ✅ Working (4-9 seconds) |
| Course generation from educational standards (5-agent pipeline) | ✅ Working |
| Student advisors with persistent memory | ✅ Working |
| Interactive workbook with lessons and questions | ✅ Working |
| Admin dashboard for course creation | ✅ Working |
| Invitation-only admin onboarding + Google SSO enforcement | ✅ Working |
| Admin OAuth audit logging and auth_provider_required enforcement | ✅ Working |
| Centralized security headers (CSP, HSTS, CORS allowlist) | ✅ Working |
| Docker deployment | ✅ Working |
| Quiz grading system (auto + AI-powered) | ✅ Working |
| Lesson quizzes and unit tests | ✅ Working |
| Video/multimedia content | ❌ Placeholders only |
| Vector similarity search (RAG) | ❌ Not implemented |

## Technology Stack

| Component | Technology | Port |
|-----------|------------|------|
| Frontend | HTML5, CSS3, vanilla JavaScript | - |
| Backend | PHP 8.0+ with JWT authentication | 80/443 (8081 in Docker) |
| Database | MariaDB 10.11+ Docker | 3307 (external), 3306 (internal) |
| Agent Service | C++ HTTP microservice | 8080 |
| LLM Server | llama-server (llama.cpp) | 8090 |
| Model | Qwen 2.5 1.5B Q4_K_M (quantized) | - |

## Authentication & Authorization

- **Subsystem boundaries:** The Student Portal and Course Factory run from separate directories and never share UI or authority. See [ARCHITECTURE.md](ARCHITECTURE.md) for the definitive rules.
- **JWT everywhere:** All PHP endpoints mint HS256 JWTs via `generateToken()` in [config/database.php](config/database.php). Tokens are stored in the `auth_token` cookie with `SameSite=Strict`; the `ENV` variable controls whether the cookie is marked `secure`.

### Student & Observer Login

- Username/password login flows (root site and `student_portal/`) post to [api/auth/login.php](api/auth/login.php). The endpoint applies `set_api_security_headers()`, enforces prepared statements, updates `last_login`, and logs failures.
- Google Sign-In buttons on [login.html](login.html) and [student_portal/login.html](student_portal/login.html) reuse the same OAuth endpoints described below. Accounts created without an invitation default to the `student` role.

### Course Factory Admin Access

- Bootstrap access is still provided by the root account that `setup_root_admin.sh` creates (username `root`, password `Root1234`). Change this password immediately after installation.
- All Course Factory API endpoints gate through `requireAdmin()` or `requireRoot()` in [api/admin/auth_check.php](api/admin/auth_check.php). Frontend pages import [course_factory/admin_auth.js](course_factory/admin_auth.js) to redirect anyone whose JWT role is not `admin` or `root`.

### Invitation-Only Admin Workflow

1. A root user calls [api/admin/invite_admin.php](api/admin/invite_admin.php) to generate a cryptographically secure token stored in `admin_invitations` with a seven-day expiry. Only `admin`, `staff`, or `root` roles can be requested.
2. Invitation status can be reviewed via [api/admin/list_invitations.php](api/admin/list_invitations.php) (GET with optional `pending_only=true`) and is surfaced in Course Factory UI.
3. Invitees land on [course_factory/admin_accept_invite.html](course_factory/admin_accept_invite.html), which validates the token through [api/admin/validate_invitation.php](api/admin/validate_invitation.php) before launching Google OAuth.
4. During OAuth, the invite token is injected into the state parameter by [api/auth/google/login.php](api/auth/google/login.php). The callback handler [api/auth/google/callback.php](api/auth/google/callback.php) verifies the state (10-minute TTL via the `oauth_states` table), ensures the Google email matches the invitation, upgrades the role, sets `auth_provider_required='google'`, and marks the invitation used.
5. All subsequent password attempts for these accounts are blocked by the provider check inside [api/auth/login.php](api/auth/login.php), forcing Google SSO for invited admins.

### OAuth 2.0 Details

- The implementation is server-side only using the League OAuth2 client. Minimal scopes (`openid email profile`) are requested.
- Helper functions in [config/database.php](config/database.php) handle state generation/storage, linking Google IDs, and writing audit rows to `auth_events` via `logAuthEvent()`.
- OAuth requests must use a `localhost` redirect URI during development (see `OAUTH_*` docs). Production domains belong on the allowlist before going live.

### Session Handling & Frontend API Calls

- The `admin_auth.js` helper injects the JWT into every `fetch` call via `getAuthHeaders()` and forces re-login if the token is missing.
- Student portal JS (`student_portal/app.js`) stores tokens separately for student-facing APIs so student and admin scopes never overlap.

## Security Posture

- The hardening work documented in [SECURITY.md](SECURITY.md) is applied project-wide. All API entrypoints start with `set_api_security_headers()` from [api/helpers/security_headers.php](api/helpers/security_headers.php), which adds CSP, X-Frame-Options, Permissions-Policy, HSTS (when `ENV=production` over HTTPS), and an environment-aware CORS allowlist.
- SQL access is through prepared statements only; legacy debug endpoints and raw queries were removed during Phase 1.
- Authentication events funnel through `logAuthEvent()` in [config/database.php](config/database.php) and persist to the `auth_events` table for auditability. Invitation creation and usage are logged as well.
- Error responses are standardized (no stack traces), while detailed context lands in `/tmp` logs or Apache error logs for administrators.
- Admin and root actions are separated from student APIs both in PHP (`requireAdmin()` / `requireRoot()`) and in frontend code (`admin_auth.js`), ensuring Course Factory tooling cannot call student data endpoints directly.
- Security headers, OAuth enforcement, and JWT cookies are all environment-aware so development remains practical without diluting production requirements.

## Architecture

```
Browser
   │
   ├── Student Portal (student_portal/)
   │   └── Dashboard, Workbook, Quiz, Login
   │
   └── Course Factory (course_factory/)
       └── Admin Dashboard, Course Wizard, Question Generator
           │
           ↓
    ┌──────────────┐
    │  PHP API     │ (api/)
    │  Port 80     │
    └──────┬───────┘
           │
           ↓
    ┌──────────────┐      ┌────────────────┐
    │ agent_service│ ───→ │  llama-server  │
    │  Port 8080   │      │   Port 8090    │
    └──────┬───────┘      └────────────────┘
           │
           ↓
    ┌──────────────┐
    │   MariaDB    │
    │  Port 3306   │
    └──────────────┘
```

### Subsystems

| Subsystem | Path | Purpose |
|-----------|------|---------|
| Student Portal | `/student_portal/` | Learning interface, advisor chat, workbooks |
| Course Factory | `/course_factory/` | Admin tools, course creation, content review |
| Shared | `/shared/`, `/api/`, `/config/` | Authentication, database, common utilities |

## Project Structure

```
Professor_Hawkeinstein/
├── start_services.sh           # Service startup script
├── schema.sql                  # Database schema
├── Makefile                    # Build and deployment
├── docker-compose.yml          # Container orchestration
│
├── student_portal/             # Student-facing subsystem
│   ├── student_dashboard.html
│   ├── workbook.html
│   ├── quiz.html
│   ├── login.html
│   ├── register.html
│   └── app.js
│
├── course_factory/             # Admin subsystem
│   ├── admin_dashboard.html
│   ├── admin_course_wizard.html
│   ├── admin_question_generator.html
│   ├── admin_agent_factory.html
│   └── admin_login.html
│
├── api/                        # PHP API endpoints
│   ├── admin/                  # Admin endpoints (50+, JWT required)
│   ├── agent/                  # Agent chat proxy
│   ├── auth/                   # Authentication
│   ├── course/                 # Course content
│   ├── student/                # Student endpoints
│   └── progress/               # Progress tracking
│
├── cpp_agent/                  # C++ agent microservice
│   ├── src/
│   │   ├── main.cpp
│   │   ├── http_server.cpp
│   │   ├── agent_manager.cpp
│   │   ├── llamacpp_client.cpp
│   │   ├── database.cpp
│   │   └── rag_engine.cpp
│   ├── include/
│   └── bin/agent_service
│
├── config/
│   └── database.php            # DB config, JWT settings
│
├── shared/                     # Shared utilities
│   ├── auth/
│   └── db/
│
├── docs/                       # Documentation
├── models/                     # LLM model files (not in git)
└── llama.cpp/                  # llama.cpp build
```

## Deployment Options

**There are TWO different ways to run this system:**

| Method | Use Case | Model Config | Services |
|--------|----------|--------------|----------|
| **Docker** | Recommended for production, isolated, portable | `docker-compose.yml` line 39 | `docker compose up -d` |
| **Native** | Development, debugging, direct access | `start_services.sh` line 18 | `./start_services.sh` |
| **Hybrid** | Current local dev setup | Mix of both | See below |

**⚠️ Important:** These methods are independent. Do not mix them. Choose one method and use it consistently.

### Current Local Deployment (Hybrid)

The current development environment uses a **HYBRID setup**:

**What's Native:**
- Apache web server on port 80 (professorhawkeinstein.local)
- Web files in `/var/www/html/basic_educational` (PROD)
- PHP executed by native Apache

**What's Docker:**
- Database (MariaDB) on port 3307
- LLM Server (llama-server) on port 8090
- Agent Service (C++) on port 8080

**How it works:**
1. Browser requests `http://professorhawkeinstein.local/student_portal/workbook.html`
2. Native Apache serves HTML/CSS/JS from `/var/www/html/basic_educational`
3. JavaScript calls PHP APIs at same domain
4. PHP connects to Docker services (DB at localhost:3307, Agent at localhost:8080)

**Important sync workflow:**
```bash
# 1. Edit files in DEV
nano /home/steve/Professor_Hawkeinstein/student_portal/unit_test.html

# 2. Sync to PROD
make sync-web

# 3. Hard refresh browser (Ctrl+Shift+R or disable cache in DevTools)
```

**⚠️ CRITICAL: Avoid Native Backend Conflicts**
```bash
# These should NOT be running (causes port conflicts with Docker)
ps aux | grep llama-server | grep -v docker  # Should be empty
ps aux | grep agent_service | grep -v docker # Should be empty

# If found, kill them:
pkill llama-server
pkill agent_service
```

### Model Configuration

**Docker method:** Edit `docker-compose.yml` line 39
```yaml
environment:
  - MODEL_FILE=qwen2.5-1.5b-instruct-q4_k_m.gguf  # Change model here
```

**Native method:** Edit `start_services.sh` line 18
```bash
ACTIVE_MODEL="qwen2.5-1.5b-instruct-q4_k_m.gguf"  # Change model here
```

**Hybrid method:** Uses Docker model (see docker-compose.yml)

## Google OAuth Configuration

1. Register a Web OAuth client in Google Cloud Console and add `http://localhost/api/auth/google/callback.php` as an authorized redirect URI for local testing (production domains must be HTTPS).
2. Populate `.env` with `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and (optionally) `GOOGLE_REDIRECT_URI`. These values are consumed by [api/auth/google/login.php](api/auth/google/login.php) and [api/auth/google/callback.php](api/auth/google/callback.php).
3. Ensure the database has run `migrations/add_oauth_support.sql` so the `oauth_states`, `auth_providers`, and `auth_events` tables exist.
4. Follow [OAUTH_IMPLEMENTATION_COMPLETE.md](OAUTH_IMPLEMENTATION_COMPLETE.md), [OAUTH_TESTING_GUIDE.md](OAUTH_TESTING_GUIDE.md), and [docs/OAUTH_LOCALHOST_SETUP.md](docs/OAUTH_LOCALHOST_SETUP.md) for validation steps.

Without a valid OAuth configuration, invitation-based admin onboarding will fail because invited accounts are forced to complete Google SSO.

## Quick Start

### Prerequisites

**Required:**
- Linux (tested on Ubuntu 20.04+)
- Docker & Docker Compose (v2.0+)
- 8GB+ RAM (for LLM inference)
- 10GB+ disk space (for models)

**Optional (for native deployment):**
- Apache 2.4+, PHP 8.0+, MariaDB 10.11+
- g++ with C++17 support
- libcurl, libjsoncpp, libmysqlclient development libraries

### Option 1: Docker Deployment (Recommended)

```bash
# 1. Clone repository
git clone https://github.com/stevemeierotto/Professor-Hawkeinstein.git
cd Professor_Hawkeinstein

# 2. Download LLM model (1.5GB)
mkdir -p models
cd models
wget https://huggingface.co/Qwen/Qwen2.5-1.5B-Instruct-GGUF/resolve/main/qwen2.5-1.5b-instruct-q4_k_m.gguf
cd ..

# 3. Create .env file
cat > .env << 'EOF'
DB_PASSWORD=BT1716lit
DB_USER=professorhawkeinstein_user
CSP_API_KEY=7RBswV2Rr3F9GmPPNCXc7wrV
EOF

# 4. Initialize database
docker-compose up -d database
sleep 10
docker exec -i phef-database mysql -u root -pRoot1234 < schema.sql

# 5. Start all services
docker-compose up -d

# 6. Verify services
docker-compose ps
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent-service
```

**Services started:**
- **phef-database** (MariaDB 10.11) - port 3307 (external)
- **phef-llama** (llama-server) - port 8090
- **phef-agent** (C++ service) - port 8080
- **phef-api** (PHP API) - port 8081

**Access URLs:**
- Student Portal: http://localhost:8081/student_portal/
- Admin Dashboard: http://localhost:8081/course_factory/admin_login.html
- Course Factory: http://localhost:8081/course_factory/

### Option 2: Native Deployment (Advanced)

**⚠️ Warning:** Native deployment is more complex and requires pre-installed services (Apache, PHP, MariaDB). Docker is recommended for testing and production.

**When to use native deployment:**
- Active development and debugging
- Need direct access to logs and processes
- Testing agent service changes without rebuilding containers
- Working on C++ agent code

**Pre-requisites:**
- Apache with PHP 8.0+ configured and running
- MariaDB installed and running (port 3306)
- llama.cpp compiled in `llama.cpp/build/bin/`
- C++ agent service compiled at `cpp_agent/build/bin/agent_service`
- Web root at `/var/www/html/basic_educational`

1. **Install dependencies:**
   ```bash
   # System packages
   sudo apt update
   sudo apt install -y apache2 php8.0 php8.0-mysql php8.0-curl php8.0-json
   sudo apt install -y mariadb-server mariadb-client
   sudo apt install -y g++ make cmake git
   sudo apt install -y libcurl4-openssl-dev libjsoncpp-dev libmysqlclient-dev
   
   # Enable Apache modules
   sudo a2enmod rewrite
   sudo a2enmod headers
   sudo systemctl restart apache2
   ```

2. **Download LLM model:**
   ```bash
   mkdir -p models
   cd models
   wget https://huggingface.co/Qwen/Qwen2.5-1.5B-Instruct-GGUF/resolve/main/qwen2.5-1.5b-instruct-q4_k_m.gguf
   cd ..
   ```

3. **Set up database:**
   ```bash
   sudo mysql -u root -p
   ```
   ```sql
   CREATE DATABASE professorhawkeinstein_platform;
   CREATE USER 'professorhawkeinstein_user'@'localhost' IDENTIFIED BY 'BT1716lit';
   GRANT ALL PRIVILEGES ON professorhawkeinstein_platform.* TO 'professorhawkeinstein_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```
   ```bash
   mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform < schema.sql
   ```

4. **Configure application:**
   ```bash
   # Create .env file
   cat > .env << 'EOF'
   DB_HOST=localhost
   DB_PORT=3306
   DB_USER=professorhawkeinstein_user
   DB_PASSWORD=BT1716lit
   CSP_API_KEY=7RBswV2Rr3F9GmPPNCXc7wrV
   EOF
   
   # Configure web server
   sudo cp Professor_Hawkeinstein.conf /etc/apache2/sites-available/
   sudo a2ensite Professor_Hawkeinstein
   sudo systemctl reload apache2
   ```

5. **Build llama.cpp:**
   ```bash
   cd llama.cpp
   make clean
   make -j4
   cd ..
   ```

6. **Build agent service:**
   ```bash
   cd cpp_agent
   make clean && make
   cd ..
   ```

7. **Start services:**
   ```bash
   # Start llama-server (in background)
   nohup ./llama.cpp/build/bin/llama-server \
       --model models/qwen2.5-1.5b-instruct-q4_k_m.gguf \
       --port 8090 --threads 4 --ctx-size 4096 --parallel 2 --cont-batching \
       > /tmp/llama_server.log 2>&1 &
   
   # Start agent service (in background)
   nohup ./cpp_agent/bin/agent_service > /tmp/agent_service_full.log 2>&1 &
   
   # Verify services
   curl http://localhost:8090/health
   curl http://localhost:8080/health
   ```

## Admin Onboarding Workflow

1. **Bootstrap root access.** Run [setup_root_admin.sh](setup_root_admin.sh) (or the equivalent SQL) to ensure the `root` account exists, then change its password after the first login.
2. **Invite administrators.** While authenticated as root, POST to [api/admin/invite_admin.php](api/admin/invite_admin.php) (or use the Course Factory UI) with the invitee's email and role. Tokens are stored for seven days in `admin_invitations`.
3. **Share the invite link.** The API response includes the `course_factory/admin_accept_invite.html?token=...` URL. Invitees can also see pending invitations via [api/admin/list_invitations.php](api/admin/list_invitations.php).
4. **Validate and launch OAuth.** The accept page calls [api/admin/validate_invitation.php](api/admin/validate_invitation.php) before initiating Google OAuth via [api/auth/google/login.php](api/auth/google/login.php).
5. **Complete Google SSO.** The callback at [api/auth/google/callback.php](api/auth/google/callback.php) enforces email matching, upgrades the role, marks the invitation used, and sets `auth_provider_required='google'` so password logins are blocked for that account.
6. **Operate Course Factory.** Newly provisioned admins must use the Google button on [course_factory/admin_login.html](course_factory/admin_login.html). JWTs are injected into API calls by [course_factory/admin_auth.js](course_factory/admin_auth.js).

This flow guarantees that Course Factory authority can only be granted through an auditable invitation approved by a root user.

### Default & Demo Accounts

- **Root bootstrap:** `root` / `Root1234` (created by [setup_root_admin.sh](setup_root_admin.sh)). Change this password before exposing the system outside of a lab environment.
- **Demo students:** Seed data is defined in [schema.sql](schema.sql) and updated by [migrations/012_fix_student_accounts.sql](migrations/012_fix_student_accounts.sql). Apply the migration to align `john_doe` and `Jack` test accounts with the documented demo passwords, or create fresh student records for your environment.

## API Reference

### Authentication

```bash
# Login
curl -X POST http://localhost/basic_eduautomated course creation:

| Agent | ID | Purpose | Status |
|-------|----|---------|--------|
| Standards Analyzer | 5 | Educational standards → JSON standards | ✅ Tested |
| Outline Generator | 6 | Standards → Course structure (units/lessons) | ✅ Tested |
| Content Creator | 18 | Lesson outlines → Full content (800-1000 words) | ✅ Tested |
| Question Generator | 19 | Lessons → Question banks | ⏳ Ready |
| Content Validator | 22 | QA check all generated content | ⏳ Ready |

**All agents use the same Qwen 2.5 1.5B model with different system prompts and parameters.**

### Pipeline Flow

```
1. Admin enters: Grade Level + Subject
   ↓
2. Agent 5 (Standards Analyzer) generates educational standards
   ↓
3. Agent 6 (Outline Generator) creates course outline (units/lessons)
   ↓
4. Admin approves outline
   ↓
5. Agent 18 (Content Creator) generates lesson content for each lesson
   ↓
6. Agent 19 (Question Generator) creates question banks
   ↓
7. Agent 22 (Content Validator) validates everything
   ↓
8. Admin publishes course
```
```bash
# Send message to agent
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"agentId":1,"message":"Hello"}'

# Response: {response, success}
```

### Student Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/student/get_advisor.php` | GET | Get student's personal advisor |
| `/api/student/update_advisor_data.php` | POST | Update conversation/progress |
| `/api/course/get_available_courses.php` | GET | List published courses |
| `/api/course/get_lesson_content.php` | GET | Fetch lesson content |

### Admin Endpoints (JWT Required)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/admin/generate_draft_outline.php` | POST | Create course outline |
| `/api/admin/generate_lesson_content.php` | POST | Generate lesson content |
| `/api/admin/generate_lesson_questions.php` | POST | Create question banks |
| `/api/admin/publish_course.php` | POST | Publish course |
| `/api/admin/list_courses.php` | GET | List all courses |
| `/api/admin/list_student_advisors.php` | GET | View advisor instances |
 Notes |
|---------|-------|-------|
| Model | Qwen 2.5 1.5B Q4_K_M | Single model for all agents |
| Model File | qwen2.5-1.5b-instruct-q4_k_m.gguf | 1.5GB download |
| Context | 4096 tokens | --ctx-size flag |
| Threads | 4 | Adjust based on CPU cores |
| Parallel Requests | 2 | --parallel flag |
| System Prompts | 200-400 chars | Optimized for speed |
| Max Tokens | 256 (chat), 1024-4096 (generation) | Agent-specific |
| Temperature | 0.3-0.7 | Agent-specific (factual vs creative) |
| Response Time | 4-9 seconds (chat), 30-60s (lessons) | Typical on 4-core CPU |
| Cache | Enabled | --cont-batching for prompt caching
## Course Generation Pipeline

The syst - Check all services
docker-compose ps

# Individual service logs
docker logs phef-database 2>&1 | tail -20
docker logs phef-llama 2>&1 | tail -20
docker logs phef-agent 2>&1 | tail -20
docker logs phef-api 2>&1 | tail -20

# Health checks
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent-service

# Agent list
curl http://localhost:8080/agent/list | jq
```

### View Logs

```bash
# Docker
docker-compose logs -f --tail=50

# Native
tail -f /tmp/llama_server.log
tail -f /tmp/agent_service_full.log
tail -f /var/log/apache2/error.log
tail -f /var/log/apache2/course_factory_error.log
```

### Common Issues

| Issue | Symptom | Solution |
|-------|---------|----------|
| Services won't start | Docker compose fails | Check ports 8080, 8090, 8081, 3307 aren't in use |
| 401 errors in admin | "Unauthorized" | Login at `/course_factory/admin_login.html`, check JWT token |
| Agent timeouts | Requests hang | Check agent logs: `docker logs phef-agent` |
| "Agent not found" | Agent ID doesn't exist | Verify agent loaded: `curl localhost:8080/agent/list` |
| Slow LLM responses | >30s per request | Check CPU usage, reduce `--threads` if high load |
| Database connection refused | Can't connect to DB | Verify DB_PORT (3306 internal, 3307 external) |
| Lessons don't save | No error, 0 records | Check `docker logs phef-api` for PHP errors |

### Database Access

```bash
# Docker (from host)
docker exec -it phef-database mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform

# Docker (root access)
docker exec -it phef-database mysql -uroot -pRoot1234 professorhawkeinstein_platform

# Native
mysql -h localhost -P 3306 -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform

# Check agent count
docker exec phef-database mysql -uroot -pRoot1234 professorhawkeinstein_platform \
  -e "SELECT agent_type, COUNT(*) FROM agents GROUP BY agent_type;"

### View Logs

```bash
tail -f /tmp/llama_server.log
tail -f /tmp/agent_service_full.log
tail -f /var/log/apache2/error.log
```

### Common Issues

| Issue | Solution |
|-------|----------|
| 401 errors | Check JWT token in browser sessionStorage |
| Agent timeouts | Check `/tmp/agent_service_full.log` |
| Slow LLM responses | Run `./clear_llm_cache.sh` |
| Database connection | Verify credentials in `config/database.php` |

### Database Access

```bash
# Docker
docker exec -it phef-database mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform

# Native
mysql -u professorhawkeinstein_user -p professorhawkeinstein_platform
```

## Known Limitations

1. **No vector search** - RAG uses basic text matching, not semantic similarity
2. **Single-node only** - No clustering or horizontal scaling
3. **Model size constraints** - Runs quantized models on consumer hardware
4. **No WebSocket** - Frontend uses polling for updates
5. **Multimedia placeholders** - Video/audio content not implemented

## Design Philosophy

### Grade-Based Complexity

**The grade level defines the complexity.** A 2nd grade science lesson is inherently appropriate for 2nd graders, and a 10th grade physics lesson is appropriate for 10th graders. 

This platform does not use "beginner/intermediate/advanced" or "easy/medium/hard" difficulty labels as they are redundant when the grade level already indicates the appropriate complexity level.

## Documentation

| Document | Purpose |
|----------|---------|
| [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) | Concise summary of subsystems, services, and current limitations |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Canonical reference for Student Portal vs Course Factory boundaries |
| [SECURITY.md](SECURITY.md) | Timeline of the 2026 security hardening phases and header/audit policies |
| [docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md) | Required workflow for DEV ⇄ PROD syncs (`make sync-web`) |
| [OAUTH_IMPLEMENTATION_COMPLETE.md](OAUTH_IMPLEMENTATION_COMPLETE.md) | Google OAuth design notes and configuration checklist |
| [OAUTH_TESTING_GUIDE.md](OAUTH_TESTING_GUIDE.md) | Step-by-step verification of OAuth login, database tables, and logs |
| [docs/OAUTH_LOCALHOST_SETUP.md](docs/OAUTH_LOCALHOST_SETUP.md) | Explains why OAuth must use `localhost` and how to configure Apache aliases |
| [docs/COURSE_GENERATION_API.md](docs/COURSE_GENERATION_API.md) | Admin API reference for the five-agent course builder |
| [docs/ADVISOR_INSTANCE_API.md](docs/ADVISOR_INSTANCE_API.md) | Student advisor storage and API contract |
| [FUTURE_IMPROVEMENTS.md](FUTURE_IMPROVEMENTS.md) | Roadmap items and open design questions |

## License

Proprietary - All rights reserved

## Repository

https://github.com/stevemeierotto/Professor-Hawkeinstein
