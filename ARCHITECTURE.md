# Professor Hawkeinstein – System Architecture Overview

## Purpose of This Document

This document defines the **intentional architectural split** of the Professor Hawkeinstein platform into two independent but related subsystems:

1. **Student Portal** – learning, progress, and observation
2. **Course Factory** – curriculum authoring and generation

This file is intended to be a **stable reference point** for humans and AI copilots during refactors, migrations, and feature development. Any architectural decision should be checked against this document.

---

## High-Level Architecture

The system is a **single codebase** that hosts **multiple applications**, separated by responsibility and deployed via **subdomains**.

```text
Student Portal   → https://app.professorhawkeinstein.org
Course Factory   → https://factory.professorhawkeinstein.org
```

Both applications:

* Share infrastructure (database, models, agent runtime)
* Do NOT share UI, authentication, or authority

---

## Subsystem 1: Student Portal

**Primary Purpose**

* Deliver learning experiences to students
* Track progress and mastery
* Provide read-only insight to parents and teachers

**Who Uses It**

* Students
* Observers (parents, teachers)

**Responsibilities**

* Authentication for students and observers
* Course consumption (view lessons, activities)
* Tutor-style AI agent interaction
* Progress tracking (mastery, completion, time)
* Observer dashboards (read-only)

**Explicitly Must NOT**

* Create or modify courses
* Generate curriculum or questions
* Edit standards or pacing
* Access Course Factory UI

**Directory Anchor**

```text
/student_portal/
```

Observers are implemented as a **role within the Student Portal**, not as system administrators.

---

## Subsystem 2: Course Factory

**Primary Purpose**

* Author and generate educational content
* Manage curriculum pipelines
* Configure system and authoring agents

**Who Uses It**

* Platform owner
* Curriculum designers
* Authorized content contributors

**Responsibilities**

* Course creation and editing
* Lesson and outline generation
* Standards alignment
* Question bank generation
* Agent configuration for authoring

**Explicitly Must NOT**

* Access individual student identities
* Read or modify student progress
* Authenticate students or observers
* Act as a learning environment

**Directory Anchor**

```text
/course_factory/
```

Files currently prefixed with `admin_*` are considered **Course Factory UI**, even if they are temporarily located at the repository root.

---

## Shared Infrastructure

Some components are intentionally shared but must remain **neutral**.

**Shared Components**

* Database schema and migrations
* Agent runtime and models
* API utilities
* Configuration and secrets

**Directory Anchors**

```text
/api/
/shared/
/config/
/cpp_agent/
/models/
```

Shared code must not contain business logic specific to either subsystem.

---

## Authentication & Authority Boundaries

| Area        | Student Portal         | Course Factory     |
| ----------- | ---------------------- | ------------------ |
| Login       | Students & Observers   | Factory Admins     |
| Tokens      | Separate scope         | Separate scope     |
| Cookies     | Subdomain-isolated     | Subdomain-isolated |
| Permissions | Role-based, read/write | Authoring-only     |

There is **no global admin role**.

Observers are **visibility-only** and cannot perform actions.

---

## Deployment Model

* Single repository
* Single server (initially)
* Multiple subdomains
* Separate document roots per subdomain

This allows future separation into multiple servers or repositories **without architectural changes**.

---

## Migration & Refactor Rules (Critical)

1. **Boundaries first, movement second**
2. Do not break existing URLs without redirects
3. Do not merge observer and admin concepts
4. Course Factory must never depend on student data
5. Student Portal must never modify curriculum

---

## Checkpoints for Copilot / Human Review

During refactors, periodically verify:

* [ ] Is this change clearly inside one subsystem?
* [ ] Does this violate a stated MUST NOT rule?
* [ ] Does this introduce cross-authority access?
* [ ] Can this be explained using this document?

If the answer is unclear, STOP and reassess before continuing.

---

## Source of Truth

This document is the **architectural source of truth** for Professor Hawkeinstein.

If code, prompts, or tooling conflict with this file, the conflict must be resolved explicitly — not ignored.

---

## Technical Architecture Details

### Three-Tier System

```
Frontend (HTML/JS) → PHP API (JWT auth) → C++ service :8080 → llama-server :8090
                      ↓
                   MariaDB (agents, student_advisors, educational_content)
```

### Critical Services

- **llama-server** (port 8090): HTTP API for LLM inference, keeps model loaded in memory
- **agent_service** (port 8080): C++ microservice handling agent logic, RAG, memory
- **Apache/PHP**: Authentication, database operations, admin panels

**Start services:** `./start_services.sh` (kills existing, starts llama-server then agent_service)

### Design Pattern: Template-Instance (Student Advisors)

```
agents table = TEMPLATES (e.g., "Professor Hawkeinstein", is_student_advisor=1)
      ↓
student_advisors table = PER-STUDENT INSTANCES (1:1 with students)
```

**Critical constraint:** `UNIQUE KEY unique_student_advisor (student_id)` enforces one advisor per student.

**Data isolation:** Each student's advisor instance has separate:
- `conversation_history` (LONGTEXT JSON)
- `progress_notes` (TEXT)
- `testing_results` (LONGTEXT JSON)
- `custom_system_prompt` (TEXT, can override template)

**Deprecated tables:** `student_advisor_assignments` was removed. Use `student_advisors` instead.

### Authentication Systems

