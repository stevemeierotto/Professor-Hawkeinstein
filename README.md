# Professor Hawkeinstein Educational Platform

A complete AI-powered educational platform featuring automated course generation, personalized student advisors, and interactive workbooks. Built with local LLM inference for privacy and cost efficiency.

## 🚀 Current Status (December 2025)

**Alpha Release** - Core features functional, not production-ready:
- ✅ Automated course generation from educational standards (5-agent pipeline)
- ✅ 12-lesson "2nd Grade Science" course published with full content
- ✅ Student advisors with persistent memory and conversation history
- ✅ Interactive workbook with lessons and practice questions
- ✅ Admin dashboard for course creation and question generation
- ✅ Dockerized deployment (MariaDB, PHP API, C++ Agent Service, llama-server)
- ⚠️ **Not Ready:** Lesson quizzes, unit tests, video content, security hardening

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (vanilla, no frameworks)
- **Backend**: PHP 8.0+ with JWT authentication
- **Database**: MariaDB 10.7+ (Docker container: `phef-database:3307`)
- **AI Agents**: C++ HTTP microservice on port 8080 (Docker: `phef-agent`)
- **LLM**: llama-server on port 8090 (Docker: `phef-llama`) running Qwen 2.5 1.5B
- **API Layer**: PHP on port 8081 (Docker: `phef-api`)
- **Memory Management**: Prompt cache clearing script for long-running sessions
- **Security**: Username/password authentication (biometric features removed for liability)

## Architecture Overview

```
┌──────────────────────────────────────────────────────────┐
│                     Docker Compose                       │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │ phef-api    │  │ phef-agent   │  │ phef-llama     │ │
│  │ PHP :8081   │←→│ C++ :8080    │←→│ llama-server   │ │
│  │             │  │              │  │ :8090          │ │
│  └─────┬───────┘  └──────────────┘  └────────────────┘ │
│        │                                                 │
│        ↓                                                 │
│  ┌─────────────┐                                        │
│  │phef-database│                                        │
│  │MariaDB :3307│                                        │
│  └─────────────┘                                        │
└──────────────────────────────────────────────────────────┘
         ↑
         │ HTTP
    ┌────┴────┐
    │ Browser │
    └─────────┘
```

## Core Features

### 🤖 AI-Powered Course Generation
- **5-Agent Pipeline**: Standards → Outline → Lessons → Questions → Validation
- **Automated Content**: Generates 10K+ character lessons from educational standards
- **Question Banks**: Multiple choice, fill-in-blank, and short essay questions
- **Batch Generation**: Creates 5 questions per LLM call for efficiency
- **Memory Management**: Built-in cache clearing for long generation sessions

### 👨‍🎓 Personalized Student Advisors
- **1:1 Student-Advisor Mapping**: Each student gets their own Professor Hawkeinstein
- **Persistent Memory**: Conversation history, progress notes, testing results
- **Isolated Data**: No cross-contamination between students
- **Template-Instance Pattern**: Advisor templates → per-student instances

### 📚 Interactive Workbooks
- **Lesson Content**: 11K+ character educational content with formatting
- **Practice Questions**: Organized by type with expandable answers
- **Visual Placeholders**: Sections for future diagrams, videos, and animations
- **Progressive Navigation**: Next/Previous lesson navigation
- **Chat Integration**: Professor Hawkeinstein assistant panel for questions

### 🛠️ Admin Tools
- **Course Wizard**: Multi-step course creation from standards
- **Question Generator**: Batch generation with progress tracking (target: 10 per type)
- **Content Review**: Preview and approve generated content
- **Agent Factory**: Create custom AI agents with specific prompts

## Project Structure

