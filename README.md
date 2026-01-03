# Professor Hawkeinstein Educational Platform

An AI-powered educational platform with automated course generation, personalized student advisors, and interactive workbooks. Built with local LLM inference using llama.cpp.

## Development vs Production Environment

**Read before making changes:** [`docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md`](docs/DEPLOYMENT_ENVIRONMENT_CONTRACT.md)

- **Development:** `/home/steve/Professor_Hawkeinstein` (not web-accessible)
- **Production:** `/var/www/html/basic_educational` (web-accessible)

Changes in DEV do not automatically sync to PROD. Deploy explicitly using `make sync-web`.

## Current Status (December 2025)

**Alpha Release** - Core features functional, not production-ready.

| Feature | Status |
|---------|--------|
| Two-subsystem architecture (Student Portal + Course Factory) | ✅ Working |
| C++ agent service with llama.cpp integration | ✅ Working |
| Live AI responses from LLM | ✅ Working |
| Course generation from educational standards | ✅ Working |
| Student advisors with persistent memory | ✅ Working |
| Interactive workbook with lessons and questions | ✅ Working |
| Admin dashboard for course creation | ✅ Working |
| Docker deployment | ✅ Working |
| Lesson quizzes and unit tests | ⏳ Partial |
| Video/multimedia content | ❌ Placeholders only |
| Vector similarity search (RAG) | ❌ Not implemented |

## Technology Stack

| Component | Technology | Port |
|-----------|------------|------|
| Frontend | HTML5, CSS3, vanilla JavaScript | - |
| Backend | PHP 8.0+ with JWT authentication | 80/443 |
| Database | MariaDB 10.7+ | 3306 (or 3307 in Docker) |
| Agent Service | C++ HTTP microservice | 8080 |
| LLM Server | llama-server (llama.cpp) | 8090 |
| Model | Qwen 2.5 1.5B or Llama 2 7B (quantized) | - |

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

- Linux (tested on Ubuntu)
- Docker & Docker Compose (for containerized deployment)
- OR: Apache 2.4+, PHP 8.0+, MariaDB 10.7+, g++ with C++17
- 8GB+ RAM for LLM inference

### Option 1: Docker Deployment

```bash
git clone https://github.com/stevemeierotto/Professor-Hawkeinstein.git
cd Professor_Hawkeinstein

# Start all services
./start_services.sh
```

Services started:
- **phef-database** (MariaDB) - port 3307
- **phef-llama** (llama-server) - port 8090
- **phef-agent** (C++ service) - port 8080
- **phef-api** (PHP) - port 8081

Access:
- Student Portal: http://localhost:8081/student_portal/
- Admin Dashboard: http://localhost:8081/course_factory/

### Option 2: Native Deployment

1. **Install dependencies:**
   ```bash
   sudo apt install apache2 php8.0 php8.0-mysql mariadb-server
   sudo apt install g++ libcurl4-openssl-dev libjsoncpp-dev libmysqlclient-dev
   ```

2. **Set up database:**
   ```bash
   mysql -u root -p < schema.sql
   ```

3. **Configure PHP:**
   Edit `config/database.php` with your credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'professorhawkeinstein_platform');
   define('DB_USER', 'your_user');
   define('DB_PASS', 'your_password');
   ```

4. **Build and start llama-server:**
   ```bash
   cd llama.cpp
   make
   ./build/bin/llama-server \
       --model ../models/qwen2.5-1.5b-instruct-q4_k_m.gguf \
       --port 8090 --threads 4 --ctx-size 4096
   ```

5. **Build and start agent service:**
   ```bash
   cd cpp_agent
   make clean && make
   ./bin/agent_service
   ```

6. **Start services:**
   ```bash
   ./start_services.sh
   ```

### Default Credentials

- **Admin:** `root` / `Root1234`
- **Test Student:** `john_doe` / `student123`

## API Reference

### Authentication

```bash
# Login
curl -X POST http://localhost/basic_educational/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}'

# Response: {success, token, user{userId, username, role}}
```

### Agent Chat

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

### Health Checks

```bash
curl http://localhost:8090/health  # llama-server
curl http://localhost:8080/health  # agent_service
```

## Course Generation Pipeline

The system uses a 5-agent pipeline for course creation:

| Agent | Purpose | Status |
|-------|---------|--------|
| 1. Outline | Standards → Course structure | ✅ Working |
| 2. Lessons | Standards → Lesson content | ✅ Working |
| 3. Questions | Lessons → Question banks | ✅ Working |
| 4. Unit Tests | Compile assessments | ⏳ Planned |
| 5. Validator | QA check | ⏳ Planned |

## LLM Configuration

| Setting | Value |
|---------|-------|
| Model | Qwen 2.5 1.5B or Llama 2 7B (Q4 quantized) |
| Context | 4096-8192 tokens |
| Threads | 4 |
| System prompts | 200-400 chars (optimized for speed) |
| Max tokens | 256 (chat), 1024-4800 (content generation) |
| Response time | 4-9 seconds typical |

## Troubleshooting

### Check Service Status

```bash
# Docker
docker ps
docker logs phef-llama
docker logs phef-agent

# Native
curl http://localhost:8080/health
curl http://localhost:8090/health
```

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

## TODO: Pending Improvements

### High Priority
- [ ] **Remove difficulty_distribution from question banks** - Grade level is sufficient
- [ ] **Remove difficulty field from question objects** - Not needed when grade determines complexity
- [ ] **Implement Quiz Creator agent (ID 20)** - Assemble quizzes from question banks
- [ ] **Implement Unit Test Creator agent (ID 21)** - Generate comprehensive unit assessments
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
