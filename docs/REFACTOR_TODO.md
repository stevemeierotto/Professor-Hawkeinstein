# Architecture Refactor Todo List

**Source of Truth:** [ARCHITECTURE.md](ARCHITECTURE.md)  
**Generated:** December 28, 2025  
**Completed:** December 28, 2025  
**Status:** ✅ ALL PHASES COMPLETE

---

## Overview

This document describes the incremental refactoring of Professor Hawkeinstein into the two-subsystem architecture defined in ARCHITECTURE.md:

1. **Student Portal** (`/student_portal/`) - Learning and progress
2. **Course Factory** (`/course_factory/`) - Authoring and generation

**Guiding Principles:**
- Boundaries first, movement second
- No destructive changes without redirects
- Parallel structure before removal
- Every step must be verifiable
- Live system - must remain functional throughout

---

## Implementation Summary

### What Was Done
| Phase | Status | Summary |
|-------|--------|---------|
| Phase 0: Foundation | ✅ Complete | Created index.php stubs, API directories, URL inventory |
| Phase 1: Shared Infrastructure | ✅ Complete | Created /shared/auth/ and /shared/db/ utilities |
| Phase 2: Course Factory | ✅ Complete | Copied 14 admin HTML files, created 67 API proxies |
| Phase 3: Student Portal | ✅ Complete | Copied 11 student files, created 23 API proxies |
| Phase 4: Subdomains | ✅ Complete | Apache vhosts for app.* and factory.* |
| Phase 5: Redirects | ✅ Complete | .htaccess 301 redirects from root |
| Phase 6: Cleanup | ✅ Complete | 21 files archived to /archive/ |

### Current Directory Structure
| Directory | Status | Contents |
|-----------|--------|----------|
| `/student_portal/` | ✅ Active | 11 HTML/JS/CSS files + 23 API proxies |
| `/course_factory/` | ✅ Active | 14 HTML files + 67 API proxies + styles.css, auth_storage.js |
| `/shared/` | ✅ Active | /auth/jwt.php, middleware.php + /db/connection.php |
| `/api/` | ✅ Active | Original APIs (still functional) |
| `/config/` | ✅ Active | Database config (shared) |
| `/config/apache/` | ✅ New | Subdomain vhost configurations |
| `/archive/` | ✅ New | 21 archived root-level files |

### Access URLs
| URL | Destination |
|-----|-------------|
| `http://app.professorhawkeinstein.local/` | Student Portal |
| `http://factory.professorhawkeinstein.local/` | Course Factory |
| `http://professorhawkeinstein.local/admin_*` | 301 → Course Factory |
| `http://professorhawkeinstein.local/student_*` | 301 → Student Portal |
| `http://localhost:8081/` | Docker direct (Student Portal) |

---

## Detailed Phase Checklist (All Complete)

## PHASE 0: Foundation ✅

- [x] Create `/student_portal/index.php` - Redirects to student_dashboard.html
- [x] Create `/course_factory/index.php` - Redirects to admin_dashboard.html
- [x] Create `/student_portal/api/` with README
- [x] Create `/course_factory/api/` with README
- [x] Create `docs/URL_INVENTORY.md` listing all URLs

## PHASE 1: Shared Infrastructure ✅

- [x] Create `docs/SHARED_DEPENDENCIES.md`
- [x] Create `/shared/auth/jwt.php` - JWT functions extracted
- [x] Create `/shared/auth/middleware.php` - Auth middleware
- [x] Create `/shared/db/connection.php` - Database wrapper

## PHASE 2: Course Factory Boundary ✅

- [x] Copy all `admin_*.html` (14 files) to `/course_factory/`
- [x] Copy `admin_auth.js`, `styles.css`, `auth_storage.js` to `/course_factory/`
- [x] Create 67 API proxy files in `/course_factory/api/`
- [x] Create `/course_factory/api/auth/` layer

## PHASE 3: Student Portal Boundary ✅

- [x] Copy 11 student files to `/student_portal/` (HTML, JS, CSS)
- [x] Create 23 API proxy files in `/student_portal/api/`
- [x] Create `/student_portal/api/auth/` layer

## PHASE 4: Subdomain Configuration ✅

- [x] Create `config/apache/app.professorhawkeinstein.conf` for Student Portal
- [x] Create `config/apache/factory.professorhawkeinstein.conf` for Course Factory
- [x] Add /etc/hosts entries for local testing
- [x] Enable vhosts with a2ensite

## PHASE 5: Redirect Implementation ✅

- [x] Add 301 redirects to `.htaccess` for all admin_*.html → /course_factory/
- [x] Add 301 redirects for student files → /student_portal/

## PHASE 6: Cleanup ✅

- [x] Archive 21 root-level HTML files to `/archive/root_files_backup_20251228/`
- [x] Verify redirects still work
- [x] Update index.php files to redirect to actual pages

---

## Verification Results (All Passing)

### Student Portal ✅
- [x] Lives at `/student_portal/` and `app.` subdomain
- [x] Can authenticate students
- [x] Can view courses (read-only)
- [x] Can track progress

### Course Factory ✅
- [x] Lives at `/course_factory/` and `factory.` subdomain
- [x] Can authenticate factory admins
- [x] Can create and edit courses
- [x] Can generate content via agents

### Shared Infrastructure ✅
- [x] `/shared/` contains only neutral utilities
- [x] `/api/` contains original APIs (still functional)
- [x] `/config/` contains only configuration

---

## Rollback Procedures (If Needed)

### Full Rollback
```bash
# Restore archived files
cp -r archive/root_files_backup_20251228/* .

# Remove redirect rules from .htaccess
# Disable new Apache vhosts
sudo a2dissite app.professorhawkeinstein.conf factory.professorhawkeinstein.conf
sudo systemctl reload apache2

# Remove /etc/hosts entries
# Delete subsystem directories if needed
```

---

## Future Work (Deferred)

### Database Schema Changes
- Consider adding `subsystem` column to relevant tables
- Consider audit logging by subsystem

### Authentication Token Scopes
- Separate JWT scopes for Student vs Factory
- Subdomain-isolated cookies

### Observer Role Implementation
- Observer authentication endpoints in Student Portal only
- Read-only dashboard views
