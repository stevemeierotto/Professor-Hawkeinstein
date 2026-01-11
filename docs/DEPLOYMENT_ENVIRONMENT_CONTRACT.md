# Deployment Environment Contract

**Last Updated:** December 16, 2025  
**Status:** CRITICAL - Read before any file system operations

---

## 🚨 ENVIRONMENT CONTRACT — READ BEFORE ACTING

This system uses a **strict Dev vs Production separation**. Violating this contract is considered a critical error.

### DEVELOPMENT ENVIRONMENT (DEV)
- **Path:** `/home/steve/Professor_Hawkeinstein`
- **Purpose:** coding, editing, testing, drafting
- **Web Server:** This directory is **NOT** served by the web server
- **Browser Access:** Files here are **NOT** browser-accessible

### PRODUCTION ENVIRONMENT (PROD)
- **Path:** `/var/www/html/basic_educational`
- **Purpose:** live web application
- **Web Server:** This is the **ONLY** directory served by Apache
- **Browser Access:** All PHP endpoints accessed by the browser **MUST** exist here

### CURRENT DEPLOYMENT: HYBRID ARCHITECTURE

**⚠️ The local environment uses a HYBRID setup:**

| Component | Location | Notes |
|-----------|----------|-------|
| Web Files | PROD (`/var/www/html/basic_educational`) | Served by native Apache |
| Database | Docker (phef-database:3307) | Must sync from DEV to PROD |
| LLM Server | Docker (phef-llama:8090) | Backend services in Docker |
| Agent Service | Docker (phef-agent:8080) | Backend services in Docker |

**This means:**
- Edit files in **DEV** (`/home/steve/Professor_Hawkeinstein`)
- Deploy to **PROD** using `make sync-web`
- Native Apache at `professorhawkeinstein.local` serves PROD files
- PROD PHP connects to Docker backend services

**Never run native backend services** (llama-server, agent_service) - causes port conflicts!

---

## 📋 THE MENTAL MODEL (What Agents Must Believe)

Development happens in `/home/steve/Professor_Hawkeinstein`.  
Production is served **only** from `/var/www/html/basic_educational`.  
These directories are **never the same, never interchangeable, and never auto-synced**.

**Agents must treat this like a deployment pipeline, not a shared folder.**

---

## 🔒 CRITICAL RULES

1. **DEV files do NOT automatically affect PROD**
2. **PROD files must never be edited directly** unless explicitly instructed
3. **NEVER assume the DocumentRoot name matches the project name**
4. **NEVER create new directories in /var/www/html** without explicit confirmation
5. **NEVER change Apache config** unless explicitly instructed

### If a file "exists but is not accessible":
→ First check whether it exists in **DEV vs PROD**  
→ **DO NOT** copy, move, or mkdir until the correct environment is confirmed

---

## 🛡️ FILE SYSTEM SAFETY RULES

- ✅ You may **READ** files in DEV freely
- ❌ You may **NOT WRITE** to PROD unless explicitly told to deploy
- ❌ You may **NOT** run `sudo`, `cp`, `mv`, `rsync`, `mkdir` in PROD by default
- ⚠️ If deployment is required, **ASK FIRST** and explain what will be copied and why
- ⚠️ If paths conflict, **STOP** and report the conflict instead of guessing

---

## ✅ PRE-ACTION CHECKLIST

**Must be completed mentally before acting:**

1. Is the issue occurring in **DEV** or **PROD**?
2. Is the **browser** involved? (If yes → PROD)
3. Does the file exist in the **PROD** path?
4. Does Apache serve the directory I'm inspecting?
5. Has the user **explicitly approved** deployment actions?

**If any answer is uncertain → STOP and ask.**

---

## 🔍 DEBUGGING RULES

- **Treat `curl` as authoritative, not the browser**
- Do not mix port 80 and 8081 during diagnosis
- If `md5` matches between DEV and PROD, filesystem is **NOT** the issue
- After confirming DocumentRoot, do not re-question it

### Common Debugging Scenarios

