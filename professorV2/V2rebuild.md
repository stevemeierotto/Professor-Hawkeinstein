# Professor Hawkeinstein V2 – Controlled Rebuild Specification

## Context

We are creating a clean V2 implementation of Professor Hawkeinstein inside a new directory (`Professor_Hawkeinstein_v2/`) while keeping V1 frozen as a reference. This is **not a rewrite from scratch** – we are extracting proven components, eliminating dead code, fixing architectural fragility, and implementing a Docker-only deployment model.

---

## Core Principles

1. **No Speculative Features**: Only implement what exists and works in V1
2. **Docker-Only**: Eliminate the hybrid native/Docker split entirely
3. **Single Source of Truth**: No DEV/PROD directory split
4. **Keep What Works**: Auth, OAuth, analytics, RAG foundations are solid
5. **Phase-Gated Development**: Must complete and validate each phase before proceeding

---

## What to Keep (Proven Components)

### **1. Authentication & Authorization** ✅
- JWT-based session management
- Google OAuth SSO integration
- Invitation-only admin onboarding
- Role-based access control (student/admin)
- Session timeout and refresh logic
- **Location in V1**: `app/api/auth/`, `app/shared/auth/`

### **2. Database Schema** ✅
- Core entities: users, courses, lessons, advisor_instances
- Analytics tables with privacy controls
- OAuth support tables (oauth_states, auth_providers, auth_events)
- RAG embeddings infrastructure (content_embeddings with VECTOR support)
- **Location in V1**: `data/sql/schema.sql`, `migrations/`

### **3. Agent System** ✅
- C++ agent service (port 8080)
- LLM inference via llama-server (port 8090)
- System agents (Career Explorer, Math Tutor, Writing Coach, Course Generation Pipeline agents)
- **Location in V1**: `app/cpp_agent/`, system agent definitions

### **4. Analytics & Compliance** ✅
- Event tracking with PII masking
- Daily aggregation pipeline
- Privacy-compliant audit logging
- **Location in V1**: `app/api/analytics/`, audit_logs table

### **5. Core Student Features** ✅
- Workbook interface with video sidebar
- Advisor chat (WebSocket)
- Progress tracking
- Quiz grading (auto + AI-powered)
- **Location in V1**: `app/student_portal/`

### **6. Core Admin Features** ✅
- Course Factory dashboard
- System agent management
- Analytics dashboard
- Course generation pipeline
- **Location in V1**: `app/course_factory/`

### **7. Docker Web Container (phef-api)** ✅
- Apache + PHP 8.0 in Docker container
- Already configured for internal Docker networking (`DB_PORT=3306`)
- Volume-mounted application code
- Currently running but underutilized in hybrid setup
- **Location in V1**: Running on port 8081, defined in `docker-compose.yml`
- **Purpose in V2**: This becomes the PRIMARY web server, eliminating native Apache
- **Critical**: This container is NOT dead code - it's the solution to the hybrid problem

---

## What to Remove (Dead Code)

- ❌ All biometric references (deprecated)
- ❌ Web scraping code (deprecated)
- ❌ **Native Apache deployment** (`/var/www/html/basic_educational`)
- ❌ **All references to native Apache on port 80**
- ❌ `make sync-web` and DEV→PROD sync logic
- ❌ `scripts/sync_to_web.sh`
- ❌ Hybrid Docker/native service scripts
- ❌ `start_services.sh` (superseded by docker-compose)
- ❌ Any localhost/127.0.0.1 hardcoded confusion
- ❌ Model configuration in multiple locations
- ❌ Separate `config.json` and `config.docker.json` files
- ❌ PROD directory at `/var/www/html/basic_educational`
- ❌ DEV directory distinction (everything is DEV and PROD simultaneously)

---

## Architecture Changes

### **Before (V1 Hybrid - Fragile)**
```
Native Apache (port 80)
  ↓ serves from /var/www/html/basic_educational
  ↓ requires manual sync: make sync-web
  ↓ connects to:
Docker: Database (3307 external, 3306 internal)
Docker: LLM (8090)
Docker: Agent (8080)
Docker: phef-api (8081) - RUNNING BUT UNUSED

Manual sync required: make sync-web
Two database connection modes: localhost vs 127.0.0.1
```

### **After (V2 Docker-Only - Clean)**
```
Docker: Web (phef-api container, port 80)
  ↓ volume-mounted from ./app
  ↓ no sync needed, changes instant
  ↓ connects via Docker network:
Docker: Database (3306 internal, 3307 external for CLI debugging)
Docker: LLM (8090 internal)
Docker: Agent (8080 internal)

No sync needed: volume mounts handle it
Single database access method: service names
```

