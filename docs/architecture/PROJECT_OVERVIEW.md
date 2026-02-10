# Professor Hawkeinstein - Educational Platform

## Summary

A web-based educational system with AI tutoring built on LAMP stack (Linux, Apache, MariaDB, PHP) with a C++ microservice for LLM inference. The system uses llama.cpp for local AI model inference - all Ollama references have been removed.

**Architecture:** Browser → PHP/Apache → C++ Agent Service (port 8080) → llama-server (port 8090)

## Authentication & Access Control (February 2026)

- **Student & Observer access** continues to use username/password login through [api/auth/login.php](api/auth/login.php) with HS256 JWTs. Google OAuth is available on every login page; accounts created without an invitation default to the `student` role.
- **Course Factory admins** are provisioned exclusively through invitations. Root users call [api/admin/invite_admin.php](api/admin/invite_admin.php) to mint tokens stored in `admin_invitations` (7-day expiry). Invitees land on [course_factory/admin_accept_invite.html](course_factory/admin_accept_invite.html), validate the token, and must complete Google SSO via [api/auth/google/login.php](api/auth/google/login.php) and [api/auth/google/callback.php](api/auth/google/callback.php).
- During callback, the system enforces email matching, upgrades the role, writes audit rows via `logAuthEvent()` (stored in `auth_events`), and sets `auth_provider_required='google'`. Subsequent password attempts for that user are blocked inside [api/auth/login.php](api/auth/login.php).
- All admin endpoints enforce JWT scopes in [api/admin/auth_check.php](api/admin/auth_check.php), and the frontend guard in [course_factory/admin_auth.js](course_factory/admin_auth.js) refuses to load pages for non-admin roles.
- **Bootstrap access:** the `root` account (created by [setup_root_admin.sh](setup_root_admin.sh)) still exists for emergency and provisioning work. Change its password after installation; additional admins must use the invitation flow.

## Security Highlights

- Every API endpoint invokes `set_api_security_headers()` from [api/helpers/security_headers.php](api/helpers/security_headers.php), applying CSP, HSTS (production HTTPS only), `X-Frame-Options: DENY`, a strict Permissions-Policy, and an allowlisted CORS configuration.
- Phased hardening documented in [SECURITY.md](SECURITY.md) removed legacy debug endpoints, required prepared statements, standardized error responses, and centralized logging.
- OAuth state tokens live in the `oauth_states` table with a 10-minute TTL. All auth activity (password or Google) is written to `auth_events` for audit trails.
- Admin invitations, acceptance attempts, and role upgrades are logged through `logActivity()` calls, giving visibility into approval workflows.
- Environment separation is enforced operationally: edit code in `/home/steve/Professor_Hawkeinstein`, then run `make sync-web` to deploy to `/var/www/html/basic_educational` as described in [docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md).

## System Architecture

The project is organized as a **two-subsystem architecture**:

1. **Student Portal** (`/student_portal/`) - Student-facing dashboard, chat, workbooks, quizzes
2. **Course Factory** (`/course_factory/`) - Admin tools for course creation and agent management
3. **Shared Infrastructure** (`/shared/`, `/api/`, `/config/`) - Authentication, database, common utilities

### Service Components

| Component | Port | Purpose |
|-----------|------|---------|
| Apache/PHP | 80/443 | Web server, authentication, database operations |
| agent_service | 8080 | C++ microservice for agent orchestration |
| llama-server | 8090 | llama.cpp HTTP server for LLM inference |
| MariaDB | 3306 | Database (users, agents, courses, memories) |

## Current Implementation Status

### ✅ Fully Implemented and Working

**C++ Agent Service (`cpp_agent/`)**
- HTTP server using cpp-httplib
- LlamaCppClient that communicates with llama-server via HTTP API
- Agent manager with conversation context and memory
- RAG engine structure (basic implementation)
- Database connector for agent/memory operations
- Endpoints: `/health`, `/agent/chat`, `/agent/list`, `/api/chat`