```
Professor_Hawkeinstein/
├── index.html                      # Landing page
├── login.html / register.html      # Authentication pages
├── student_dashboard.html          # Student dashboard with advisor chat
├── workbook.html                   # Interactive lesson workbook
├── admin_*.html                    # Admin dashboards (12+ pages)
├── styles.css                      # Global styles
├── workbook_app.js                 # Workbook application logic
├── start_services.sh               # Docker services startup script
├── clear_llm_cache.sh              # Memory management utility
├── Makefile                        # Build and deployment automation
│
├── config/
│   └── database.php                # Database config, JWT auth, utilities
│
├── api/
│   ├── auth/
│   │   ├── login.php               # JWT-based authentication
│   │   └── logout.php              # Session termination
│   ├── admin/                      # Admin-only endpoints (JWT required)
│   │   ├── auth_check.php          # requireAdmin() middleware
│   │   ├── generate_draft_outline.php    # Agent 1: Standards → Outline
│   │   ├── scrape_lesson_content.php     # Agent 2: Generate lessons
│   │   ├── generate_lesson_questions.php # Agent 3: Question banks
│   │   ├── publish_course.php      # Publish course to students
│   │   ├── list_student_advisors.php     # View all advisor instances
│   │   └── scraper_csp.php         # Common Standards Project API
│   ├── student/
│   │   ├── get_advisor.php         # Get student's advisor instance
│   │   └── update_advisor_data.php # Update conversation/progress
│   ├── course/
│   │   ├── get_available_courses.php     # Student course list
│   │   ├── get_lesson_content.php  # Fetch lesson from database
│   │   └── courses/                # Course JSON files
│   ├── agent/
│   │   ├── chat.php                # Proxy to C++ agent service
│   │   └── list.php                # Available agents
│   └── helpers/
│       └── system_agent_helper.php # System agent configuration
│
├── cpp_agent/                      # C++ Agent Microservice
│   ├── src/
│   │   ├── main.cpp                # HTTP server entry point
│   │   ├── http_server.cpp         # HTTP endpoints (:8080)
│   │   ├── agent_manager.cpp       # Agent orchestration
│   │   ├── llamacpp_client.cpp     # llama-server HTTP client
│   │   └── database.cpp            # MariaDB connection
│   ├── Makefile                    # Build configuration
│   └── bin/agent_service           # Compiled binary
│
├── llama.cpp/                      # llama-server submodule
│   └── llama-server                # LLM inference server
│
└── models/
    └── qwen2.5-1.5b-instruct-q4_k_m.gguf  # Quantized LLM model
```

## Quick Start

### Prerequisites
- Docker & Docker Compose
- Make (for automation)
- 8GB+ RAM (for LLM inference)

### 1. Clone and Start Services

```bash
git clone https://github.com/stevemeierotto/Professor-Hawkeinstein.git
cd Professor_Hawkeinstein

# Start all Docker services
./start_services.sh
```

This starts:
- **phef-database** (MariaDB :3307)
- **phef-llama** (llama-server :8090) - loads Qwen 2.5 1.5B model
- **phef-agent** (C++ agent service :8080)
- **phef-api** (PHP API :8081)

### 2. Access the Platform

Open your browser to:
- **Student Portal**: http://localhost:8081
- **Admin Dashboard**: http://localhost:8081/admin_dashboard.html

**Default Credentials:**
- Username: `root`
- Password: `Root1234`

### 3. Deployment to Production

```bash
# Sync files to web directory
make sync-web

# Or manual rsync
./scripts/sync_to_web.sh
```

### 2. PHP Configuration

Update `config/database.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'professorhawkeinstein_platform');
define('DB_USER', 'professorhawkeinstein_user');
define('DB_PASS', 'your_secure_password_here');
```

**Important**: Change security keys in production:
- `JWT_SECRET`
- `PASSWORD_PEPPER`

### 3. Apache Configuration

Create virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName professorhawkeinstein.local
    DocumentRoot /var/www/html/basic_educational
    
    <Directory /var/www/basic_educational>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/professorhawkeinstein_error.log
    CustomLog ${APACHE_LOG_DIR}/professorhawkeinstein_access.log combined
</VirtualHost>
```

Enable required Apache modules:
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### 4. llama-server Setup

Install and run llama-server for local LLM inference:

```bash
# Build llama.cpp
git clone https://github.com/ggerganov/llama.cpp.git
cd llama.cpp
make

# Download model
cd models
wget https://huggingface.co/Qwen/Qwen2.5-1.5B-Instruct-GGUF/resolve/main/qwen2.5-1.5b-instruct-q4_k_m.gguf

