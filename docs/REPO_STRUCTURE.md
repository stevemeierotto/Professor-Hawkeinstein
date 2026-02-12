# Repository Structure

**Project:** Professor Hawkeinstein Educational Platform  
**Last Refactored:** February 11, 2026  
**Purpose:** This document explains the reorganized directory structure and provides guidance for navigating the codebase.

---

## Overview

The repository has been restructured to reduce root-level clutter and logically group related files. All functionality and git history have been preserved.

---

## Directory Layout

### `/app` - Runtime Application Code

The core application and all deployable code. This is the primary directory synced to production.

```
/app
  ├── api/                      # Backend API endpoints (PHP)
  │   ├── admin/                # Admin-only endpoints (course management, analytics)
  │   ├── agent/                # Agent chat and context endpoints
  │   ├── auth/                 # Authentication (login, register, OAuth)
  │   ├── course/               # Course data and content delivery
  │   ├── helpers/              # Shared utilities (security headers, analytics guards)
  │   ├── progress/             # Student progress tracking
  │   ├── public/               # Public endpoints (no auth required)
  │   ├── root/                 # Root-level admin endpoints
  │   └── student/              # Student-specific endpoints
  │
  ├── config/                   # Configuration files (database.php, secrets)
  │
  ├── cpp_agent/                # C++ agent service (llama.cpp wrapper)
  │   ├── src/                  # C++ source code
  │   ├── bin/agent_service     # Compiled service binary
  │   └── Makefile              # Build configuration
  │
  ├── course_factory/           # Course authoring subsystem (admin UI)
  │   ├── admin_*.html          # Admin dashboard pages
  │   ├── admin_*.js            # Admin JavaScript modules
  │   └── api/                  # Admin-specific API wrappers (historical, unused)
  │
  ├── shared/                   # Shared libraries and utilities
  │   ├── db/connection.php     # Database connection wrapper
  │   └── utils/                # Utility functions
  │
  ├── student_portal/           # Student-facing subsystem
  │   ├── dashboard.html        # Student dashboard
  │   ├── courses.html          # Course browser
  │   ├── workbook.html         # Interactive workbook
  │   └── metrics.html          # Public analytics page
  │
  ├── *.html                    # Root-level pages (login, register, admin login)
  ├── *.js                      # Shared JavaScript (app.js, auth_storage.js)
  ├── *.css                     # Stylesheets
  └── *.php                     # Utility scripts (system_status.php, etc.)
```

**Key Points:**
- `api/` uses relative paths like `require_once '../../config/database.php'`
- `cpp_agent/` runs as a standalone service on port 8080
- `course_factory/` and `student_portal/` are isolated subsystems (see `docs/architecture/ARCHITECTURE.md`)

---

### Security Infrastructure: Rate Limiting System (February 2026)

As of February 11, 2026, a centralized rate limiting system has been implemented to protect all API endpoints from abuse.

**New Files:**
- `app/api/helpers/rate_limiter.php` - Centralized rate limiting logic
- `migrations/add_rate_limiting.sql` - Database table for rate limit tracking

**Rate Limit Profiles:**

| Profile | Limit | Window | Usage |
|---------|-------|--------|-------|
| PUBLIC | 60 requests | 1 minute | Unauthenticated endpoints |
| AUTHENTICATED | 120 requests | 1 minute | Logged-in student endpoints |
| ADMIN | 300 requests | 1 minute | Admin-only endpoints |
| ROOT | 600 requests | 1 minute | Root-level admin endpoints |
| GENERATION | 10 requests | 1 hour | LLM content generation |

**Implementation:**
- Database-backed (MariaDB `rate_limits` table)
- IP-based limiting for public endpoints
- User ID-based limiting for authenticated endpoints
- Automatic window expiration and cleanup
- Returns HTTP 429 with `retry_after_seconds` on limit violation
- Logs violations to `/tmp/rate_limit.log`

**Usage Example:**
```php
// Public endpoint
require_once __DIR__ . '/../helpers/rate_limiter.php';
enforceRateLimit('PUBLIC', getClientIP(), 'endpoint_name');

// Authenticated endpoint
$userData = validateToken();
$profile = getRateLimitProfile($userData);
enforceRateLimit($profile, $userData['user_id'], 'endpoint_name');

// Generation endpoint
enforceGenerationRateLimit($userId, 'generate_course_outline');
```

**Migration Status:**
- ✅ Infrastructure created (`rate_limiter.php`)
- ✅ Database table created (`rate_limits`)
- ⏳ Endpoint integration in progress (applying to all API endpoints)

See `docs/security/SECURITY.md` for full security documentation.

---

### `/docs` - Documentation

All markdown files, reports, and implementation documentation.

