# AI-Powered Educational Platform - Project Overview

## Summary

A complete web-based educational system with AI agents built on LAMP stack + C++ microservices. The system provides personalized learning through specialized AI tutors, progress-based evaluation (no traditional grades), and biometric anti-cheating measures.

## Completed Components ✅

### 1. Database Architecture (`schema.sql`)
- **Users & Sessions**: Authentication, biometric signatures, session tracking
- **AI Agents**: Agent configuration, models, personality settings
- **Agent Memories**: Conversation history with importance scoring
- **RAG Documents**: Content storage with chunking for retrieval
- **Embeddings**: Vector storage for semantic search (MariaDB 10.7+ vector support)
- **Courses**: Course catalog with difficulty levels and subjects
- **Course Assignments**: Student-course-agent relationships
- **Progress Tracking**: Non-grade metrics (mastery, completion, time, milestones)
- Sample data included for testing

### 2. Frontend Pages (HTML/CSS/JS)

#### `index.html` - Landing Page
- System overview and value proposition
- Feature showcase (6 key features)
- How it works (4-step process)
- AI agent profiles
- Call-to-action sections

#### `login.html` - Authentication
- Username/password login form
- Biometric authentication interface
  - Camera/microphone access
  - Facial recognition preview
  - Voice authentication
- Anti-cheating notice
- Demo credentials provided

#### `student_dashboard.html` - Student Hub
- Progress overview (4 metrics cards)
- AI agent chat interface with real-time messaging
- Active courses with progress bars
- Strengths & weaknesses visualization
- Sidebar navigation with active agent display

#### `course_viewer.html` - Lesson Interface
- Video player placeholder for multimedia content
- Lesson navigation sidebar
- Lesson content with examples
- Contextual AI agent chat
- Practice problems section
- Biometric monitoring alert
- Progress tracking

#### `styles.css` - Comprehensive Styling
- Modern, clean design with CSS variables
- Responsive grid layouts
- Interactive components (buttons, forms, cards)
- Chat interface styling
- Progress visualization (bars, badges)
- Utility classes
- Mobile-responsive breakpoints

#### `app.js` - Frontend Utilities
- **Auth module**: Login, logout, session management
- **Biometric module**: Camera/mic access, frame capture, monitoring
- **Agent module**: Chat communication, history retrieval
- **Progress module**: Overview, course progress, metric updates
- **Course module**: Enrollment management
- **UI utilities**: Loading spinners, alerts, formatting

### 3. PHP Backend API (`api/`)

#### Authentication (`api/auth/`)
- `login.php`: User authentication with JWT tokens
- `logout.php`: Session termination
- `validate.php`: Token validation

#### AI Agents (`api/agent/`)
- `chat.php`: Proxy to C++ microservice with conversation history
- `history.php`: Retrieve conversation history
- `list.php`: Get available agents

#### Biometric (`api/biometric/`)
- `verify-face.php`: Facial recognition verification
- `verify-voice.php`: Voice authentication
- Cheating flag tracking

#### Progress (`api/progress/`)
- `overview.php`: Student progress summary
- `course.php`: Course-specific metrics
- `update.php`: Update progress records

#### Courses (`api/course/`)
- `enrolled.php`: User's enrolled courses
- `detail.php`: Course information
- `available.php`: Available courses for enrollment

#### Configuration (`config/`)
- `database.php`: Database connection, JWT handling, security functions
- Helper functions for API responses
- C++ microservice communication wrapper

### 4. C++ Agent Microservice Stub (`cpp_agent_stub.cpp`)
- HTTP server framework using cpp-httplib
- Ollama client for LLM integration
- RAG engine structure (placeholder)
- Agent manager with conversation context
- Biometric processor (placeholder)
- Endpoints:
  - `/health` - Health check
  - `/api/chat` - Agent chat processing
  - `/api/biometric/verify-face` - Face verification
  - `/api/biometric/verify-voice` - Voice verification

### 5. Documentation
- `README.md`: Complete setup guide, architecture, API docs
- `PROJECT_OVERVIEW.md`: This file
- `.htaccess`: Apache configuration, security headers, routing

## Technical Decisions Made

### 1. **C++ HTTP Microservice** (vs FastCGI/Message Queue)
- Simple HTTP REST API for PHP ↔ C++ communication
- Easy to develop, test, and scale independently
- Clear separation of concerns

### 2. **MariaDB 10.7+ Vector Plugin** (vs Separate Vector DB)
- Single database solution reduces complexity
- Native vector search capabilities
- Easier deployment and maintenance

### 3. **Ollama for LLMs** (vs Cloud APIs)
- Self-hosted for privacy and cost control
- Support for multiple models (llama2, mistral)
- Note: Can switch to llama.cpp later for optimization

### 4. **Progress-Based System** (No Traditional Grades)
- Mastery percentage instead of letter grades
- Multiple metrics: completion, mastery, engagement, time
- Strengths/weaknesses tracking
- Milestone achievements

### 5. **Placement Test Deferred**
- Will be created by separate "agent factory" system
- Allows core platform development to proceed
- Expert agent can be added later without refactoring

## What's Implemented vs. What's Needed