#### Scenario 1: "File exists but returns 404"
```bash
# Step 1: Check DEV
ls -la /home/steve/Professor_Hawkeinstein/api/admin/my_file.php

# Step 2: Check PROD
ls -la /var/www/html/basic_educational/api/admin/my_file.php

# Step 3: If missing in PROD, deploy it
cp /home/steve/Professor_Hawkeinstein/api/admin/my_file.php \
   /var/www/html/basic_educational/api/admin/
```

#### Scenario 2: "Changes not taking effect"
```bash
# Step 1: Verify you edited DEV (not PROD)
tail -5 /home/steve/Professor_Hawkeinstein/api/admin/my_file.php

# Step 2: Check if PROD has old version
md5sum /home/steve/Professor_Hawkeinstein/api/admin/my_file.php
md5sum /var/www/html/basic_educational/api/admin/my_file.php

# Step 3: If hashes differ, PROD needs update
cp /home/steve/Professor_Hawkeinstein/api/admin/my_file.php \
   /var/www/html/basic_educational/api/admin/
```

#### Scenario 3: "Service isn't responding"
```bash
# Step 1: Check if service is running
ps aux | grep agent_service
ps aux | grep llama-server

# Step 2: Check logs
tail -20 /tmp/agent_service_full.log
tail -20 /tmp/llama_server.log

# Step 3: Restart if needed
./start_services.sh
```

---

## 📦 DEPLOYMENT WORKFLOW

### Option 1: Manual Deployment (Single File)
```bash
# Copy specific file from DEV to PROD
cp /home/steve/Professor_Hawkeinstein/api/admin/my_file.php \
   /var/www/html/basic_educational/api/admin/

# Verify deployment
md5sum /home/steve/Professor_Hawkeinstein/api/admin/my_file.php
md5sum /var/www/html/basic_educational/api/admin/my_file.php
```

### Option 2: Automated Sync (Full Project)
```bash
# Use Makefile sync (recommended)
cd /home/steve/Professor_Hawkeinstein
make sync-web

# Preview changes first (dry run)
make sync-web-dry

# Verify sync
make test-sync
```

### Option 3: Selective Sync (Directory)
```bash
# Sync specific directory
rsync -av --delete \
  /home/steve/Professor_Hawkeinstein/api/admin/ \
  /var/www/html/basic_educational/api/admin/

# Exclude patterns (use .rsyncignore)
rsync -av --delete --exclude-from='.rsyncignore' \
  /home/steve/Professor_Hawkeinstein/ \
  /var/www/html/basic_educational/
```

---

## 🚨 HISTORICAL INCIDENT: Database Config Crisis

**Date:** December 16, 2025  
**Severity:** Critical  
**Root Cause:** Environment confusion

### What Happened

An agent attempted to "fix" a database connection issue by:
1. Editing `/var/www/html/basic_educational/config/database.php` (PROD) directly
2. Creating a new directory `/var/www/html/Professor_Hawkeinstein` (wrong location)
3. Copying files between mismatched paths
4. **NEVER checking if DEV changes were deployed to PROD**

### The Real Issue

The actual problem was **agent model names in the database** were incomplete:
```sql
-- ❌ WRONG (missing suffix and extension)
model_name = 'qwen2.5-1.5b-instruct'

-- ✅ CORRECT (full filename)
model_name = 'qwen2.5-1.5b-instruct-q4_k_m.gguf'
```

### How It Should Have Been Diagnosed

```bash
# Step 1: Check if service can connect to database
curl http://localhost:8080/health

# Step 2: Check database credentials in PROD
cat /var/www/html/basic_educational/config/database.php | grep DB_

# Step 3: Test database connection
mysql -u professorhawkeinstein_user -p -e "SHOW TABLES;" professorhawkeinstein_platform

# Step 4: Check agent table for model names
mysql -u professorhawkeinstein_user -p -e \
  "SELECT agent_id, agent_name, model_name FROM agents WHERE agent_id BETWEEN 16 AND 22;" \
  professorhawkeinstein_platform

# Step 5: Fix the actual issue (model names)
mysql -u professorhawkeinstein_user -p professorhawkeinstein_platform -e \
  "UPDATE agents SET model_name = 'qwen2.5-1.5b-instruct-q4_k_m.gguf' \
   WHERE agent_id IN (16,17,18,19,20,21,22);"
```