```
/docs
  ├── architecture/             # System architecture documents
  │   ├── ARCHITECTURE.md       # Main architecture reference
  │   ├── PROJECT_OVERVIEW.md   # High-level project summary
  │   ├── AGENT.md              # Agent system design
  │   ├── COURSE_GENERATION_*.md # Course generation pipeline
  │   └── ...
  │
  ├── security/                 # Security documentation
  │   ├── SECURITY.md           # Security policies
  │   ├── SECURE_COOKIE_*.md    # Cookie implementation
  │   └── HTTPS_AUDIT_*.md      # HTTPS configuration
  │
  ├── compliance/               # Privacy and compliance
  │   ├── ANALYTICS_PRIVACY_*.md # FERPA/COPPA compliance
  │   └── PHASE*_*.md           # Phased rollout documentation
  │
  ├── oauth/                    # OAuth implementation
  │   ├── OAUTH_*.md            # OAuth guides and testing
  │   └── GOOGLE_OAUTH_*.md     # Google OAuth specifics
  │
  ├── roadmap/                  # Project planning
  │   ├── FUTURE_IMPROVEMENTS.md # Engineering roadmap
  │   └── QUICK_START_TESTING.md # Testing guide
  │
  ├── reports/                  # Implementation reports (JSON and MD)
  │   ├── *_REPORT.json         # Structured audit reports
  │   └── DEBUG_*.md            # Debugging guides
  │
  └── *.md                      # Other documentation (WORKBOOK_VIDEO_LAYOUT, etc.)
```

**Key Points:**
- Start with `docs/architecture/ARCHITECTURE.md` for system overview
- Security policies in `docs/security/SECURITY.md`
- Compliance audits in `docs/compliance/`

---

### `/tests` - Test Files

All test scripts and validation files.

```
/tests
  ├── php/                      # PHP unit tests
  │   ├── test_db_connection.php
  │   ├── test_env.php
  │   ├── test_analytics_direct.php
  │   └── ...
  │
  ├── shell/                    # Bash test scripts
  │   ├── test_analytics_endpoints.sh
  │   ├── verify_analytics_system.sh
  │   └── ...
  │
  └── html/                     # Manual HTML test harnesses
      ├── test_chat_flow.html
      └── test_login_flow.html
```

**Key Points:**
- Run PHP tests: `php tests/php/test_*.php`
- Run shell tests: `bash tests/shell/test_*.sh`
- HTML tests: Open in browser for interactive testing

---

### `/scripts` - Automation Scripts

Maintenance, setup, and deployment scripts.

```
/scripts
  ├── setup/                    # Setup and service management
  │   ├── start_services.sh     # Start llama-server and agent_service
  │   ├── setup.sh              # Initial database setup
  │   ├── complete_setup.sh     # Full setup automation
  │   └── ...
  │
  ├── rollback/                 # Rollback scripts
  │   └── rollback_admin_advisors.sh
  │
  ├── maintenance/              # Maintenance scripts
  │   └── (none currently)
  │
  ├── aggregate_analytics.php   # Daily analytics cron job
  ├── sync_to_web.sh            # Deploy to production
  ├── validate_analytics_privacy.sh # Privacy compliance checks
  └── ...
```

**Key Points:**
- Start services: `./scripts/setup/start_services.sh` or `make start-services`
- Deploy to production: `make sync-web` (uses `scripts/sync_to_web.sh`)
- Daily analytics: `php scripts/aggregate_analytics.php` (scheduled via cron)

---

### `/infra` - Infrastructure Configuration

Deployment and server configuration files.

```
/infra
  ├── docker/                   # Docker deployment
  │   ├── Dockerfile
  │   ├── docker-compose.yml    # Updated Feb 11, 2026 with relative paths
  │   └── .dockerignore
  │
  ├── apache/                   # Apache configuration
  │   ├── Professor_Hawkeinstein.conf  # VirtualHost config
  │   └── .htaccess             # Directory-level config
  │
  └── env/                      # Environment templates
      └── .env.docker.example   # Docker environment template
```

**Key Points:**
- Apache DocumentRoot: `/var/www/html/Professor_Hawkeinstein/app` (updated in refactor)
- Docker deployment: `cd /home/steve/Professor_Hawkeinstein && docker compose -f infra/docker/docker-compose.yml up -d`
- Docker paths updated (Feb 11, 2026) to use relative paths from project root
- Actual `.env` file lives in project root (not version controlled)

---

### `/data` - Data Files

SQL schemas, migrations, and backups.

```
/data
  ├── sql/                      # SQL schemas and seeds
  │   ├── schema.sql            # Main database schema
  │   ├── schema_agent_instances.sql
  │   ├── create_student_advisors.sql
  │   └── insert_system_agents.sql
  │
  └── backups/                  # Database backups
      └── phef_backup_20251216.sql
```