# Start llama-server
../llama-server --model qwen2.5-1.5b-instruct-q4_k_m.gguf --port 8090 --threads 4 --ctx-size 4096
```

### 5. C++ Agent Microservice

The C++ agent microservice needs to be implemented with the following components:

**Required Libraries:**
- libcurl (for llama-server HTTP API)
- nlohmann/json (JSON parsing)
- cpp-httplib (HTTP server)
- MySQL Connector/C++ (database access)

**Endpoints to implement:**
- `POST /api/chat` - Process agent chat requests
**Note:** Biometric API endpoints have been removed for liability reasons.
- `POST /api/rag/query` - RAG retrieval
- `POST /api/embedding/generate` - Generate embeddings

**Compilation:**
```bash
cd cpp_agent
make clean && make
./bin/agent_service
```

Or use the startup script:
```bash
./start_services.sh
```

### 6. Permissions

```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/basic_educational
sudo chmod -R 755 /var/www/basic_educational
sudo chmod -R 775 /var/www/Professor_Hawkeinstein/logs
sudo chmod -R 775 /var/www/Professor_Hawkeinstein/media
```

## Testing

1. **Test database connection:**
   ```bash
   php -r "require 'config/database.php'; getDB(); echo 'Connected!';"
   ```

2. **Test frontend:**
   Navigate to `http://professorhawkeinstein.local` (or `http://localhost/basic_educational`)

3. **Demo credentials:**
   - Username: `john_doe`
   - Password: `student123`

4. **Test API endpoints:**
   ```bash
   curl -X POST http://localhost/basic_educational/api/auth/login.php \
     -H "Content-Type: application/json" \
     -d '{"username":"root","password":"Root1234"}'
   ```

## Current Implementation Status

### ✅ Completed
- **Core Infrastructure**
  - Docker containerization (4 services)
  - MariaDB database with comprehensive schema
  - PHP API layer with JWT authentication
  - C++ agent microservice with HTTP server
  - llama-server integration (Qwen 2.5 1.5B)

- **Course Generation System**
  - 5-agent pipeline (Standards → Outline → Lessons → Questions → Validator)
  - Common Standards Project (CSP) API integration
  - Automated lesson content generation (11K+ chars per lesson)
  - Question bank generation (3 types: multiple choice, fill-in-blank, essay)
  - Batch generation for efficiency (5 questions per call)
  - Course publishing workflow

- **Student Features**
  - Personal advisor instances (1:1 mapping)
  - Interactive workbook with lessons and questions
  - Persistent conversation history
  - Progress tracking
  - Course enrollment system

- **Admin Tools**
  - Course creation wizard
  - Question generator with progress tracking
  - Content review interface
  - Student advisor management
  - Agent factory for custom agents

- **Performance Optimizations**
  - System prompts: 200-400 chars (90% faster)
  - Prompt caching for repeated requests
  - Memory management scripts
  - Batch processing for question generation

### 🚧 In Progress
- Video content placeholders (sections created, content pending)
- Visual diagrams and charts (placeholders ready)
- Larger LLM for improved content diversity
- Additional courses beyond 2nd Grade Science

### 📋 Planned Features
- **Multimedia Enhancement**
  - Video lesson integration
  - Interactive diagrams and animations
  - Audio explanations
  - 3D visualizations for science concepts

- **Advanced Assessment**
  - Unit tests compilation (Agent 4)
  - Course validation (Agent 5)
  - Adaptive difficulty based on performance
  - Real-time progress dashboards

**Note:** Biometric security features have been removed for liability reasons.
  - Continuous monitoring
  - Cheating detection alerts

- **Social Learning**
  - Student collaboration tools
  - Discussion forums
  - Peer review system
  - Leaderboards and achievements

## API Documentation

### Authentication
- `POST /api/auth/login.php` - JWT-based login
  - Returns: `{success, token, user{userId, username, role}}`
- `POST /api/auth/logout.php` - Session termination

### Student Endpoints
- `GET /api/student/get_advisor.php` - Get student's personal advisor
  - Returns: Advisor instance with conversation history, progress notes
- `POST /api/student/update_advisor_data.php` - Update advisor data
  - Supports: conversation_turn, test_result, progress_notes
- `GET /api/course/get_available_courses.php` - List published courses
- `GET /api/course/get_lesson_content.php` - Fetch lesson content
  - Params: `courseId`, `unitIndex`, `lessonIndex`
  - Returns: Content text/HTML + question banks by type

### Admin Endpoints (Require JWT)
**Course Generation:**
- `POST /api/admin/generate_draft_outline.php` - Agent 1: Create outline
- `POST /api/admin/scrape_lesson_content.php` - Agent 2: Generate lessons
- `POST /api/admin/generate_lesson_questions.php` - Agent 3: Create questions
  - Supports batch generation (5 questions per call)
- `POST /api/admin/publish_course.php` - Publish course to students

**Management:**
- `GET /api/admin/list_student_advisors.php` - View all advisor instances
- `POST /api/admin/assign_student_advisor.php` - Create advisor for student
- `POST /api/admin/scraper_csp.php` - Scrape educational standards

