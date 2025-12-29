# Shared Dependencies Analysis

**Generated:** December 28, 2025  
**Purpose:** Document shared vs subsystem-specific code

---

## /config/database.php

**Classification:** SHARED (Infrastructure)

| Function/Constant | Type | Used By | Target Location |
|-------------------|------|---------|-----------------|
| `getDB()` | Function | Both | Wrapped by `shared/db/connection.php` |
| `verifyToken()` | Function | Both | Wrapped by `shared/auth/jwt.php` |
| `generateToken()` | Function | Both | Wrapped by `shared/auth/jwt.php` |
| `requireAuth()` | Function | Both | Wrapped by `shared/auth/middleware.php` |
| `hashPassword()` | Function | Both | Keep in config (simple utility) |
| `verifyPassword()` | Function | Both | Keep in config (simple utility) |
| `setCORSHeaders()` | Function | Both | Keep in config (simple utility) |
| `getJSONInput()` | Function | Both | Keep in config (simple utility) |
| `sendJSON()` | Function | Both | Keep in config (simple utility) |
| `callAgentService()` | Function | Both | Keep in config (agent integration) |
| `logActivity()` | Function | Both | Keep in config (logging) |
| `getAdminId()` | Function | Course Factory | Consider moving to factory |
| `DB_*` constants | Constants | Both | Keep in config |
| `JWT_*` constants | Constants | Both | Keep in config |
| `AGENT_SERVICE_*` | Constants | Both | Keep in config |

**Status:** Original file unchanged. New wrappers created in `/shared/`.

---

## /api/helpers/

### auth_headers.php
**Classification:** SHARED

| Function | Used By | Notes |
|----------|---------|-------|
| `csp_auth_header()` | Course Factory | CSP API integration |
| `bearer_auth_header()` | Both | JWT header creation |
| `validate_csp_headers()` | Course Factory | CSP-specific validation |
| `validate_internal_headers()` | Both | JWT header validation |

**Status:** Keep in `/api/helpers/`. May move CSP functions to Course Factory later.

### embedding_generator.php
**Classification:** SHARED (Course Factory primary user)

| Function | Used By | Notes |
|----------|---------|-------|
| Embedding generation | Course Factory | Content embedding for RAG |

**Status:** Keep in `/api/helpers/`. Primary user is Course Factory.

### model_validation.php
**Classification:** SHARED

| Function | Used By | Notes |
|----------|---------|-------|
| Model validation | Both | Agent model configuration |

**Status:** Keep in `/api/helpers/`.

### system_agent_helper.php
**Classification:** Course Factory specific

| Function | Used By | Notes |
|----------|---------|-------|
| System agent utilities | Course Factory | Agent configuration |

**Status:** Keep in `/api/helpers/`. Consider moving to `/course_factory/` in Phase 2.

---

## Summary

### Created in /shared/

| File | Purpose |
|------|---------|
| `shared/auth/jwt.php` | JWT generation and verification |
| `shared/auth/middleware.php` | Authentication middleware |
| `shared/db/connection.php` | Database connection wrapper |
| `shared/README.md` | Documentation |

### Unchanged (Backward Compatibility)

| File | Reason |
|------|--------|
| `config/database.php` | Core configuration, many dependents |
| `api/helpers/auth_headers.php` | Mixed usage, stable |
| `api/helpers/embedding_generator.php` | Shared utility |
| `api/helpers/model_validation.php` | Shared utility |
| `api/helpers/system_agent_helper.php` | Course Factory specific but stable |

### Migration Path

1. ✅ Create shared wrappers (done)
2. ⏳ Subsystems use shared wrappers for new code
3. ⏳ Gradually update existing code to use shared wrappers
4. ⏳ Original functions remain for backward compatibility