### ✅ Fully Implemented
1. Complete database schema with relationships
2. All frontend pages with interactive UI
3. Full PHP backend API layer
4. Authentication and session management
5. Basic project structure and documentation

### ⏳ Stub/Placeholder Implementation
1. **C++ Agent Microservice** - Framework exists, needs:
   - Actual Ollama API integration
   - MariaDB connector for memory/RAG storage
   - Vector similarity search implementation
   - OpenCV facial recognition
   - Voice authentication processing

2. **Biometric Processing** - Frontend captures video/audio, backend needs:
   - OpenCV face detection and recognition
   - Face encoding and comparison
   - Voice feature extraction
   - Voice comparison algorithms

3. **RAG System** - Structure exists, needs:
   - Document chunking algorithms
   - Embedding generation via Ollama
   - Vector storage in MariaDB
   - Similarity search queries
   - Context retrieval and ranking

4. **Frontend-Backend Integration** - Mock data used, needs:
   - Replace localStorage with actual API calls
   - Real session management
   - Actual biometric data transmission
   - Live agent responses

## File Structure Summary

```
basic_educational/
├── index.html                  # Landing page
├── login.html                  # Authentication
├── student_dashboard.html      # Student hub
├── course_viewer.html          # Lesson viewer
├── styles.css                  # Global styles
├── app.js                      # Frontend utilities
├── schema.sql                  # Database schema
├── README.md                   # Setup guide
├── PROJECT_OVERVIEW.md         # This file
├── .htaccess                   # Apache config
├── cpp_agent_stub.cpp          # C++ microservice stub
├── config/
│   └── database.php            # DB config & utilities
└── api/
    ├── auth/                   # Authentication endpoints
    │   ├── login.php
    │   ├── logout.php
    │   └── validate.php
    ├── agent/                  # AI agent endpoints
    │   ├── chat.php
    │   ├── history.php
    │   └── list.php
    ├── biometric/              # Biometric verification
    │   ├── verify-face.php
    │   └── verify-voice.php
    ├── progress/               # Progress tracking
    │   ├── overview.php
    │   ├── course.php
    │   └── update.php
    └── course/                 # Course management
        ├── enrolled.php
        ├── detail.php
        └── available.php
```

## Next Steps (Priority Order)

### Phase 1: Core Functionality
1. **Set up development environment**
   - Install MariaDB 10.7+ with vector plugin
   - Configure Apache/PHP
   - Install Ollama and pull models
   - Set up database with sample data

2. **Test PHP backend**
   - Test authentication flow
   - Verify database connections
   - Test all API endpoints with Postman/curl

3. **Implement C++ microservice basics**
   - Set up build environment (g++, libcurl, cpp-httplib)
   - Implement Ollama API client
   - Test basic chat generation
   - Connect to PHP backend

### Phase 2: AI Agent Features
4. **Implement RAG system**
   - Document chunking and embedding generation
   - Store embeddings in MariaDB
   - Implement vector similarity search
   - Test context retrieval

5. **Complete agent memory system**
   - Store conversations in agent_memories table
   - Implement importance scoring
   - Add conversation context to prompts

### Phase 3: Biometric Integration
6. **Implement facial recognition**
   - OpenCV face detection
   - Face encoding and storage
   - Comparison algorithm
   - Integration with frontend

7. **Implement voice authentication**
   - Audio capture and processing
   - Voice feature extraction
   - Voice signature comparison

### Phase 4: Integration & Testing
8. **Frontend-backend integration**
   - Replace mock data with API calls
   - Test complete user flows
   - Add error handling

9. **Testing and refinement**
   - End-to-end testing
   - Performance optimization
   - Security hardening

### Phase 5: Advanced Features
10. **Agent Factory & Placement Test**
    - Design placement test agent
    - Implement agent creation system
    - Add automated agent assignment

## Current Status

**Stage**: Foundation Complete (Step 1 & 2 of Implementation)
**Progress**: ~35% (Architecture and placeholders done, core functionality needs implementation)
**Blockers**: None - ready to proceed with setup and C++ microservice implementation

## Demo Capability

The system can currently:
- ✅ Display all frontend pages with professional UI
- ✅ Navigate between pages
- ✅ Show biometric camera interface
- ✅ Display mock agent conversations
- ✅ Show progress visualization
- ✅ Login with demo credentials (frontend only)

The system cannot yet:
- ❌ Actually communicate with AI agents (Ollama)
- ❌ Store/retrieve real conversation data
- ❌ Perform actual biometric verification
- ❌ Track real progress in database
- ❌ Serve real multimedia content

## Technology Requirements

### Development
- Linux/macOS (or WSL on Windows)
- Apache 2.4+
- PHP 8.0+
- MariaDB 10.7+
- g++ with C++17 support
- Ollama
- libcurl, cpp-httplib, nlohmann/json
- OpenCV (for biometric features)

### Deployment
- LAMP server with MariaDB 10.7+
- Separate C++ microservice server
- Ollama instance
- SSL certificate (Let's Encrypt)

## License & Notes

This is a personal educational project demonstrating:
- Full-stack web development (LAMP)
- Systems programming (C++)
- AI integration (LLMs via Ollama)
- Biometric authentication
- RAG implementation
- RESTful API design
- Database design with vector search

**Next immediate action**: Follow README.md setup instructions to get development environment running.