**LLM Integration**
- llama-server runs with configurable models (default: llama-2-7b-chat.Q4_0.gguf)
- Multi-model support available via `MULTI_MODEL=1` flag
- Live AI responses (not mock data) when services are running
- Response times: 4-9 seconds for typical chat messages

**PHP Backend API (`api/`)**
- `api/auth/` - JWT-based authentication (login, logout, validate)
- `api/agent/` - Proxy to C++ service (chat, history, list)
- `api/admin/` - Admin endpoints with JWT auth requirement (50+ endpoints)
- `api/student/` - Student advisor management (ensure_advisor, get_advisor, update_advisor_data)
- `api/course/` - Course metadata and content management
- `api/progress/` - Progress tracking endpoints

**Student Portal (`student_portal/`)**
- `student_dashboard.html` - Main student interface with live agent chat
- `workbook.html` - Interactive workbook with quizzes
- `quiz.html` - Quiz interface
- `login.html`, `register.html` - Authentication pages
- `app.js` - Frontend utilities for agent communication

**Course Factory (`course_factory/`)**
- Admin dashboard for course management
- Agent factory for creating/editing agents
- Course wizard and editor
- Analytics dashboard (`admin_analytics.html`, `admin_analytics.js`)
- Question generator interface
- System agent management

**Database Schema (`schema.sql`)**
- Users table with role-based access (student, admin, root)
- Agents table with model configuration and system prompts
- Agent memories table for conversation history
- RAG documents and embeddings tables
- Courses, course_assignments, progress_tracking tables
- Student advisors system (per-student advisor instances)
- Analytics tables (daily rollups, course metrics, agent performance, time-series)
- Invitation system (`admin_invitations` table)
- OAuth tables (`oauth_states`, `auth_providers`, `auth_events`)
- Audit logging infrastructure

**Authentication System**
- JWT-based authentication for all API endpoints
- Admin endpoints require `requireAdmin()` middleware
- Root user provisions additional admins through the invitation workflow (`admin_invitations` table + Google SSO requirement)
- Google OAuth Authorization Code flow implemented server-side with state storage, audit logging, and role upgrades
- Session management via PHP plus secure cookies (`SameSite=Strict`, `ENV`-aware `secure` flag)
- Authentication event logging to `auth_events` table via `logAuthEvent()`

**Course Generation System (5-Agent Pipeline)**
- ✅ **Agent 1 (Standards Analyzer):** Generates educational standards from grade/subject input
- ✅ **Agent 2 (Outline Generator):** Converts standards into structured course outline (units + lessons)
- ✅ **Agent 3 (Content Creator):** Generates full lesson content (800-1000 words) from outlines
- ✅ **Agent 4 (Question Generator):** Creates question banks (fill-in-blank, multiple choice, essay)
- ✅ **Agent 5 (Assessment Generator):** Produces unit tests, midterms, and final exams
- Complete APIs: `generate_standards.php`, `generate_draft_outline.php`, `generate_lesson_content.php`, `generate_lesson_questions.php`, `generate_assessment.php`
- Documentation: `docs/COURSE_GENERATION_ARCHITECTURE.md`, `docs/COURSE_GENERATION_API.md`, `docs/ASSESSMENT_GENERATION_API.md`

**Analytics System (January 2026)**
- Aggregate metrics tables (`analytics_daily_rollup`, `analytics_course_metrics`, `analytics_agent_metrics`, `analytics_user_snapshots`, `analytics_timeseries`, `analytics_public_metrics`)
- Daily aggregation ETL script (`scripts/aggregate_analytics.php`) with cron scheduling
- Admin analytics dashboard (`course_factory/admin_analytics.html`) with Chart.js visualizations
- Public metrics page (`student_portal/metrics.html`) - no authentication required
- Time-series API (`api/admin/analytics/timeseries.php`) for daily/weekly/monthly trends
- Export API (`api/admin/analytics/export.php`) with CSV/JSON formats - anonymized (hashed user IDs)
- Rate limiting on public endpoints (`api/helpers/analytics_rate_limiter.php`) - 60 req/min public, 300 req/min admin
- FERPA/COPPA-compliant design (no PII in analytics tables, documented in `docs/ANALYTICS_PRIVACY_VALIDATION.md`)

