-- Migration: Create scraped_standards table for normalized CSP standards
-- Date: 2025-11-30
-- Purpose: Store raw and normalized standards from CSP API with 30-day TTL

CREATE TABLE IF NOT EXISTS scraped_standards (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    jurisdiction_id VARCHAR(100) NOT NULL COMMENT 'CSP jurisdiction UUID',
    grade_level VARCHAR(50) NOT NULL COMMENT 'Grade level (e.g., grade_1)',
    subject VARCHAR(100) NOT NULL COMMENT 'Subject area (e.g., mathematics)',
    raw_standards LONGTEXT NOT NULL COMMENT 'Original JSON from CSP API',
    simplified_skills LONGTEXT NOT NULL COMMENT 'Normalized skills array with UUIDs',
    raw_count INT NOT NULL COMMENT 'Number of raw standards fetched',
    skills_count INT NOT NULL COMMENT 'Number of normalized skills created',
    scraped_by INT NOT NULL COMMENT 'Admin user ID who initiated scrape',
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When standards were fetched',
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY) COMMENT 'Auto-expire after 30 days',
    metadata JSON COMMENT 'Additional scraping metadata (API version, strategy, etc.)',
    
    INDEX idx_jurisdiction (jurisdiction_id),
    INDEX idx_grade_subject (grade_level, subject),
    INDEX idx_expires (expires_at),
    INDEX idx_scraped_by (scraped_by),
    INDEX idx_composite (jurisdiction_id, grade_level, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cleanup job query (run periodically via cron)
-- DELETE FROM scraped_standards WHERE expires_at < NOW();