**Content Review:**
- `GET /api/admin/get_lesson_content.php` - Preview lesson content
- `GET /api/admin/list_scraped_content.php` - List generated content

### Agent Microservice (C++)
- `POST /agent/chat` - Send message to AI agent
  - Body: `{userId, agentId, message}`
  - Returns: `{response, success}`
- `GET /agent/list` - List active agents
- `GET /health` - Health check endpoint

## Key Technical Details

### Database Schema
- **professorhawkeinstein_platform** (MariaDB)
- Key tables:
  - `users` - Student and admin accounts
  - `agents` - Agent templates (e.g., Professor Hawkeinstein)
  - `student_advisors` - Per-student advisor instances (1:1 mapping)
  - `courses` / `course_drafts` - Published courses and drafts
  - `draft_lesson_content` - Generated lesson content
  - `lesson_question_banks` - Question banks (JSON arrays by type)
  - `scraped_content` - Source content from CSP API

### LLM Configuration
- **Model**: Qwen 2.5 1.5B Instruct (quantized Q4_K_M)
- **Context**: 8192 tokens
- **Threads**: 4
- **System prompts**: 200-400 chars for speed
- **Max tokens**: 256 (chat), 1024-4800 (content generation)
- **Cache**: Prompt caching enabled, manual clearing with `clear_llm_cache.sh`

### Course Generation Pipeline
1. **Agent 1** (Outline): CSP standards → course structure
2. **Agent 2** (Lessons): Standards → 11K+ char educational content
3. **Agent 3** (Questions): Lessons → 3 question types (10 each target)
4. **Agent 4** (Unit Tests): *Planned* - Compile comprehensive tests
5. **Agent 5** (Validator): *Planned* - QA and content validation

### Memory Management
- Template-Instance pattern for advisors (prevents data leakage)
- Conversation history stored per student advisor
- Progress notes and testing results isolated per student
- llama-server prompt cache cleared periodically
- No cross-contamination between students

### Performance Optimizations
- Batch question generation (5 per LLM call)
- Prompt caching for repeated requests
- Compact system prompts (90% speed improvement)
- Deduplication only on exact matches
- Stop sequences to prevent infinite generation

## Troubleshooting

### Service Issues
```bash
# Check service status
docker ps

# View logs
docker logs phef-llama
docker logs phef-agent
docker logs phef-api

# Restart services
./start_services.sh

# Clear LLM cache if responses slow/repetitive
./clear_llm_cache.sh
```

### Database Connection
```bash
# Connect to database
docker exec -it phef-database mysql -uprofessorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform

# Check tables
SHOW TABLES;
```

### Agent Service
```bash
# Check health
curl http://localhost:8080/health

# Test chat
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"agentId":1,"message":"Hello"}'
```

### Common Issues
- **401 errors**: Check JWT token in browser sessionStorage
- **Agent timeouts**: Check `/tmp/agent_service_full.log`
- **LLM slow responses**: Clear prompt cache with `./clear_llm_cache.sh`
- **Content not displaying**: Verify 0-based indexing in API calls

## License

Proprietary - All rights reserved

## Contact

For questions about this implementation, refer to the inline documentation in each PHP file.

---

## Documentation

### Quick Start
- `SETUP_COMPLETE.md` - Quick start guide
- `PROJECT_OVERVIEW.md` - System architecture overview
- `FUTURE_IMPROVEMENTS.md` - Roadmap and planned features

### Technical Guides (in `docs/` folder)
- `docs/COURSE_GENERATION_API.md` - Course creation API reference
- `docs/COURSE_GENERATION_ARCHITECTURE.md` - 5-agent pipeline architecture
- `docs/AGENT_FACTORY_GUIDE.md` - Creating custom AI agents
- `docs/ADVISOR_INSTANCE_API.md` - Student advisor system API
- `docs/ASSESSMENT_GENERATION_API.md` - Quiz and test generation
- `docs/WORKBOOK_GUIDE.md` - Interactive workbook system

- `docs/RAG_ENGINE_README.md` - RAG implementation details
- `docs/FILE_SYNC_GUIDE.md` - Deployment automation
- `docs/ERROR_HANDLING_GUIDE.md` - Error handling patterns
- `docs/MEMORY_POLICY_QUICKREF.md` - Agent memory isolation

## Repository

https://github.com/stevemeierotto/Professor-Hawkeinstein
