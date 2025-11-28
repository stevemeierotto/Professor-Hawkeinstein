# Professor Hawkeinstein Educational Platform - AI Agent Instructions

## Architecture Overview

**Three-tier system:** Browser → PHP/Apache → C++ Agent Service → llama-server (LLM inference)

```
Frontend (HTML/JS) → PHP API (JWT auth) → C++ service :8080 → llama-server :8090
                      ↓
                   MariaDB (agents, student_advisors, scraped_content)
```

**Critical services:**
- `llama-server` (port 8090): HTTP API for LLM inference, keeps model loaded in memory
- `agent_service` (port 8080): C++ microservice handling agent logic, RAG, memory
- Apache/PHP: Authentication, database operations, admin panels

**Start services:** `./start_services.sh` (kills existing, starts llama-server then agent_service)

## Key Design Patterns

### 1. Template-Instance Pattern (Student Advisors)
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

**Never query deleted tables:** `student_advisor_assignments` was removed. Use `student_advisors` instead.

### 2. Dual Authentication System
- **Internal (admin APIs):** JWT Bearer tokens in `Authorization` header
  - Admin endpoints: `api/admin/*.php` all require `requireAdmin()` from `auth_check.php`
  - Token stored in `sessionStorage` as `admin_token`
  - Validated via `verifyToken()` in `config/database.php`

- **External (CSP API):** Token-based auth
  - Format: `Authorization: Token token=<API_KEY>` (NOT "Bearer")
  - Key from `.env` file: `CSP_API_KEY`
  - Base URL: `https://api.commonstandardsproject.com/api/v1`

**Never mix these formats.** CSP uses "Token token=", internal uses "Bearer".

### 3. Content Scraping (CSP Integration)
**File:** `api/admin/scraper_csp.php`

**Two-step process:**
1. GET jurisdiction data: `/jurisdictions/{jurisdictionId}`
2. GET standards: `/standard_sets/{standardSetId}`

**Storage pattern:** Combines ALL standards into ONE `scraped_content` row per scrape.
```php
storeCSPStandards($standards, $scrapedBy) {
  // Creates 1 comprehensive entry with:
  // - title: "Alaska - Grade 1 Mathematics Standards (94 items)"
  // - content_text: All descriptions concatenated
  // - content_html: Formatted HTML with all standards
  // - metadata: JSON array of all standard objects
}
```

**Do NOT create separate rows per standard.** Always consolidate.

### 4. LLM Optimization Strategy
**Current setup (90% faster than original):**
- System prompts: 200-400 chars (NOT 2000+)
- `max_tokens`: 256 for chat, 1024 for summaries
- `n_predict`: 256 in llamacpp_client.cpp
- Stop sequences: `["\nStudent:", "\nUser:", "\n\n\n"]`
- `cache_prompt: true` for system prompt caching
- llama-server flags: `--threads 4 --parallel 2 --cont-batching --ctx-size 4096`

**Performance:** Professor Hawkeinstein: 4-9s per response (was 60-90s)

**Model:** `qwen2.5-1.5b-instruct-q4_k_m.gguf` (small, fast, good quality)

## Critical Workflows

### Building C++ Agent Service
```bash
cd cpp_agent
make clean && make
pkill -9 agent_service
nohup ./bin/agent_service > /tmp/agent_service_full.log 2>&1 &
```

**Dependencies:** `-lcurl -ljsoncpp -lmysqlclient -lpthread`

**Key files:**
- `src/llamacpp_client.cpp`: HTTP client to llama-server (NOT CLI execution)
- `src/agent_manager.cpp`: Orchestrates prompts, RAG, memory storage
- `src/http_server.cpp`: HTTP endpoints (`/agent/chat`, `/agent/list`, `/health`)
- `src/database.cpp`: Loads agent configs (but PHP handles student_advisors)

**Debugging:** Check `/tmp/agent_service_full.log` and `/tmp/llama_server.log`

### Admin Panel Development
**All admin pages use:**
1. `admin_auth.js` - Fetch override adding JWT `Authorization: Bearer <token>` headers
2. `admin_storage.js` - Session management (login/logout)
3. Cache-busting: `<script src="admin_auth.js?v={timestamp}"></script>`

**Browser cache issues:** Use timestamp-based versions, not `?v=1,2,3`. Regenerate with:
```bash
TIMESTAMP=$(date +%s)
sed -i "s/admin_auth.js?v=[0-9]*/admin_auth.js?v=$TIMESTAMP/" admin_*.html
```

**Common pitfall:** Headers object serialization. Use plain objects, not `new Headers()`:
```javascript
options.headers = options.headers || {};
options.headers['Authorization'] = `Bearer ${token}`;
// NOT: options.headers = new Headers(); headers.append(...)
```

### Database Queries - Common Patterns
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