**Audit System (February 2026)**
- Role-based audit access: admin (summary stats only), root (full log access)
- Audit endpoints: `api/admin/audit/summary.php`, `api/root/audit/logs.php`
- Privacy enforcement auditing (PII blocks, cohort suppressions, rate limit violations tracked)
- Audit access logging to `/tmp/audit_access.log`
- Documentation: `docs/PHASE6_AUDIT_ACCESS.md`

**Security Infrastructure (January 2026)**
- Centralized security headers (`api/helpers/security_headers.php`): CSP, HSTS, X-Frame-Options, Permissions-Policy
- Secure httpOnly cookies with `SameSite=Lax` and environment-aware `secure` flag
- HTTPS support via mkcert for localhost development (`docs/HTTPS_AUDIT_REPORT.md`)
- CORS configuration (currently `*` for dev; production requires allowlist)
- OAuth state management (10-minute TTL in `oauth_states` table)
- Invitation-only admin onboarding with email verification and forced Google SSO
- All authentication events logged to `auth_events` for audit trail

### ⏳ Partially Implemented

**RAG System**
- Database tables exist for rag_documents and embeddings
- RAGEngine class exists in C++ but similarity search is placeholder
- Document chunking not fully implemented
- Embedding generation requires external setup

**Progress Tracking Visualization**
- Database schema complete
- API endpoints exist
- Frontend visualization present but not fully connected to real-time data

### ❌ Not Yet Implemented

- Vector similarity search (MariaDB vector plugin not configured)
- Actual embedding generation and storage
- Real-time progress updates via WebSocket (current implementation uses polling)
- Multimedia content delivery (video/audio placeholders only)
- Student placement testing
- Automated agent assignment based on assessment
- Two-factor authentication (MFA/TOTP) for admin accounts
- WCAG 2.1 accessibility compliance audit
- GDPR/CCPA data access portal for students/parents

## File Structure

```
Professor_Hawkeinstein/
├── start_services.sh           # Service startup script
├── schema.sql                  # Database schema
├── Makefile                    # Build and deployment commands
│
├── student_portal/             # Student-facing subsystem
│   ├── student_dashboard.html
│   ├── workbook.html
│   ├── quiz.html
│   ├── login.html
│   ├── register.html
│   ├── app.js
│   └── api/                    # Student-specific API proxies
│
├── course_factory/             # Admin/course creation subsystem
│   ├── admin_dashboard.html
│   ├── admin_course_wizard.html
│   ├── admin_agent_factory.html
│   ├── admin_login.html
│   └── api/                    # Course factory API proxies
│
├── shared/                     # Shared infrastructure
│   ├── auth/
│   └── db/
│
├── api/                        # Main API endpoints
│   ├── admin/                  # Admin-only endpoints (JWT required)
│   │   ├── auth_check.php      # requireAdmin() middleware
│   │   ├── generate_*.php      # Content generation endpoints
│   │   ├── list_*.php          # Listing endpoints
│   │   └── ...                 # 50+ admin endpoints
│   ├── agent/                  # Agent communication
│   │   ├── chat.php            # Proxy to C++ service
│   │   ├── list.php
│   │   └── history.php
│   ├── auth/                   # Authentication
│   │   ├── login.php
│   │   ├── logout.php
│   │   └── validate.php
│   ├── course/                 # Course content
│   │   ├── CourseMetadata.php  # Course file handling
│   │   └── courses/            # Course JSON files
│   ├── student/                # Student-specific APIs
│   │   ├── ensure_advisor.php
│   │   ├── get_advisor.php
│   │   └── update_advisor_data.php
│   └── progress/               # Progress tracking
│
├── cpp_agent/                  # C++ microservice
│   ├── src/
│   │   ├── main.cpp
│   │   ├── http_server.cpp
│   │   ├── agent_manager.cpp
│   │   ├── llamacpp_client.cpp # HTTP client to llama-server
│   │   ├── database.cpp
│   │   └── rag_engine.cpp
│   ├── include/                # Header files
│   ├── bin/agent_service       # Compiled binary
│   └── Makefile
│
├── config/
│   └── database.php            # DB config, JWT settings, helpers
│
├── models/                     # LLM model files (not in git)
│   └── *.gguf                  # Model files
│
├── llama.cpp/                  # llama.cpp source (submodule/build)
│   └── build/bin/llama-server
│
├── docs/                       # Documentation
│   ├── ARCHITECTURE.md
│   ├── COURSE_GENERATION_API.md
│   ├── ADVISOR_INSTANCE_API.md
│   └── ...
│
└── migrations/                 # Database migrations
```