---

## Environment Structure

### **V1 (Current - Fragile)**
```
/home/steve/Professor_Hawkeinstein/     # DEV
/var/www/html/basic_educational/        # PROD
(requires manual sync, two sources of truth)
```

### **V2 (Target - Clean)**
```
/home/steve/Professor_Hawkeinstein_v2/
  ├── app/                  # All application code
  │   ├── api/              # PHP API endpoints
  │   ├── config/           # Configuration
  │   ├── cpp_agent/        # C++ agent service
  │   ├── course_factory/   # Admin UI
  │   ├── student_portal/   # Student UI
  │   └── shared/           # Shared utilities
  ├── docker-compose.yml    # Single orchestration file
  ├── .env                  # Single config source
  ├── data/                 # SQL schemas
  ├── models/               # LLM models
  └── docs/                 # Documentation

(single source of truth, volume-mounted to containers)
```

---

## Phased Implementation Plan

### **Phase 1: Foundation (Week 1)**
**Goal**: Docker environment + database + basic auth

**Deliverables**:

1. **Update docker-compose.yml** with 4 services:
   ```yaml
   services:
     web:
       # Use existing phef-api configuration
       container_name: phef-api
       build: ./infra/apache  # or use php:8.0-apache
       ports:
         - "80:80"  # Map to port 80 instead of 8081
       volumes:
         - ./app:/var/www/html  # Direct mount, no sync
       environment:
         - DB_HOST=database
         - DB_PORT=3306
       depends_on:
         - database
       networks:
         - phef-network

     database:
       container_name: phef-database
       image: mariadb:10.11
       ports:
         - "3307:3306"  # External access for debugging only
       environment:
         - MYSQL_ROOT_PASSWORD=Root1234
         - MYSQL_DATABASE=professorhawkeinstein_platform
         - MYSQL_USER=professorhawkeinstein_user
         - MYSQL_PASSWORD=BT1716lit
       command: --plugin_load_add=ha_vector --plugin-maturity=alpha
       volumes:
         - db-data:/var/lib/mysql
       networks:
         - phef-network

     llama:
       container_name: phef-llama
       build: ./infra/llama
       ports:
         - "8090:8090"
       volumes:
         - ./models:/models
       environment:
         - MODEL_FILE=qwen2.5-1.5b-instruct-q4_k_m.gguf
       networks:
         - phef-network

     agent:
       container_name: phef-agent
       build: ./app/cpp_agent
       ports:
         - "8080:8080"
       volumes:
         - ./models:/app/models
       environment:
         - DB_HOST=database
         - DB_PORT=3306
         - LLM_HOST=llama
         - LLM_PORT=8090
       depends_on:
         - database
         - llama
       networks:
         - phef-network

   networks:
     phef-network:
       driver: bridge

   volumes:
     db-data:
   ```

2. **Directory structure**:
   ```
   Professor_Hawkeinstein_v2/
   ├── app/
   │   ├── api/
   │   │   └── auth/
   │   │       ├── login.php
   │   │       ├── logout.php
   │   │       └── verify.php
   │   ├── config/
   │   │   └── database.php  # ONLY uses DB_HOST and DB_PORT env vars
   │   └── shared/
   │       ├── auth/
   │       │   └── jwt_helper.php
   │       └── db/
   │           └── connection.php
   ├── infra/
   │   ├── apache/
   │   │   └── Dockerfile
   │   └── llama/
   │       └── Dockerfile
   ├── docker-compose.yml
   ├── .env.example
   └── README.md
   ```

3. **Critical configuration rules**:
   ```php
   // app/config/database.php
   // ✅ CORRECT - reads from environment
   define('DB_HOST', getenv('DB_HOST') ?: 'database');  // Docker service name
   define('DB_PORT', getenv('DB_PORT') ?: '3306');      // Internal port
   define('DB_USER', getenv('DB_USER') ?: 'professorhawkeinstein_user');
   define('DB_PASS', getenv('DB_PASS') ?: 'BT1716lit');
   define('DB_NAME', getenv('DB_NAME') ?: 'professorhawkeinstein_platform');

   // ❌ NEVER use localhost or 127.0.0.1 in Docker
   // ❌ NEVER use port 3307 inside containers
   ```

