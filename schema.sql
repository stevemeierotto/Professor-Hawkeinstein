-- AI-Powered Educational Platform Database Schema
-- MariaDB 10.7+ with Vector Plugin Support
-- Date: November 17, 2025

-- Drop existing tables if recreating
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS quiz_configurations;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS units;
DROP TABLE IF EXISTS training_exports;
DROP TABLE IF EXISTS content_reviews;
DROP TABLE IF EXISTS educational_content;
DROP TABLE IF EXISTS admin_activity_log;
DROP TABLE IF EXISTS progress_tracking;
DROP TABLE IF EXISTS rag_documents;
DROP TABLE IF EXISTS embeddings;
DROP TABLE IF EXISTS agent_memories;
DROP TABLE IF EXISTS course_assignments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS agents;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- Users table: Students, admins, and root superuser
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'admin', 'root') DEFAULT 'student',
    created_by INT NULL,  -- For admins created by root
    -- DEPRECATED: facial_signature and voice_signature columns removed for liability with minors
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table: Track user sessions
CREATE TABLE sessions (
    session_id VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agents table: AI expert agents with specializations
CREATE TABLE agents (
    agent_id INT AUTO_INCREMENT PRIMARY KEY,
    agent_name VARCHAR(100) NOT NULL,
    agent_type VARCHAR(50) NOT NULL,  -- e.g., 'math_tutor', 'language_expert', 'science_guide'
    specialization TEXT,  -- Detailed description of expertise
    personality_config JSON,  -- Agent personality parameters
    model_name VARCHAR(100),  -- Ollama model to use (e.g., 'llama2', 'mistral')
    system_prompt TEXT,  -- Base system prompt for this agent
    temperature DECIMAL(3,2) DEFAULT 0.7,
    max_tokens INT DEFAULT 2048,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_type (agent_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agent Memories table: Per-agent conversation and interaction history
CREATE TABLE agent_memories (
    memory_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    user_id INT NOT NULL,
    interaction_type ENUM('chat', 'lesson', 'assessment', 'feedback') NOT NULL,
    user_message TEXT,
    agent_response TEXT,
    context_used TEXT,  -- RAG context that was retrieved
    metadata JSON,  -- Additional structured data
    importance_score DECIMAL(3,2) DEFAULT 0.5,  -- For memory prioritization
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_agent_user (agent_id, user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_importance (importance_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RAG Documents table: Content for Retrieval-Augmented Generation
CREATE TABLE rag_documents (
    document_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NULL,  -- NULL means shared across all agents
    document_title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50),  -- 'lesson', 'reference', 'example', 'textbook'
    content TEXT NOT NULL,
    content_chunk TEXT NOT NULL,  -- Chunked for embedding
    chunk_index INT DEFAULT 0,
    source_url VARCHAR(500) NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    INDEX idx_agent_id (agent_id),
    INDEX idx_document_type (document_type),
    FULLTEXT idx_content (content, content_chunk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Embeddings table: Vector embeddings for RAG and semantic search
-- Note: MariaDB 10.7+ supports VECTOR type or use BLOB with application-level handling
CREATE TABLE embeddings (
    embedding_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('rag_document', 'agent_memory', 'user_query') NOT NULL,
    source_id BIGINT NOT NULL,  -- References document_id or memory_id
    agent_id INT NULL,
    embedding_vector BLOB NOT NULL,  -- Store as serialized float array or use VECTOR type if available
    vector_dimension INT DEFAULT 768,  -- e.g., 768 for many models, 1536 for OpenAI
    model_used VARCHAR(100),  -- Which embedding model generated this
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    INDEX idx_source (source_type, source_id),
    INDEX idx_agent_id (agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courses table: Learning content organized into courses
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL,
    course_description TEXT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL,
    subject_area VARCHAR(100),  -- 'mathematics', 'science', 'language', etc.
    recommended_agent_id INT NULL,  -- Suggested expert agent for this course
    multimedia_path VARCHAR(500),  -- Path to video/audio/interactive content
    estimated_hours DECIMAL(5,2),
    prerequisites JSON,  -- Array of prerequisite course_ids
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recommended_agent_id) REFERENCES agents(agent_id) ON DELETE SET NULL,
    INDEX idx_difficulty (difficulty_level),
    INDEX idx_subject (subject_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course Assignments table: Which students are enrolled in which courses
CREATE TABLE course_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    agent_id INT NOT NULL,  -- Assigned expert agent
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status ENUM('assigned', 'in_progress', 'completed', 'paused') DEFAULT 'assigned',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_course (user_id, course_id),
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress Tracking table: Non-grade progress metrics for students
CREATE TABLE progress_tracking (
    progress_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    agent_id INT NOT NULL,
    metric_type VARCHAR(50) NOT NULL,  -- 'completion', 'mastery', 'engagement', 'time_spent'
    metric_value DECIMAL(5,2) NOT NULL,  -- Percentage or numeric value
    milestone VARCHAR(100),  -- Specific achievement or checkpoint
    strengths TEXT,  -- JSON array of identified strengths
    weaknesses TEXT,  -- JSON array of identified weaknesses
    notes TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    INDEX idx_user_course (user_id, course_id),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for development

-- Insert sample admin user (password: 'admin123')
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@professorhawkeinstein.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Sample student user (password: 'student123')
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('john_doe', 'john.doe@student.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'student');

-- Sample AI agents
INSERT INTO agents (agent_name, agent_type, specialization, model_name, system_prompt) VALUES
('Professor Hawkeinstein', 'math_tutor', 'Advanced mathematics, calculus, algebra, and problem-solving', 'llama2', 'You are Professor Hawkeinstein, an expert mathematics tutor. You help students understand complex mathematical concepts through clear explanations and step-by-step problem solving.'),
('Dr. Chen', 'science_guide', 'Physics, chemistry, and general science education', 'mistral', 'You are Dr. Chen, a passionate science educator. You make scientific concepts accessible and exciting through real-world examples and interactive explanations.'),
('Ms. Rodriguez', 'language_expert', 'English language arts, writing, and literature', 'llama2', 'You are Ms. Rodriguez, an experienced language arts teacher. You help students improve their reading comprehension, writing skills, and appreciation for literature.'),
('Agent Zero', 'general_tutor', 'General education assistant for initial student interaction', 'llama2', 'You are a helpful educational assistant. You guide students through the learning platform and help them get started with their personalized learning journey.');

-- Sample courses
INSERT INTO courses (course_name, course_description, difficulty_level, subject_area, recommended_agent_id, estimated_hours) VALUES
('Algebra Fundamentals', 'Introduction to algebraic concepts, equations, and problem-solving techniques', 'beginner', 'mathematics', 1, 20.0),
('Calculus I', 'Differential and integral calculus for beginners', 'intermediate', 'mathematics', 1, 40.0),
('Introduction to Physics', 'Basic principles of motion, energy, and forces', 'beginner', 'science', 2, 30.0),
('Creative Writing Workshop', 'Develop your creative writing skills through practice and feedback', 'intermediate', 'language_arts', 3, 25.0);

-- Sample RAG document
INSERT INTO rag_documents (agent_id, document_title, document_type, content, content_chunk) VALUES
(1, 'Quadratic Equations Guide', 'lesson', 'A quadratic equation is a second-order polynomial equation in a single variable x: ax²+bx+c=0. The solutions can be found using the quadratic formula: x = (-b ± √(b²-4ac)) / 2a', 'Quadratic equations are polynomial equations of degree 2. They have the standard form ax²+bx+c=0 where a≠0.');

-- Note: Embeddings would be generated by the C++ microservice and inserted via API
-- Progress tracking would be populated as students interact with the system

-- Admin Activity Log: Track all admin actions
CREATE TABLE admin_activity_log (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,  -- e.g., 'AGENT_CREATED', 'CONTENT_APPROVED', 'COURSE_UPDATED'
    details TEXT NOT NULL,
    metadata TEXT,  -- JSON with additional context
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Educational Content: Store AI-generated and curated educational content
CREATE TABLE educational_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(2048) NOT NULL,
    title VARCHAR(500),
    content_type VARCHAR(50) DEFAULT 'educational',  -- 'educational', 'ai_generated', 'lesson', 'reference'
    content_html LONGTEXT NOT NULL,  -- Full HTML content
    content_text LONGTEXT,  -- Cleaned text content
    video_url VARCHAR(255) DEFAULT NULL,  -- YouTube video ID or URL
    metadata TEXT,  -- JSON: author, date_published, keywords, etc.
    credibility_score DECIMAL(3,2) DEFAULT 0.00,  -- 0.00-1.00 rating
    domain VARCHAR(255),  -- Extracted domain name
    scraped_by INT NOT NULL,  -- Admin user who created/imported content
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_status ENUM('pending', 'approved', 'rejected', 'needs_revision') DEFAULT 'pending',
    reviewed_by INT NULL,  -- Admin user who reviewed
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,  -- Feedback from reviewer
    is_added_to_rag BOOLEAN DEFAULT FALSE,  -- Whether content was added to RAG system
    grade_level VARCHAR(50),  -- e.g., 'grade_1', 'grade_2', 'high_school', 'college'
    subject VARCHAR(100),  -- 'mathematics', 'science', 'language_arts', etc.
    FOREIGN KEY (scraped_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_url (url(255)),
    INDEX idx_review_status (review_status),
    INDEX idx_scraped_at (scraped_at),
    INDEX idx_grade_subject (grade_level, subject),
    INDEX idx_video_url (video_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content Reviews: Detailed review records
CREATE TABLE content_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    accuracy_score DECIMAL(3,2),  -- 0.00-1.00, how accurate is the content
    relevance_score DECIMAL(3,2),  -- 0.00-1.00, how relevant for target audience
    quality_score DECIMAL(3,2),  -- 0.00-1.00, overall quality rating
    strengths TEXT,  -- What's good about this content
    weaknesses TEXT,  -- What needs improvement
    fact_check_notes TEXT,  -- Specific facts verified or disputed
    recommendation ENUM('approve', 'reject', 'revise') NOT NULL,
    revision_needed TEXT,  -- Specific changes required
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES educational_content(content_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_content_id (content_id),
    INDEX idx_reviewer_id (reviewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Exports: Track model fine-tuning data exports
CREATE TABLE training_exports (
    export_id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT,  -- NULL if exporting for all agents
    export_name VARCHAR(255) NOT NULL,
    export_format VARCHAR(50) DEFAULT 'jsonl',  -- 'jsonl', 'json', 'csv'
    min_importance_score DECIMAL(3,2) DEFAULT 0.70,  -- Filter by message importance
    date_from TIMESTAMP NULL,  -- Optional date range
    date_to TIMESTAMP NULL,
    total_conversations INT DEFAULT 0,
    total_messages INT DEFAULT 0,
    file_path VARCHAR(500),  -- Where the export file is stored
    file_size_bytes BIGINT,
    metadata TEXT,  -- JSON with export parameters and statistics
    exported_by INT NOT NULL,
    exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_used_for_finetuning BOOLEAN DEFAULT FALSE,
    finetuning_notes TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    FOREIGN KEY (exported_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_agent_id (agent_id),
    INDEX idx_exported_at (exported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Units: Course units generated by agents
CREATE TABLE units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    agent_id INT NOT NULL,  -- Agent that generated this unit
    unit_number INT NOT NULL,
    unit_title VARCHAR(255) NOT NULL,
    unit_description TEXT,
    learning_objectives TEXT,  -- JSON array of objectives
    total_lessons INT DEFAULT 0,
    estimated_hours DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_unit (course_id, unit_number),
    INDEX idx_course_id (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lessons: Individual lessons within units
CREATE TABLE lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lesson_number INT NOT NULL,
    lesson_title VARCHAR(255) NOT NULL,
    lesson_content LONGTEXT NOT NULL,  -- Main lesson text/HTML
    video_url VARCHAR(255) DEFAULT NULL,  -- YouTube video ID or URL
    lesson_objectives TEXT,  -- JSON array of specific objectives
    key_concepts TEXT,  -- JSON array of key concepts
    examples TEXT,  -- JSON with worked examples
    practice_problems TEXT,  -- JSON with practice problems
    estimated_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
    UNIQUE KEY unique_unit_lesson (unit_id, lesson_number),
    INDEX idx_unit_id (unit_id),
    INDEX idx_video_url (video_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quiz Questions: Questions for lessons, units, and finals
CREATE TABLE quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NULL,  -- NULL for unit/final questions
    unit_id INT NULL,  -- For unit-level questions
    course_id INT NULL,  -- For final exam questions
    question_type ENUM('lesson', 'unit', 'final') NOT NULL,
    question_text TEXT NOT NULL,
    question_format ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
    options TEXT,  -- JSON array of answer options
    correct_answer TEXT NOT NULL,
    explanation TEXT,  -- Explanation of correct answer
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_lesson_id (lesson_id),
    INDEX idx_unit_id (unit_id),
    INDEX idx_course_id (course_id),
    INDEX idx_question_type (question_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quiz Configurations: Admin-configurable quiz settings
CREATE TABLE quiz_configurations (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lesson_quiz_pool_size INT DEFAULT 100,  -- Total questions per lesson
    lesson_quiz_question_count INT DEFAULT 10,  -- Questions shown in quiz
    unit_quiz_pool_size INT DEFAULT 250,  -- Total questions per unit
    unit_quiz_question_count INT DEFAULT 25,  -- Questions shown in unit test
    final_quiz_pool_size INT DEFAULT 1000,  -- Total questions for final
    final_quiz_question_count INT DEFAULT 100,  -- Questions shown in final
    passing_percentage DECIMAL(5,2) DEFAULT 70.00,
    allow_retakes BOOLEAN DEFAULT TRUE,
    show_correct_answers BOOLEAN DEFAULT TRUE,
    randomize_questions BOOLEAN DEFAULT TRUE,
    randomize_answers BOOLEAN DEFAULT TRUE,
    time_limit_minutes INT NULL,  -- NULL = no time limit
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_config (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
