# Professor Hawkeinstein Educational Platform

An adaptive learning system with AI agents that assess, track, and teach students using biometric anti-cheating and progress-based evaluation. Powered by local LLM inference via llama-server.

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP 8.0+ (LAMP Stack)
- **Database**: MariaDB 10.7+
- **AI Agents**: C++ HTTP microservice (agent_service)
- **LLM**: Self-hosted models via llama-server (qwen2.5-1.5b-instruct)
- **Biometric**: OpenCV for facial recognition, Web Audio API for voice

## Architecture Overview

```
┌─────────────────┐
│  Web Browser    │
│  (HTML/CSS/JS)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Apache/PHP     │
│  API Layer      │
└────┬────────────┘
     │
     ├─────────────────┐
     │                 │
     ▼                 ▼
┌─────────────┐  ┌──────────────┐
│  MariaDB    │  │ C++ Agent    │
│  Database   │  │ Microservice │
│  + Vectors  │  │  (HTTP API)  │
└─────────────┘  └──────┬───────┘
                        │
                        ▼
                   ┌──────────────┐
                   │ llama-server │
                   │   :8090      │
                   └──────────────┘
```

## Features

- ✅ **Expert AI Agents** - Specialized tutors for different subjects
- ✅ **Progress-Based Learning** - No traditional grades, mastery-focused
- ✅ **Biometric Authentication** - Facial recognition and voice verification
- ✅ **Adaptive Curriculum** - Courses at any level (remedial to advanced)
- ✅ **RAG System** - Retrieval-augmented generation for contextual responses
- ✅ **Agent Memory** - Persistent conversation and learning history
- ✅ **Multimedia Content** - Video lessons and interactive materials
- ✅ **Anti-Cheating** - Continuous biometric monitoring during sessions

## Project Structure

```
basic_educational/
├── index.html                 # Landing page
├── login.html                 # Login with biometric authentication
├── student_dashboard.html     # Student dashboard with AI chat
├── course_viewer.html         # Course content and lesson viewer
├── styles.css                 # Global styles
├── app.js                     # Frontend utilities
├── schema.sql                 # Database schema
├── config/
│   └── database.php           # Database config and utilities
├── api/
│   ├── auth/
│   │   ├── login.php          # User authentication
│   │   ├── logout.php         # Session termination
│   │   └── validate.php       # Token validation
│   ├── agent/
│   │   ├── chat.php           # AI agent communication proxy
│   │   ├── history.php        # Conversation history
│   │   └── list.php           # Available agents
│   ├── biometric/
│   │   ├── verify-face.php    # Facial recognition
│   │   └── verify-voice.php   # Voice authentication
│   ├── progress/
│   │   ├── overview.php       # Student progress summary
│   │   ├── course.php         # Course-specific progress
│   │   └── update.php         # Update progress metrics
│   └── course/
│       ├── enrolled.php       # User's enrolled courses
│       ├── detail.php         # Course details
│       └── available.php      # Available courses
└── cpp_agent/                 # C++ microservice (to be implemented)
    ├── main.cpp
    ├── agent_manager.cpp
    ├── rag_engine.cpp
    └── ollama_client.cpp
```

## Setup Instructions

### 1. Database Setup

```bash
# Install MariaDB 10.7+
sudo apt-get install mariadb-server

# Create database and user
sudo mysql
```

```sql
CREATE DATABASE professorhawkeinstein_platform;
CREATE USER 'professorhawkeinstein_user'@'localhost' IDENTIFIED BY 'BT1716lit';
GRANT ALL PRIVILEGES ON professorhawkeinstein_platform.* TO 'professorhawkeinstein_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Import schema
mysql -u professorhawkeinstein_user -p professorhawkeinstein_platform < schema.sql
```

### 2. PHP Configuration

Update `config/database.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'professorhawkeinstein_platform');
define('DB_USER', 'professorhawkeinstein_user');
define('DB_PASS', 'BT1716lit');
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
- `POST /api/biometric/verify-face` - Facial recognition
- `POST /api/biometric/verify-voice` - Voice authentication
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

## Development Roadmap

### Phase 1: Foundation (Current)
- ✅ Database schema design
- ✅ Frontend pages with placeholders
- ✅ PHP API endpoints
- ✅ Basic authentication system

### Phase 2: C++ Agent Microservice
- ✅ HTTP server implementation
- ✅ llama-server integration
- ✅ RAG engine with embedding search
- ✅ Memory management system
- ✅ Embedding generation

### Phase 3: Biometric Integration
- ⏳ OpenCV facial recognition
- ⏳ Voice authentication
- ⏳ Continuous monitoring
- ⏳ Cheating detection alerts

### Phase 4: Agent Features
- ⏳ Conversation context management
- ⏳ Personalized responses
- ⏳ Learning style adaptation
- ⏳ Progress-driven recommendations

### Phase 5: Content & Courses
- ⏳ Multimedia content upload
- ⏳ Lesson management
- ⏳ Interactive exercises
- ⏳ Assessment tools

### Phase 6: Agent Factory (Future)
- ⏳ Placement test agent
- ⏳ Dynamic agent creation
- ⏳ Specialized agent training
- ⏳ Agent performance optimization

## API Documentation

### Authentication
- `POST /api/auth/login.php` - User login
- `POST /api/auth/logout.php` - User logout
- `GET /api/auth/validate.php` - Validate session token

### AI Agents
- `POST /api/agent/chat.php` - Send message to agent
- `GET /api/agent/history.php?agentId={id}&limit={n}` - Get conversation history
- `GET /api/agent/list.php` - List available agents

### Progress Tracking
- `GET /api/progress/overview.php` - Get student progress overview
- `GET /api/progress/course.php?courseId={id}` - Get course progress
- `POST /api/progress/update.php` - Update progress metric

### Courses
- `GET /api/course/enrolled.php` - Get enrolled courses
- `GET /api/course/detail.php?courseId={id}` - Get course details
- `GET /api/course/available.php` - Get available courses

### Biometric
- `POST /api/biometric/verify-face.php` - Verify facial recognition
- `POST /api/biometric/verify-voice.php` - Verify voice authentication

## Security Considerations

1. **Change default secrets** in `config/database.php`
2. **Use HTTPS** in production (Let's Encrypt)
3. **Rate limiting** on API endpoints
4. **Input validation** and SQL injection prevention (using PDO prepared statements)
5. **Secure password storage** (Argon2ID with pepper)
6. **Session management** with secure tokens
7. **Biometric data encryption** at rest
8. **CORS configuration** for production

## Quick Start

For rapid deployment:

```bash
# Clone repository
git clone https://github.com/stevemeierotto/Professor-Hawkeinstein.git
cd Professor-Hawkeinstein

# Start services
./start_services.sh

# Deploy to web directory
make sync-web
```

See `SETUP_COMPLETE.md` for detailed setup instructions.

## License

Proprietary - All rights reserved

## Contact

For questions about this implementation, refer to the inline documentation in each PHP file.

---

## Documentation

- `SETUP_COMPLETE.md` - Complete setup guide
- `AUTH_FORMAT_CENTRALIZATION.md` - Authentication system documentation
- `FILE_SYNC_GUIDE.md` - Deployment automation
- `ADVISOR_INSTANCE_API.md` - Student advisor system API
- `RAG_ENGINE_README.md` - RAG implementation details

## Repository

https://github.com/stevemeierotto/Professor-Hawkeinstein