4. **Validation Criteria**:
   - [ ] All services start with `docker compose up -d`
   - [ ] Database accessible on `localhost:3307` from host machine
   - [ ] Web accessible at `http://localhost/api/auth/login.php`
   - [ ] `/api/auth/login.php` returns JWT token
   - [ ] `/api/auth/verify.php` validates tokens
   - [ ] No native Apache processes running
   - [ ] No manual sync commands exist

**Validation Commands**:
```bash
# Should show 4 containers, all healthy
docker compose ps

# Should return empty (no native processes)
ps aux | grep -E "apache2|httpd" | grep -v docker | grep -v grep

# Should work (database accessible externally)
mysql -h 127.0.0.1 -P 3307 -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT 1;"

# Should work (API responds)
curl http://localhost/api/auth/login.php -d '{"username":"test","password":"test"}'

# Should fail (nothing on port 80 outside Docker)
curl http://localhost:80 --connect-timeout 2
```

**Do NOT proceed to Phase 2 until all validation passes.**

---

### **Phase 2: OAuth + Admin Access (Week 2)**
**Goal**: Google OAuth working, admin can log in

**Deliverables**:

1. **OAuth flow implementation**:
   - `/api/auth/google/login.php`
   - `/api/auth/google/callback.php`
   - State validation, CSRF protection
   - **Copy from V1**: `app/api/auth/google/` (proven implementation)

2. **Admin dashboard skeleton**:
   - Basic Course Factory UI
   - Session-protected routes
   - **Copy from V1**: `app/course_factory/admin_login.html`, `app/course_factory/admin_dashboard.html`

3. **Database migrations**:
   - `oauth_states` table
   - `auth_providers` table
   - `auth_events` audit table
   - **Copy from V1**: `migrations/add_oauth_support.sql`

4. **Environment variables**:
   ```bash
   # .env
   GOOGLE_CLIENT_ID=your_client_id
   GOOGLE_CLIENT_SECRET=your_client_secret
   GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback.php
   JWT_SECRET=your_secret_key
   ```

5. **Validation Criteria**:
   - [ ] Google OAuth login flow completes
   - [ ] Admin dashboard accessible after login at `http://localhost/course_factory/admin_dashboard.html`
   - [ ] Invalid tokens rejected with 401
   - [ ] OAuth state reuse prevented
   - [ ] Audit events logged to database
   - [ ] Can test entire flow: Google login → callback → JWT → dashboard

**Validation Commands**:
```bash
# Verify OAuth tables exist
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform \
  -e "SHOW TABLES LIKE 'oauth_%';"

# Should show: oauth_states

# Verify auth events table
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform \
  -e "SELECT COUNT(*) FROM auth_events;"

# Test OAuth redirect (should redirect to Google)
curl -I http://localhost/api/auth/google/login.php
```

**Do NOT proceed to Phase 3 until OAuth is bulletproof.**

---

### **Phase 3: Agent System (Week 3)**
**Goal**: Advisor chat working end-to-end

**Deliverables**:

1. **C++ agent service in Docker**:
   - **Copy from V1**: `app/cpp_agent/` (entire directory)
   - Update `Dockerfile` if needed
   - Single config file (no more `config.json` vs `config.docker.json`)
   - Connects to `llama:8090` via Docker network
   - Connects to `database:3306` via Docker network
   - Exposes `/chat`, `/health`, `/agent/list` endpoints

2. **System agents defined**:
   - **Copy from V1**: Agent definitions from database
   - Career Explorer (agent_id 1)
   - Math Tutor (agent_id 2)
   - Writing Coach (agent_id 3)
   - Course Generation Pipeline agents (5, 6, 18, 19, 20, 21, 22)
   - **Location**: `data/sql/insert_system_agents.sql`

3. **Student Portal chat interface**:
   - **Copy from V1**: `app/student_portal/workbook.html`, `workbook_app.js`
   - WebSocket connection to agent
   - Message history display
   - Context awareness
   - Video sidebar layout

4. **LLM Configuration**:
   ```yaml
   # docker-compose.yml
   llama:
     environment:
       - MODEL_FILE=qwen2.5-1.5b-instruct-q4_k_m.gguf
       - THREADS=4
       - CONTEXT_SIZE=2048
   ```

5. **Validation Criteria**:
   - [ ] Agent service starts without errors
   - [ ] `curl http://localhost:8080/health` returns 200
   - [ ] `curl http://localhost:8080/agent/list` returns all agents
   - [ ] LLM inference working (test with simple prompt)
   - [ ] Chat API endpoint functional: `http://localhost/api/agent/chat.php`
   - [ ] Chat history persists to database
   - [ ] Timeout and error handling works
   - [ ] Can complete full flow: student login → workbook → chat → response

