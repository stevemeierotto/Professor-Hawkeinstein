#!/bin/bash
# Setup Root Admin and Enhanced Schema
# This initializes the agent factory system with root superuser

echo "🚀 Setting up Enhanced Agent Factory System..."

DB_HOST="localhost"
DB_NAME="professorhawkeinstein_platform"
DB_USER="professorhawkeinstein_user"
DB_PASS="BT1716lit"

# Create media directories
echo "📁 Creating directories..."
mkdir -p /var/www/html/Professor_Hawkeinstein/media/training_exports
chmod 755 /var/www/html/Professor_Hawkeinstein/media/training_exports
echo "✓ Directories created"

# Apply schema updates
echo "🗄️  Updating database schema..."

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<'EOF'

-- Modify users table to add root role
ALTER TABLE users 
    MODIFY COLUMN role ENUM('student', 'admin', 'root') DEFAULT 'student',
    ADD COLUMN created_by INT NULL AFTER role,
    ADD FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Create Units table
CREATE TABLE IF NOT EXISTS units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    agent_id INT NOT NULL,
    unit_number INT NOT NULL,
    unit_title VARCHAR(255) NOT NULL,
    unit_description TEXT,
    learning_objectives TEXT,
    total_lessons INT DEFAULT 0,
    estimated_hours DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_unit (course_id, unit_number),
    INDEX idx_course_id (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Lessons table
CREATE TABLE IF NOT EXISTS lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lesson_number INT NOT NULL,
    lesson_title VARCHAR(255) NOT NULL,
    lesson_content LONGTEXT NOT NULL,
    lesson_objectives TEXT,
    key_concepts TEXT,
    examples TEXT,
    practice_problems TEXT,
    estimated_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE CASCADE,
    UNIQUE KEY unique_unit_lesson (unit_id, lesson_number),
    INDEX idx_unit_id (unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Quiz Questions table
CREATE TABLE IF NOT EXISTS quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NULL,
    unit_id INT NULL,
    course_id INT NULL,
    question_type ENUM('lesson', 'unit', 'final') NOT NULL,
    question_text TEXT NOT NULL,
    question_format ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
    options TEXT,
    correct_answer TEXT NOT NULL,
    explanation TEXT,
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

-- Create Quiz Configurations table
CREATE TABLE IF NOT EXISTS quiz_configurations (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lesson_quiz_pool_size INT DEFAULT 100,
    lesson_quiz_question_count INT DEFAULT 10,
    unit_quiz_pool_size INT DEFAULT 250,
    unit_quiz_question_count INT DEFAULT 25,
    final_quiz_pool_size INT DEFAULT 1000,
    final_quiz_question_count INT DEFAULT 100,
    passing_percentage DECIMAL(5,2) DEFAULT 70.00,
    allow_retakes BOOLEAN DEFAULT TRUE,
    show_correct_answers BOOLEAN DEFAULT TRUE,
    randomize_questions BOOLEAN DEFAULT TRUE,
    randomize_answers BOOLEAN DEFAULT TRUE,
    time_limit_minutes INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_config (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

EOF

if [ $? -eq 0 ]; then
    echo "✓ Database schema updated successfully"
else
    echo "✗ Error updating database schema"
    exit 1
fi

# Create root superuser (password: root1234)
echo "👑 Creating root superuser..."

# Password hash for 'root1234' (bcrypt)
ROOT_HASH='$2y$10$YourHashHereForRoot1234'

# Use PHP to generate proper bcrypt hash
ROOT_HASH=$(php -r "echo password_hash('root1234', PASSWORD_BCRYPT);")

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
-- Remove existing root user if exists
DELETE FROM users WHERE username='root';

-- Insert root superuser
INSERT INTO users (username, email, password_hash, full_name, role, created_by, created_at) 
VALUES ('root', 'root@system.local', '$ROOT_HASH', 'Root Administrator', 'root', NULL, NOW());

EOF

if [ $? -eq 0 ]; then
    echo "✓ Root user created successfully"
    echo ""
    echo "═══════════════════════════════════════════"
    echo "  ROOT CREDENTIALS"
    echo "═══════════════════════════════════════════"
    echo "  Username: root"
    echo "  Password: root1234"
    echo "═══════════════════════════════════════════"
    echo ""
else
    echo "✗ Error creating root user"
    exit 1
fi

# Set proper permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/html/Professor_Hawkeinstein/media 2>/dev/null || echo "⚠ Could not set www-data ownership (may need sudo)"
chmod -R 755 /var/www/html/Professor_Hawkeinstein/media

echo ""
echo "✅ Setup complete!"
echo ""
echo "📋 Next steps:"
echo "1. Navigate to: http://localhost/Professor_Hawkeinstein/admin_dashboard.html"
echo "2. Login with root credentials (username: root, password: root1234)"
echo "3. Create admin users from the User Management panel"
echo "4. Admins can then create and manage expert agents"
echo ""
echo "🎯 Root privileges:"
echo "   ✓ Create/edit/delete admin accounts"
echo "   ✓ All admin capabilities"
echo ""
echo "🔧 Admin privileges:"
echo "   ✓ Create/modify expert agents"
echo "   ✓ Scrape and review content"
echo "   ✓ Configure quiz settings"
echo "   ✓ Generate course content with agents"
echo ""