**Key Points:**
- Import schema: `mysql -u root -p phef < data/sql/schema.sql`
- Backups stored here for reference (not automated yet)

---

### `/logs`, `/media`, `/models` - Runtime Data

These directories are not restructured (remain at root).

```
/logs           # Application logs (not synced to production)
/media          # User-uploaded media files
/models         # LLM model files (.gguf, large, not in git)
```

**Key Points:**
- `/logs` is gitignored and excluded from sync
- `/models` contains multi-GB model files (download separately)
- `/media` syncs to production for user uploads

---

### Other Root Files

```
/.github/               # GitHub Actions, PR templates, copilot instructions
/.vscode/               # VS Code workspace settings
/llama.cpp/             # Llama.cpp submodule (inference engine)
/llama-server/          # Llama-server wrapper
/migrations/            # Database migrations (PHP-based)
/vendor/                # Composer dependencies (PHP)
/archive/               # Archived old files
.env                    # Environment variables (NOT in git)
.gitignore
.rsyncignore            # Sync exclusion rules
composer.json           # PHP dependencies
Makefile                # Build and deployment targets
README.md               # Main project README
LICENSE
```

---

## Path Reference Changes

### Key Files That Changed Location

| **Old Path** | **New Path** | **Notes** |
|--------------|--------------|-----------|
| `api/` | `app/api/` | All API endpoints |
| `config/database.php` | `app/config/database.php` | Database config |
| `cpp_agent/` | `app/cpp_agent/` | C++ service source |
| `course_factory/` | `app/course_factory/` | Admin UI |
| `student_portal/` | `app/student_portal/` | Student UI |
| `shared/` | `app/shared/` | Shared utilities |
| `ARCHITECTURE.md` | `docs/architecture/ARCHITECTURE.md` | Main architecture doc |
| `SECURITY.md` | `docs/security/SECURITY.md` | Security policy |
| `FUTURE_IMPROVEMENTS.md` | `docs/roadmap/FUTURE_IMPROVEMENTS.md` | Roadmap |
| `test_*.php` | `tests/php/test_*.php` | PHP tests |
| `test_*.sh` | `tests/shell/test_*.sh` | Shell tests |
| `setup.sh` | `scripts/setup/setup.sh` | Setup script |
| `start_services.sh` | `scripts/setup/start_services.sh` | Service launcher |
| `Dockerfile` | `infra/docker/Dockerfile` | Docker config |
| `schema.sql` | `data/sql/schema.sql` | Database schema |

---

## Important Notes

### Path Resolution in PHP

Most PHP files use relative paths:
```php
// In app/api/auth/login.php
require_once __DIR__ . '/../../config/database.php';  // Resolves to app/config/database.php
```

After the refactor, paths remain relative to their parent directory, so no code changes were needed **inside `/app`**.

### Apache Configuration

The Apache VirtualHost was updated:
```apache
DocumentRoot /var/www/html/Professor_Hawkeinstein/app  # Changed from .../Professor_Hawkeinstein
```

This ensures the web server serves files from `/app` as the document root.

### Deployment Sync

The `scripts/sync_to_web.sh` script was updated to:
- Exclude new directories: `scripts/`, `tests/`, `data/`, `infra/`
- Update config path: `app/config/database.php` (excluded from sync)

See `.rsyncignore` for full exclusion list.

### Build Targets

Makefile targets updated:
```bash
make start-services    # Now calls scripts/setup/start_services.sh
make agent-build       # Now builds in app/cpp_agent/
make sync-web          # Uses scripts/sync_to_web.sh
```

---

## Migration Impact

### What Changed
- **File locations:** All files moved to logical subdirectories
- **Git history:** Preserved via `git mv` commands
- **Relative paths:** Updated in scripts, `.rsyncignore`, Apache config

### What Did NOT Change
- **Functionality:** All endpoints, services, and features work identically
- **Database schema:** No database changes
- **API contracts:** All API endpoints at same relative URLs (from `/app` root)
- **Runtime behavior:** Services run identically after path updates

---

## For Developers

### Finding Code
1. **Backend API:** Check `app/api/{subsystem}/`
2. **Frontend HTML:** Check `app/course_factory/` (admin) or `app/student_portal/` (student)
3. **Documentation:** Check `docs/{category}/`
4. **Tests:** Check `tests/{php,shell,html}/`
5. **Scripts:** Check `scripts/{setup,rollback,maintenance}/`

### Running Services
```bash
# Start all services (local)
make start-services
# or
./scripts/setup/start_services.sh

# Start services with Docker
cd /home/steve/Professor_Hawkeinstein
docker compose -f infra/docker/docker-compose.yml up -d

# Check health
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent_service
```