**List scraped content (admin):**
```php
// Returns: content_id, url, title, content_preview (SUBSTRING of content_text)
// NOT: page_title, source_url, extracted_text, domain
// Frontend must compute domain from url: new URL(item.url).hostname
```

## File Sync Requirements

**Always sync to web directory after editing:**
```bash
cp /home/steve/Professor_Hawkeinstein/{file} /var/www/html/Professor_Hawkeinstein/
```

**Critical paths:**
- PHP APIs: `/var/www/html/Professor_Hawkeinstein/api/`
- Admin panels: `/var/www/html/Professor_Hawkeinstein/admin_*.html`
- Config: `/var/www/html/Professor_Hawkeinstein/config/database.php`

**Verify sync:** `md5sum` both files, check timestamps with `ls -lh`

## API Endpoint Reference

**Agent endpoints:**
- `POST /agent/chat` - `{userId, agentId, message}` → `{response, success}`
- `GET /agent/list` - Returns active agents with `{id, name, avatarEmoji, description, model}`

**Student advisor endpoints:**
- `GET /api/student/get_advisor.php` - Returns student's advisor instance with all data
- `POST /api/student/update_advisor_data.php` - Update conversation/progress/tests
  - `conversation_turn`: Appends to array with timestamp
  - `test_result`: Appends to array, auto-calculates percentage
  - `progress_notes`: Replaces text field

**Admin endpoints:**
- `GET /api/admin/list_student_advisors.php` - List all advisor instances (filters: student_id, advisor_type_id, is_active)
- `POST /api/admin/assign_student_advisor.php` - Create advisor instance for student
- `POST /api/admin/scraper_csp.php` - Scrape CSP standards (returns combined entry)
- `GET /api/admin/list_scraped_content.php` - Returns scraped items with pagination

**All admin endpoints require:** `requireAdmin()` from `api/admin/auth_check.php`

## Configuration Files

**`config/database.php`:**
- DB credentials: `professorhawkeinstein_user` / `BT1716lit`
- JWT secrets: `JWT_SECRET`, `PASSWORD_PEPPER`
- Service URLs: `AGENT_SERVICE_URL` (port 8080)
- CSP API: `CSP_API_KEY`, `CSP_API_BASE_URL`

**`.env` (required):**
```
CSP_API_KEY=7RBswV2Rr3F9GmPPNCXc7wrV
DB_USER=professorhawkeinstein_user
DB_PASS=BT1716lit
```

**`cpp_agent/config.json`:**
```json
{
  "server_port": 8080,
  "model_name": "llama-2-7b-chat",
  "context_length": 4096,
  "temperature": 0.7
}
```

## Testing & Debugging

**Health checks:**
```bash
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent service
```

**Test agent chat:**
```bash
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"agentId":1,"message":"Hello"}'
```

**Common issues:**
1. **401 errors in admin panel:** Check browser console for JWT token presence, verify `admin_auth.js` loaded
2. **Agent timeouts:** Check `/tmp/agent_service_full.log` for LlamaCppClient errors
3. **CSP API failures:** Verify Authorization header format is "Token token=" not "Bearer"
4. **Column not found errors:** Check if using old names (page_title → title, source_url → url)

**Root credentials:** Username: `root`, Password: `Root1234` (for admin panel access)

## Agent Development Best Practices

**When creating new agents:**
1. Add to `agents` table with optimized `system_prompt` (200-400 chars)
2. Set `max_tokens` appropriately (256 chat, 512-1024 summaries)
3. Set `temperature` (0.3 factual, 0.7 creative, 0.9 very creative)
4. Add `avatar_emoji` for UI display

**When modifying agent behavior:**
- Edit `agents.system_prompt` in database (NOT in C++ code)
- Keep prompts concise - longer prompts = slower responses
- Test with `agent_service` logs: `tail -f /tmp/agent_service_full.log`

**Memory management:**
- PHP handles all `student_advisors` storage
- C++ service retrieves via database.cpp but doesn't update
- Use `api/student/update_advisor_data.php` for persistence

## Project Structure Quick Reference

```
Professor_Hawkeinstein/
├── start_services.sh              # Service startup script
├── admin_*.html                   # Admin panel interfaces
├── student_dashboard.html         # Student chat interface
├── api/
│   ├── admin/                     # Admin-only endpoints (require JWT)
│   │   ├── auth_check.php         # requireAdmin() middleware
│   │   ├── scraper_csp.php        # CSP standards scraping
│   │   └── list_scraped_content.php
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
└── config/database.php            # DB config, JWT, CSP settings
```

**Documentation:**
- `ADVISOR_INSTANCE_API.md` - Advisor system API reference
- `AGENT_TROUBLESHOOTING_LOG.md` - Historical debugging notes
- `SETUP_COMPLETE.md` - Quick start guide