**Internal (admin APIs):** JWT Bearer tokens in `Authorization` header
- Admin endpoints: `api/admin/*.php` all require `requireAdmin()` from `auth_check.php`
- Token stored in `sessionStorage` as `admin_token`
- Validated via `verifyToken()` in `config/database.php`

### Course Generation Pipeline (5-Agent System)

**Architecture:** ALL course content is generated by the local LLM.

```
Standards → Agent 1 (Outline) → Agent 2 (Lessons) → Agent 3 (Questions)
                                                       ↓
                              Agent 5 (Validator) ← Agent 4 (Unit Tests)
```

**Agent Pipeline:**
| Agent | Purpose | File | Status |
|-------|---------|------|--------|
| 1. Standards-to-Outline | Structure course | `generate_draft_outline.php` | Implemented |
| 2. Lesson Builder | Generate lesson content | `generate_lesson_content.php` | Implemented |
| 3. Question Bank | Create quiz questions | `generate_lesson_questions.php` | Not Implemented |
| 4. Unit Test | Compile unit tests | `generate_unit_test.php` | Not Implemented |
| 5. Validator | QA check | `validate_course.php` | Not Implemented |

**Key insight:** The LLM generates ALL age-appropriate content directly from educational standards.
No web scraping or external content retrieval - everything is AI-generated from scratch.

### C++ Agent Service

**Build process:**
```bash
cd cpp_agent
make clean && make
pkill -9 agent_service
nohup ./bin/agent_service > /tmp/agent_service_full.log 2>&1 &
```

**Dependencies:** `-lcurl -ljsoncpp -lmysqlclient -lpthread`

**Key files:**
- `cpp_agent/src/llamacpp_client.cpp`: HTTP client to llama-server (NOT CLI execution)
- `cpp_agent/src/agent_manager.cpp`: Orchestrates prompts, RAG, memory storage
- `cpp_agent/src/http_server.cpp`: HTTP endpoints (`/agent/chat`, `/agent/list`, `/health`)
- `cpp_agent/src/database.cpp`: Loads agent configs (but PHP handles student_advisors)

**Debugging:** Check `/tmp/agent_service_full.log` and `/tmp/llama_server.log`

### Database Schema Patterns

**Student advisor lookup:**
```php
$stmt = $db->prepare("
  SELECT sa.*, a.agent_name, a.system_prompt
  FROM student_advisors sa
  JOIN agents a ON sa.advisor_type_id = a.agent_id
  WHERE sa.student_id = ? AND sa.is_active = 1
");
```

**Update advisor conversation:**
```php
$stmt = $db->prepare("
  UPDATE student_advisors 
  SET conversation_history = JSON_ARRAY_APPEND(conversation_history, '$', ?),
      last_interaction = NOW()
  WHERE student_id = ?
");
$stmt->execute([json_encode($turn), $studentId]);
```

### API Endpoints

**Agent endpoints:**
- `POST /agent/chat` - `{userId, agentId, message}` → `{response, success}`
- `GET /agent/list` - Returns active agents with `{id, name, avatarEmoji, description, model}`

**Student advisor endpoints:**
- `GET /api/student/get_advisor.php` - Returns student's advisor instance with all data
- `POST /api/student/update_advisor_data.php` - Update conversation/progress/tests

**Admin endpoints:**
- `GET /api/admin/list_student_advisors.php` - List all advisor instances
- `POST /api/admin/assign_student_advisor.php` - Create advisor instance for student
- `GET /api/admin/list_educational_content.php` - Returns stored educational content with pagination

### Configuration Files

**config/database.php:**
- DB credentials from environment variables
- JWT secrets: `JWT_SECRET`, `PASSWORD_PEPPER`
- Service URLs: `AGENT_SERVICE_URL` (port 8080)

**cpp_agent/config.json:**
```json
{
  "server_port": 8080,
  "model_name": "llama-2-7b-chat",
  "context_length": 4096,
  "temperature": 0.7
}
```

### Health Checks

```bash
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent service
```

### Project Structure

```
Professor_Hawkeinstein/
├── start_services.sh              # Service startup script
├── admin_*.html                   # Admin panel interfaces
├── student_dashboard.html         # Student chat interface
├── api/
│   ├── admin/                     # Admin-only endpoints (require JWT)
│   │   ├── auth_check.php         # requireAdmin() middleware
│   │   └── list_educational_content.php
│   ├── agent/
│   │   ├── chat.php               # Proxy to C++ service
│   │   └── list.php               # Available agents
│   └── student/
│       ├── get_advisor.php        # Student's advisor instance
│       └── update_advisor_data.php # Update conversation/progress
├── cpp_agent/
│   ├── src/
│   │   ├── llamacpp_client.cpp    # HTTP client to llama-server
│   │   ├── agent_manager.cpp      # Agent orchestration
│   │   └── http_server.cpp        # HTTP endpoints
│   ├── Makefile                   # Build configuration
│   └── bin/agent_service          # Compiled binary
└── config/database.php            # DB config, JWT settings
```

### Documentation References

- `ADVISOR_INSTANCE_API.md` - Advisor system API reference
- `AGENT_TROUBLESHOOTING_LOG.md` - Historical debugging notes
- `SETUP_COMPLETE.md` - Quick start guide
- `DEPLOYMENT_ENVIRONMENT_CONTRACT.md` - DEV/PROD deployment rules