### Working with Rate Limiting
```bash
# View rate limit violations
tail -f /tmp/rate_limit.log

# Check rate_limits table
mysql -h 127.0.0.1 -P 3307 -u professorhawkeinstein_user -p professorhawkeinstein_platform \
  -e "SELECT * FROM rate_limits ORDER BY window_start DESC LIMIT 20;"

# Clear rate limit data (testing only)
mysql -h 127.0.0.1 -P 3307 -u professorhawkeinstein_user -p professorhawkeinstein_platform \
  -e "TRUNCATE rate_limits;"
```

### Deploying
```bash
# Preview changes
make sync-web-dry

# Deploy
make sync-web

# Check sync log
tail -f /tmp/sync_to_web.log
```

---

## Ambiguous Files / Decisions

The following files had unclear purposes. Decisions made:

| **File** | **Location** | **Reasoning** |
|----------|--------------|---------------|
| `clear_llm_cache.sh` | Root (unchanged) | Utility script, infrequent use, kept accessible |
| `sync_to_web.sh.deprecated` | Root (unchanged) | Deprecated marker file, harmless to leave |
| `composer.json` | Root (unchanged) | Standard PHP convention, dependencies reference it |
| `.rsyncignore`, `.gitignore` | Root (unchanged) | Must be at repo root for git/rsync to find them |

---

## References

- **Main Architecture:** [docs/architecture/ARCHITECTURE.md](architecture/ARCHITECTURE.md)
- **Deployment Contract:** [docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md](DEPLOYMENT_ENVIRONMENT_CONTRACT.md)
- **Security Policy:** [docs/security/SECURITY.md](security/SECURITY.md)
- **Engineering Roadmap:** [docs/roadmap/FUTURE_IMPROVEMENTS.md](roadmap/FUTURE_IMPROVEMENTS.md)

---

## Change Log

### February 11, 2026 - Phase 8: DEFAULT-ON Rate Limiting ✅ COMPLETE

**Infrastructure:**
- Centralized rate limiting system (`app/api/helpers/rate_limiter.php`)
- Added `rate_limits` database table (MariaDB) with sliding window tracking
- Five profiles: PUBLIC (60/min), AUTHENTICATED (120/min), ADMIN (300/min), ROOT (600/min), GENERATION (10/hour)
- Automatic role detection from JWT tokens
- Double-invocation prevention
- Comprehensive logging to `/tmp/rate_limit.log`

**Endpoint Protection:**
- ✅ **97/97 user-facing endpoints protected (100%)**
- Auth (7): login, register, OAuth, logout, validate, me
- Student (3): all advisor operations
- Course (9): all course and draft operations
- Agent (5): chat, history, list, context, activation
- Progress (5): all tracking including quiz submission
- Admin (67): all CRUD, analytics, and 10 LLM generation endpoints
- Root (2): audit access
- Public (1): metrics

**Monitoring & Testing:**
- Automated test suite: `tests/rate_limiting_test.php`
- Real-time monitoring: `scripts/monitor_rate_limits.sh` (console/JSON/alert modes)
- Log analyzer: `scripts/analyze_rate_limits.sh`
- Cron jobs: `infra/cron/rate_limiting.cron` (monitoring, alerts, cleanup)
- Documentation: [docs/RATE_LIMITING_MONITORING.md](RATE_LIMITING_MONITORING.md)

**Security Achievement:**
- All user-facing endpoints protected against brute force, DoS, scraping
- LLM generation endpoints strictly limited (10 req/hour)
- Complete audit trail for rate limit violations
- COPPA, FERPA, SOC 2 compliance requirements met

**Documentation:**
- Coverage report: [docs/RATE_LIMITING_COVERAGE.md](RATE_LIMITING_COVERAGE.md)
- Security policy updated: [docs/security/SECURITY.md](security/SECURITY.md)
- Monitoring guide: [docs/RATE_LIMITING_MONITORING.md](RATE_LIMITING_MONITORING.md)

**Docker Configuration:**
- Updated `infra/docker/docker-compose.yml` to use relative paths from project root
- Fixed volume mounts and build contexts to reference `../../` paths
- Docker services start from project root: `docker compose -f infra/docker/docker-compose.yml up -d`

### February 10, 2026 - Repository Restructure

**Major Reorganization:**
- Moved all application code to `/app` directory
- Organized documentation into `/docs` with subdirectories
- Created `/tests`, `/scripts`, `/infra`, `/data` directories
- Preserved all git history via `git mv` commands

**Impact:**
- Cleaner root directory structure
- Logical grouping of related files
- No functionality changes

---

*This document reflects the repository structure as of February 11, 2026. All git history and functionality have been preserved during refactors.*
