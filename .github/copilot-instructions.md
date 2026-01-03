# Professor Hawkeinstein - AI Agent Rules

## 🚨 MANDATORY: Read First

**Primary reference:** `ARCHITECTURE.md` at project root
**Deployment rules:** `docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md`

These documents define system architecture and operational constraints. Always consult them before making changes.

---

## Core Behavioral Rules

### 1. Development vs Production Separation

**DEV:** `/home/steve/Professor_Hawkeinstein` (coding, testing - NOT web-accessible)
**PROD:** `/var/www/html/basic_educational` (live site - ONLY web-accessible directory)

**NEVER:**
- Edit PROD files directly (changes lost on next deploy)
- Assume DEV changes auto-sync to PROD (they don't)
- Create new directories in `/var/www/html` without confirmation

**ALWAYS:**
- Edit files in DEV first
- Deploy to PROD explicitly using `make sync-web`
- Check logs at `/tmp/sync_to_web.log` before assuming file sync issues
- Ask before touching PROD filesystem

### 2. Security & Credentials

**NEVER:**
- Include real credentials in documentation, prompts, code comments, or commits
- Hardcode passwords, tokens, or API keys
- Commit `.env` files or `config/database.php` with real values

**ALWAYS:**
- Use placeholders: `<DB_USERNAME>`, `<DB_PASSWORD>`, `<JWT_SECRET>`
- Load credentials from environment variables or `.env` files
- Verify `.gitignore` includes sensitive files

### 3. File Synchronization

**ALWAYS use automated sync:**
```bash
make sync-web         # Deploy to production
make sync-web-dry     # Preview changes
make test-sync        # Verify sync
```

**NEVER:**
- Manually copy files between DEV and PROD
- Use `cp`, `rsync`, or `scp` commands directly
- Assume changes propagate automatically

**Sync exclusions:** `.env`, `config/database.php`, `tests/`, `migrations/`, `*.md`, `models/`, `llama.cpp/`, `build/`

### 4. Architecture Boundaries

**Student Portal** (students, observers):
- MAY: View courses, track progress, interact with tutor agents
- MUST NOT: Create courses, edit curriculum, access admin functions

**Course Factory** (curriculum authors):
- MAY: Create courses, generate content, configure authoring agents
- MUST NOT: Access student data, read progress, authenticate students

**Shared Infrastructure** (neutral):
- Database, agent runtime, models, configuration
- MUST NOT contain subsystem-specific business logic

See `ARCHITECTURE.md` for complete subsystem definitions.

### 5. Agent Development

**When creating agents:**
- Add to `agents` table with optimized `system_prompt`
- Set `temperature` based on use case (0.3 factual, 0.7 creative, 0.9 very creative)
- Add `avatar_emoji` for UI display
- Keep prompts as short as possible, as long as necessary
- Adapt to active model's context window

**When modifying agent behavior:**
- Edit `agents.system_prompt` in database (NOT in C++ code)
- Test with agent_service logs: `tail -f /tmp/agent_service_full.log`

**Memory management:**
- PHP handles all `student_advisors` table storage
- C++ service retrieves via database.cpp but doesn't update
- Use `api/student/update_advisor_data.php` for persistence

### 6. Code Practices

**All file paths:**
- Relative to project root: `/home/steve/Professor_Hawkeinstein/`
- Use absolute paths in commands and configuration
- No ambiguous `src/` references without parent directory

**Database queries:**
- ALWAYS use prepared statements
- NEVER concatenate user input into SQL
- Use `requireAdmin()` from `api/admin/auth_check.php` for all admin endpoints

**JavaScript admin panels:**
- Include `admin_auth.js` for JWT authorization headers
- Use cache-busting with timestamps: `?v={timestamp}`
- Use plain objects for headers, NOT `new Headers()`

### 7. Service Management

**Start services:** `./start_services.sh`
**Health checks:**
```bash
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent service
```

**Log locations:**
- Agent service: `/tmp/agent_service_full.log`
- LLM server: `/tmp/llama_server.log`
- Sync operations: `/tmp/sync_to_web.log`

### 8. Incomplete Features

**Agents marked as not implemented:**
- Agent 3 (Question Bank): Returns "Not Implemented" if invoked
- Agent 4 (Unit Test): Returns "Not Implemented" if invoked
- Agent 5 (Validator): Returns "Not Implemented" if invoked

**Behavior:** If called, return deterministic error message, do not attempt execution.

### 9. Debugging Workflow

**Common issues:**
1. **401 errors in admin panel:** Check JWT token in browser console, verify `admin_auth.js` loaded
2. **Agent timeouts:** Check `/tmp/agent_service_full.log` for errors
3. **Column not found errors:** Verify using current column names (check migrations)

**Never query deleted tables:** `student_advisor_assignments` removed, use `student_advisors` instead.

### 10. Local-First Architecture

**Assumptions:**
- Self-hosted LLM inference (llama.cpp via llama-server)
- Local model files in `models/` directory
- No cloud API dependencies for core functionality
- All agent processing happens on local hardware

**NEVER:**
- Introduce cloud API dependencies without explicit approval
- Assume internet connectivity for LLM operations
- Reference external inference services in core workflows

---

## Enforcement

These rules are **mandatory**. Violations must be corrected immediately.

If a rule conflicts with user request, clarify intent before proceeding.

If unsure about architecture or scope, consult `ARCHITECTURE.md` before making changes.
