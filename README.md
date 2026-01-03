# Professor Hawkeinstein Educational Platform

An AI-powered educational platform with automated course generation, personalized student advisors, and interactive workbooks. Built with local LLM inference using llama.cpp.

## Development vs Production Environment

**Read before making changes:** [`docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md`](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md)

- **Development:** `/home/steve/Professor_Hawkeinstein` (not web-accessible)
- **Production:** `/var/www/html/basic_educational` (web-accessible)

Changes in DEV do not automatically sync to PROD. Deploy explicitly using `make sync-web`.

## Current Status (January 2026)

**Alpha Release** - Core features functional, tested, not production-ready.

| Feature | Status |
|---------|--------|
| Two-subsystem architecture (Student Portal + Course Factory) | ✅ Working |
| C++ agent service with llama.cpp integration | ✅ Working |
| Live AI responses from LLM | ✅ Working (4-9 seconds) |
| Course generation from educational standards (5-agent pipeline) | ✅ Working |
| Student advisors with persistent memory | ✅ Working |
| Interactive workbook with lessons and questions | ✅ Working |
| Admin dashboard for course creation | ✅ Working |
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

**⚠️ Warning:** Native deployment is more complex. Docker deployment is recommended for testing.

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

### Default Credentials

- **Admin:** `root` / `Root1234`
- **Test Student:** `john_doe` / `student123`

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

### Grade-Based Difficulty System

**Important:** This platform does not use traditional "beginner/intermediate/advanced" difficulty labels. 

**The grade level IS the difficulty level.** A 2nd grade science lesson is inherently appropriate for 2nd graders, and a 10th grade physics lesson is appropriate for 10th graders. 

Any references to "easy/medium/hard" difficulty in the codebase should be removed as they are redundant and potentially confusing when the grade level already indicates the appropriate complexity.
### Quick Reference
| Document | Purpose |
|----------|---------|
| [docs/SETUP_STATUS.md](docs/SETUP_STATUS.md) | **System status, URLs, credentials** |
| [docs/DEBUG_TROUBLESHOOTING.md](docs/DEBUG_TROUBLESHOOTING.md) | **Debugging guide, common issues** |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | **Deployment procedures** |

### Detailed Docs
| Document | Purpose |
|----------|---------|
| [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) | System architecture overview |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Technical architecture details |
| [docs/COURSE_GENERATION_ARCHITECTURE.md](docs/COURSE_GENERATION_ARCHITECTURE.md) | Agent pipeline specifications |
| [docs/COURSE_GENERATION_API.md](docs/COURSE_GENERATION_API.md) | Course creation API reference |
| [docs/ADVISOR_INSTANCE_API.md](docs/ADVISOR_INSTANCE_API.md) | Student advisor system API |
| [docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md) | Dev/prod separation rules |
| [docs/ERROR_HANDLING_GUIDE.md](docs/ERROR_HANDLING_GUIDE.md) | Error handling patterns |
| [docs/FILE_SYNC_GUIDE.md](docs/FILE_SYNC_GUIDE.md) | File sync procedurecomprehensive unit assessments
- [ ] **Implement Content Validator agent (ID 22)** - QA check for generated content
- [ ] **Add course publishing workflow** - Final approval step before making courses live

### Medium Priority
- [ ] Implement true vector similarity search for RAG
- [ ] Add WebSocket support for real-time LLM streaming
- [ ] Support multiple LLM models per agent type
- [ ] Add course versioning and rollback
- [ ] Implement student progress analytics dashboard

### Low Priority
- [ ] Add multimedia content support (videos, audio)
- [ ] Implement horizontal scaling with load balancing
- [ ] Add collaborative course editing for multiple admins
- [ ] Support for custom question types beyond fill-in/multiple-choice/essay

## Documentation

| Document | Purpose |
|----------|---------|
| [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) | System architecture |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Technical architecture |
| [docs/COURSE_GENERATION_API.md](docs/COURSE_GENERATION_API.md) | Course creation API |
| [docs/ADVISOR_INSTANCE_API.md](docs/ADVISOR_INSTANCE_API.md) | Student advisor system |
| [docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md) | Dev/prod separation |
| [docs/REFACTOR_TODO.md](docs/REFACTOR_TODO.md) | Pending tasks |

## License

Proprietary - All rights reserved

## Repository

https://github.com/stevemeierotto/Professor-Hawkeinstein
