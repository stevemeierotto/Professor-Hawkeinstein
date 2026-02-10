#!/bin/bash
# Database Setup Script for Agent Factory System
# Run this after updating schema.sql

echo "🚀 Setting up Agent Factory database tables..."

# Database credentials
DB_HOST="localhost"
DB_NAME="professorhawkeinstein_platform"
DB_USER="professorhawkeinstein_user"
DB_PASS="BT1716lit"

# Create media directory for exports
echo "📁 Creating media directories..."
mkdir -p media/training_exports
chmod 755 media/training_exports
echo "✓ Directories created"

# Apply database schema updates
echo "🗄️  Updating database schema..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF

-- Add new tables for Agent Factory system

-- Admin Activity Log
CREATE TABLE IF NOT EXISTS admin_activity_log (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NOT NULL,
    metadata TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scraped Content
CREATE TABLE IF NOT EXISTS scraped_content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    source_url VARCHAR(2048) NOT NULL,
    page_title VARCHAR(500),
    content_type VARCHAR(50) DEFAULT 'educational',
    raw_content LONGTEXT NOT NULL,
    extracted_text LONGTEXT,
    metadata TEXT,
    credibility_score DECIMAL(3,2) DEFAULT 0.00,
    domain VARCHAR(255),
    scraped_by INT NOT NULL,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_status ENUM('pending', 'approved', 'rejected', 'needs_revision') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    is_added_to_rag BOOLEAN DEFAULT FALSE,
    grade_level VARCHAR(50),
    subject_area VARCHAR(100),
    FOREIGN KEY (scraped_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_source_url (source_url(255)),
    INDEX idx_review_status (review_status),
    INDEX idx_scraped_at (scraped_at),
    INDEX idx_grade_subject (grade_level, subject_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content Reviews
CREATE TABLE IF NOT EXISTS content_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    accuracy_score DECIMAL(3,2),
    relevance_score DECIMAL(3,2),
    quality_score DECIMAL(3,2),
    strengths TEXT,
    weaknesses TEXT,
    fact_check_notes TEXT,
    recommendation ENUM('approve', 'reject', 'revise') NOT NULL,
    revision_needed TEXT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES scraped_content(content_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_content_id (content_id),
    INDEX idx_reviewer_id (reviewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Exports
CREATE TABLE IF NOT EXISTS training_exports (
    export_id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT,
    export_name VARCHAR(255) NOT NULL,
    export_format VARCHAR(50) DEFAULT 'jsonl',
    min_importance_score DECIMAL(3,2) DEFAULT 0.70,
    date_from TIMESTAMP NULL,
    date_to TIMESTAMP NULL,
    total_conversations INT DEFAULT 0,
    total_messages INT DEFAULT 0,
    file_path VARCHAR(500),
    file_size_bytes BIGINT,
    metadata TEXT,
    exported_by INT NOT NULL,
    exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_used_for_finetuning BOOLEAN DEFAULT FALSE,
    finetuning_notes TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    FOREIGN KEY (exported_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_agent_id (agent_id),
    INDEX idx_exported_at (exported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

EOF

if [ $? -eq 0 ]; then
    echo "✓ Database tables created successfully"
else
    echo "✗ Error creating database tables"
    exit 1
fi

# Verify admin user exists
echo "👤 Verifying admin user..."
ADMIN_EXISTS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM users WHERE username='admin' AND role='admin'")

if [ "$ADMIN_EXISTS" -eq 0 ]; then
    echo "Creating admin user..."
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO users (username, email, password_hash, full_name, role) 
VALUES ('admin', 'admin@professorhawkeinstein.org', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');
EOF
    echo "✓ Admin user created (username: admin, password: admin123)"
else
    echo "✓ Admin user already exists"
fi

echo ""
echo "✅ Setup complete!"
echo ""
echo "📋 Next steps:"
echo "1. Navigate to http://your-domain/admin_dashboard.html"
echo "2. Login with username: admin, password: admin123"
echo "3. Start scraping educational content"
echo "4. Review and approve content"
echo "5. Create specialized agents with the Agent Factory"
echo ""
echo "📖 See AGENT_FACTORY_GUIDE.md for detailed instructions"