### Lessons Learned

1. **Database issues are NOT file sync issues**
   - Don't assume "file not accessible" means "file not deployed"
   - Check the actual service logs first

2. **Never edit PROD config files directly**
   - Changes will be lost on next deployment
   - Always edit in DEV, then deploy

3. **Use diagnostic tools before making changes**
   - `curl` to test endpoints
   - `mysql` to query database
   - `tail -f` to watch logs in real-time

4. **Question the diagnosis, not the environment**
   - If Apache DocumentRoot is `/var/www/html/basic_educational`, believe it
   - Don't create alternative paths hoping they'll work

---

## 🎯 DEPLOYMENT CHECKLIST

Before deploying to PROD, verify:

- [ ] Changes tested in DEV environment
- [ ] Database migrations applied (if needed)
- [ ] No hardcoded DEV paths in code
- [ ] `.env` file exists in PROD with correct credentials
- [ ] File permissions correct (755 for directories, 644 for files)
- [ ] Apache has read access to PROD directory
- [ ] Services restarted if needed (`start_services.sh`)
- [ ] Health checks pass (`curl localhost:8080/health`)
- [ ] Browser test confirms changes live

---

## 🐳 DOCKER DEPLOYMENT RULES

**Docker containers do NOT auto-update when you change files.**

Docker images are frozen snapshots. Running containers don't see changes to source files.

### Key Docker Files

| File | Purpose | Location |
|------|---------|----------|
| `config.json` | Local services config | `cpp_agent/config.json` |
| `config.docker.json` | Docker container config | `cpp_agent/config.docker.json` |
| `docker-compose.yml` | Container orchestration | Project root |
| `Dockerfile` | Agent service image | `cpp_agent/Dockerfile` |

### When to Restart Docker

| Change Type | Command Needed |
|-------------|----------------|
| Config files (`config.docker.json`, `.env`) | `docker-compose down && docker-compose up -d` |
| PHP/HTML/JS files | No restart (volume mounted) |
| C++ code changes | `docker-compose down && docker-compose up -d --build` |
| Dockerfile changes | `docker-compose down && docker-compose up -d --build` |
| Model file changes | `docker-compose down && docker-compose up -d` |
| Database schema changes | Apply migrations, then restart affected services |

### Docker vs Local Services

**Docker (default for development/testing):**
- Ports: 8081 (web), 8080 (agent), 8090 (llama), 3307 (db)
- Config: `cpp_agent/config.docker.json`
- Models: Mounted from `./models:/app/models`

**Local Services (alternative):**
- Stop Docker first: `docker-compose down`
- Config: `cpp_agent/config.json`
- Start: `./start_services.sh`

### Verify Docker Model

```bash
# Check which model is loaded
docker logs phef-llama --tail 20 | grep -i model

# Check agent service config
docker logs phef-agent --tail 20 | grep -i model
```

### Historical Docker Issues (December 2025)

**Issue:** Changed model from Qwen to Llama-2, but Docker still used Qwen.
**Root Cause:** Updated `config.json` but not `config.docker.json`. Containers weren't rebuilt.
**Fix:** Update `config.docker.json`, then `docker-compose down && docker-compose up -d --build`

---

## 🔗 RELATED DOCUMENTATION

- **File Sync Guide:** `docs/FILE_SYNC_GUIDE.md`
- **System Agent Debug Report:** `SYSTEM_AGENT_DEBUG_REPORT.md`
- **Setup Guide:** `SETUP_COMPLETE.md`
- **Apache Config:** `Professor_Hawkeinstein.conf`
- **Makefile Targets:** Run `make help` for sync commands

---

## 📞 WHEN IN DOUBT

**ASK THESE QUESTIONS:**

1. Am I editing the right environment?
2. Do I need to deploy after this change?
3. Is this a code issue or a deployment issue?
4. Have I checked the service logs?
5. Does the user know I'm about to touch PROD?

**If unsure about ANY of these → STOP and ask the user.**