## Running the System

### Prerequisites
- Linux (tested on Ubuntu)
- Apache 2.4+ with PHP 8.0+
- MariaDB 10.7+
- g++ with C++17 support
- libcurl, jsoncpp, mysqlclient libraries

### Starting Services

```bash
# Start all services (llama-server + agent_service)
./start_services.sh

# Or with multi-model support (requires more RAM)
MULTI_MODEL=1 ./start_services.sh
```

### Building C++ Agent Service

```bash
cd cpp_agent
make clean && make
```

### Health Checks

```bash
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent_service
```

## Configuration

### Environment Variables
- `CSP_API_KEY` - Common Standards Project API key (for standards retrieval)
- `MULTI_MODEL` - Set to 1 for multi-model mode
- `ACTIVE_MODEL` - Model file to use in single-model mode

### Key Config Files
- `config/database.php` - Database credentials, JWT secrets
- `cpp_agent/config.json` - Agent service configuration
- `.env` - Environment variables (not in git)

## API Endpoints

### Agent Endpoints
- `POST /api/agent/chat.php` - Send message to agent
- `GET /api/agent/list.php` - List available agents

### Admin Endpoints (require JWT)
- `POST /api/admin/generate_course_outline.php` - Generate course outline
- `POST /api/admin/generate_lesson_content.php` - Generate lesson
- `GET /api/admin/list_courses.php` - List courses
- See `docs/COURSE_GENERATION_API.md` for full list

### Student Endpoints
- `GET /api/student/get_advisor.php` - Get student's advisor
- `POST /api/student/update_advisor_data.php` - Update conversation/progress

## Known Limitations

1. **No vector search** - RAG relies on basic text matching, not semantic similarity
2. **Single-node deployment** - No clustering or horizontal scaling
3. **Model size constraints** - Runs on consumer hardware with quantized models
4. **No real-time sync** - Frontend polling, not WebSocket
5. **Limited multimedia** - Video/audio placeholders only
6. **No 2FA** - Password and OAuth authentication implemented, but TOTP/SMS multi-factor authentication not present
7. **Rate limiting gaps** - Public analytics endpoints have rate limiting; admin invitation and course generation endpoints do not
8. **No accessibility audit** - WCAG 2.1 compliance not formally tested
9. **No GDPR/CCPA data portal** - No mechanism for students/parents to download personal data on request

## Development Notes

### Deployment Environment
- **Development**: `/home/steve/Professor_Hawkeinstein`
- **Production**: `/var/www/html/basic_educational`
- Use `make sync-web` to deploy changes (see `docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md`)

### Testing
- Test files in `tests/` directory
- Manual testing scripts: `test_*.sh`
- Health check endpoints for service verification

### Logs
- llama-server: `/tmp/llama_server.log`
- agent_service: `/tmp/agent_service_full.log`
- Apache: `/var/log/apache2/error.log`

## Documentation

| Document | Purpose |
|----------|---------|
| `docs/ARCHITECTURE.md` | System architecture details |
| `docs/COURSE_GENERATION_API.md` | Course generation endpoints |
| `docs/ADVISOR_INSTANCE_API.md` | Student advisor system |
| `docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md` | Dev/prod separation rules |
| `docs/REFACTOR_TODO.md` | Pending refactoring tasks |
| `README.md` | Setup and installation guide |