**Validation Commands**:
```bash
# Check agent service logs
docker logs phef-agent --tail 50

# Check LLM server logs
docker logs phef-llama --tail 50

# Verify agents loaded
curl http://localhost:8080/agent/list | jq

# Test agent endpoint
curl -X POST http://localhost:8080/chat \
  -H "Content-Type: application/json" \
  -d '{"agent_id": 1, "message": "Hello"}'

# Verify database connection from agent
docker exec phef-agent cat /app/logs/agent_service.log | grep "Connected to database"
```

**Do NOT proceed to Phase 4 until agents respond correctly.**

---

### **Phase 4: Analytics + RAG Foundation (Week 4)**
**Goal**: Event tracking + embeddings infrastructure

**Deliverables**:

1. **Analytics pipeline**:
   - **Copy from V1**: `app/api/analytics/` directory
   - Event logging API (`/api/analytics/log_event.php`)
   - PII masking for sensitive data
   - Daily aggregation cron job (`scripts/aggregate_analytics.php`)
   - Privacy compliance validation script

2. **Analytics tables**:
   - **Copy from V1**: Analytics migrations
   - `analytics_events`
   - `analytics_daily_summary`
   - `analytics_course_metrics`
   - `analytics_user_engagement`
   - Plus 5 other analytics tables

3. **RAG infrastructure**:
   - **Copy from V1**: `migrations/add_rag_embeddings.sql`
   - `content_embeddings` table with VECTOR(384) type
   - Embedding generation pipeline (placeholder for now)
   - Vector similarity search query template

4. **MariaDB Vector Plugin**:
   ```yaml
   # docker-compose.yml
   database:
     command: --plugin_load_add=ha_vector --plugin-maturity=alpha
   ```

5. **Validation Criteria**:
   - [ ] Events logged without PII exposure
   - [ ] `curl http://localhost/api/analytics/log_event.php` works
   - [ ] Aggregation job runs: `docker exec phef-api php /var/www/html/scripts/aggregate_analytics.php`
   - [ ] Vector plugin loaded: `SHOW PLUGINS LIKE 'vector';`
   - [ ] Distance queries return results
   - [ ] Privacy validation passes: `./scripts/validate_analytics_privacy.sh`

**Validation Commands**:
```bash
# Verify vector plugin
docker exec phef-database mysql -u root -pRoot1234 professorhawkeinstein_platform \
  -e "SHOW PLUGINS LIKE 'vector';"

# Verify analytics tables
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform \
  -e "SHOW TABLES LIKE 'analytics_%';"

# Should show 9 tables

# Test vector search (placeholder query)
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform \
  -e "SELECT COUNT(*) FROM content_embeddings;"

# Run aggregation manually
docker exec phef-api php /var/www/html/scripts/aggregate_analytics.php
```

---

### **Phase 5: Production Deployment & Migration (Week 5)**
**Goal**: V2 ready for real use, V1 data migrated

**Deliverables**:

1. **Production docker-compose**:
   - Environment variable overrides for production
   - Volume persistence configuration
   - Resource limits (CPU/memory)
   - Health checks for all services
   - Restart policies

2. **Migration scripts**:
   ```bash
   # scripts/migrate_v1_to_v2.sh
   # 1. Export V1 database
   # 2. Import to V2
   # 3. Validate data integrity
   # 4. Copy uploaded media files
   # 5. Verify user accounts
   ```

3. **Data validation**:
   - All users migrated
   - All courses migrated
   - All progress tracking migrated
   - All analytics history migrated
   - OAuth tokens invalidated (force re-auth)

4. **Documentation**:
   - `README.md` - Quick start guide
   - `DEPLOYMENT.md` - Deployment procedures
   - `ARCHITECTURE.md` - System architecture
   - `TROUBLESHOOTING.md` - Common issues
   - `.env.example` - Environment variable template

5. **Validation Criteria**:
   - [ ] All V1 features functional in V2
   - [ ] Database migrated successfully
   - [ ] No hybrid deployment artifacts remain
   - [ ] Single `docker compose up -d` starts everything
   - [ ] No manual sync commands anywhere
   - [ ] All documentation accurate
   - [ ] Can destroy V1 without breaking anything

**Final Validation**:
```bash
# Start from scratch
docker compose down -v
docker compose up -d

# Wait for services
sleep 30

# Import schema
docker exec -i phef-database mysql -u root -pRoot1234 professorhawkeinstein_platform < data/sql/schema.sql

# Verify all services
docker compose ps  # All should be healthy

# Test full flow
curl http://localhost/api/auth/login.php
curl http://localhost:8080/agent/list
curl http://localhost:8090/health

# Access in browser
# http://localhost/student_portal/
# http://localhost/course_factory/admin_login.html
```

---

## Critical Constraints

### **Docker Network Rules**
- Services communicate via service names: `database:3306`, `llama:8090`, `agent:8080`
- **NEVER** use `localhost` or `127.0.0.1` in inter-service communication
- External access only via mapped ports (for debugging only)
- Web container must use service names in all API calls

### **Configuration Rules**
- Single `.env` file at project root
- **NO** separate `config.json` and `config.docker.json`
- Model name defined **once** in `docker-compose.yml` or `.env`
- Database credentials in `.env`, never hardcoded
- All config reads from environment variables first

### **File Organization Rules**
- All application code in `app/`
- All Docker configs in `infra/` or project root
- **NO** `/var/www` paths anywhere in code
- **NO** sync scripts anywhere
- **NO** references to "DEV" or "PROD" environments

### **Volume Mount Rules**
```yaml
# docker-compose.yml
services:
  web:
    volumes:
      - ./app:/var/www/html  # Direct mount
      # NOT: ./app:/tmp/app with sync script
```

### **Port Mapping Rules**
```yaml
# External:Internal
ports:
  - "80:80"      # web - primary access
  - "3307:3306"  # database - CLI debugging only
  - "8080:8080"  # agent - debugging only
  - "8090:8090"  # llama - debugging only

# Services talk internally via:
# - database:3306 (not localhost:3307)
# - llama:8090 (not localhost:8090)
# - agent:8080 (not localhost:8080)
```

### **Version Control Rules**
- Git branch: `v2-rebuild`
- V1 branch: `main` (frozen, no new features)
- Each phase = separate commit with tag
- Tag format: `v2-phase1-complete`, `v2-phase2-complete`, etc.
- Commit message format: `[V2 Phase X] Description`

### **No Hybrid Artifacts**
Remove all traces of:
- ❌ Native Apache configs
- ❌ `/var/www/html/basic_educational/`
- ❌ `make sync-web`
- ❌ `scripts/sync_to_web.sh`
- ❌ References to "PROD" or "production" directory
- ❌ Two config files per service
- ❌ `start_services.sh`

---

## Validation Checkpoints

### **After Each Phase**

Run this validation script:
```bash
#!/bin/bash
# scripts/validate_phase.sh <phase_number>

PHASE=$1

echo "=== Phase $PHASE Validation ==="

# 1. Docker health
echo "Checking Docker services..."
docker compose ps | grep -q "healthy" || { echo "❌ Services not healthy"; exit 1; }

# 2. No native processes
echo "Checking for native processes..."
ps aux | grep -E "apache2|httpd|llama-server|agent_service" | grep -v docker | grep -v grep && { echo "❌ Native processes found"; exit 1; }

# 3. Database connectivity
echo "Checking database..."
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform -e "SELECT 1;" > /dev/null || { echo "❌ Database connection failed"; exit 1; }

# 4. Web server
echo "Checking web server..."
curl -f http://localhost/ > /dev/null || { echo "❌ Web server not responding"; exit 1; }

# Phase-specific checks
case $PHASE in
  1)
    curl -f http://localhost/api/auth/login.php > /dev/null || { echo "❌ Auth API not working"; exit 1; }
    ;;
  2)
    curl -I http://localhost/api/auth/google/login.php | grep -q "302" || { echo "❌ OAuth redirect not working"; exit 1; }
    ;;
  3)
    curl -f http://localhost:8080/agent/list > /dev/null || { echo "❌ Agent service not responding"; exit 1; }
    ;;
  4)
    docker exec phef-database mysql -u root -pRoot1234 professorhawkeinstein_platform -e "SHOW PLUGINS LIKE 'vector';" | grep -q "vector" || { echo "❌ Vector plugin not loaded"; exit 1; }
    ;;
  5)
    # Full integration test
    echo "Running full integration test..."
    # Add comprehensive checks here
    ;;
esac

echo "✅ Phase $PHASE validation passed"
```

**DO NOT PROCEED** if any validation fails.

---

## Success Metrics

V2 is complete when:

1. ✅ Zero native services required
2. ✅ Single `docker compose up -d` starts everything
3. ✅ No DEV/PROD directory split
4. ✅ All V1 core features working
5. ✅ Documentation accurate and complete
6. ✅ Database migration path validated
7. ✅ Can edit code and see changes instantly (no sync)
8. ✅ All inter-service communication via Docker network
9. ✅ Can destroy and rebuild in under 5 minutes
10. ✅ New developer can run system from README in under 30 minutes

---

## What NOT to Do

- ❌ Add new features during rebuild
- ❌ Change database schema unless fixing bugs
- ❌ Optimize prematurely
- ❌ Skip validation checkpoints
- ❌ Work on multiple phases simultaneously
- ❌ Delete V1 before V2 is fully validated
- ❌ Mix native and Docker services
- ❌ Create separate DEV/PROD environments
- ❌ Introduce new dependencies without justification
- ❌ Write new code when V1 code works

---

## Common Pitfalls to Avoid

### **Pitfall 1: localhost vs service names**
```php
// ❌ WRONG - breaks in Docker
$db_host = 'localhost';
$llm_url = 'http://localhost:8090';

// ✅ CORRECT - works in Docker
$db_host = getenv('DB_HOST') ?: 'database';
$llm_url = 'http://' . (getenv('LLM_HOST') ?: 'llama') . ':8090';
```

### **Pitfall 2: Port confusion**
```yaml
# ❌ WRONG - using external port internally
environment:
  - DB_PORT=3307  # This is the EXTERNAL port

# ✅ CORRECT - using internal port
environment:
  - DB_PORT=3306  # This is the INTERNAL port
```

### **Pitfall 3: Missing dependencies**
```yaml
# ❌ WRONG - agent starts before database ready
services:
  agent:
    ports:
      - "8080:8080"

# ✅ CORRECT - explicit dependencies
services:
  agent:
    depends_on:
      - database
      - llama
```

### **Pitfall 4: Hardcoded paths**
```php
// ❌ WRONG - assumes specific directory structure
require_once '/var/www/html/basic_educational/config/database.php';

// ✅ CORRECT - relative to current file
require_once __DIR__ . '/../../config/database.php';
```

---

## Questions Before Starting

Before you begin Phase 1, answer these:

1. **Which phase do you want to start with?** (Recommend Phase 1)
2. **Do you have a fresh Ubuntu VM to test V2 on?** (Recommended to avoid conflicts)
3. **Have you backed up V1 database?** (Required before migration)
4. **Are you comfortable with Docker networking concepts?** (Review if needed)
5. **Do you want CI/CD integration from the start?** (Can add in Phase 5)
6. **Do you have the LLM model file downloaded?** (`qwen2.5-1.5b-instruct-q4_k_m.gguf`, 1.5GB)

---

## Emergency Rollback Plan

If V2 fails catastrophically:

```bash
# 1. Stop V2
cd /home/steve/Professor_Hawkeinstein_v2
docker compose down

# 2. Restart V1
cd /home/steve/Professor_Hawkeinstein
docker compose up -d
sudo systemctl start apache2

# 3. Verify V1 working
curl http://professorhawkeinstein.local

# 4. Debug V2 offline
cd /home/steve/Professor_Hawkeinstein_v2
docker compose logs > debug.log
```

---

## Final Checklist Before Release

- [ ] All 5 phases validated
- [ ] V1 database migrated successfully
- [ ] All V1 features working in V2
- [ ] No sync scripts exist
- [ ] No native Apache references
- [ ] Documentation complete and accurate
- [ ] Can start from scratch in under 5 minutes
- [ ] New developer can follow README successfully
- [ ] All services use Docker network for communication
- [ ] All configuration in `.env` file
- [ ] Git repository clean (no sensitive data)
- [ ] `.gitignore` properly configured
- [ ] V1 backed up and frozen

---

## Summary

This is a **controlled consolidation**, not a rewrite:

- **Keep**: Auth, OAuth, agents, analytics, database schema, phef-api container
- **Remove**: Native Apache, DEV/PROD split, sync scripts, hybrid complexity
- **Fix**: Use phef-api as primary web server, Docker-only architecture, single config source
- **Result**: Clean, maintainable system that's easier to deploy and debug

The fragility was never in your code - it was in the deployment model. V2 fixes that by embracing Docker fully and eliminating the hybrid split.

**Your instinct to rebuild was correct. This plan makes it controlled and safe.**
