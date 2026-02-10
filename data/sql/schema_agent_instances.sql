-- Agent Instances Table Migration
-- Unified table for both student and admin advisor instances
-- Replaces student_advisors with more flexible architecture

-- Create agent_instances table
CREATE TABLE IF NOT EXISTS agent_instances (
    instance_id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,  -- References agents.agent_id (the template)
    owner_id INT NOT NULL,  -- References users.user_id (student or admin)
    owner_type ENUM('student', 'admin') NOT NULL,
    model_path VARCHAR(500) NULL,  -- Optional: override model path for this instance
    conversation_history LONGTEXT NULL,  -- JSON array of conversation turns
    progress_notes TEXT NULL,
    testing_results LONGTEXT NULL,  -- JSON array of test results (students only)
    strengths_areas TEXT NULL,
    growth_areas TEXT NULL,
    custom_system_prompt TEXT NULL,  -- Override agent template's system prompt
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_interaction TIMESTAMP NULL,
    
    -- Foreign keys
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    -- Unique constraint: one instance per owner
    UNIQUE KEY unique_owner_instance (owner_id, owner_type),
    
    -- Indexes for performance
    INDEX idx_owner (owner_id, owner_type),
    INDEX idx_agent (agent_id),
    INDEX idx_active (is_active),
    INDEX idx_last_interaction (last_interaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Copy existing student_advisors data to agent_instances
-- Note: Run this after creating agent_instances table if student_advisors exists
-- INSERT INTO agent_instances 
--     (agent_id, owner_id, owner_type, conversation_history, progress_notes, 
--      testing_results, strengths_areas, growth_areas, custom_system_prompt, 
--      is_active, created_at, last_interaction)
-- SELECT 
--     advisor_type_id as agent_id,
--     student_id as owner_id,
--     'student' as owner_type,
--     conversation_history,
--     progress_notes,
--     testing_results,
--     strengths_areas,
--     growth_areas,
--     custom_system_prompt,
--     is_active,
--     created_at,
--     last_interaction
-- FROM student_advisors;

-- Add is_advisor flag to agents table if not exists
ALTER TABLE agents ADD COLUMN IF NOT EXISTS is_advisor TINYINT(1) DEFAULT 0;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS is_student_advisor TINYINT(1) DEFAULT 0;

-- Update existing advisor agents
UPDATE agents SET is_advisor = 1 WHERE agent_type IN ('student_advisor', 'advisor', 'mentor');
UPDATE agents SET is_student_advisor = 1 WHERE agent_type = 'student_advisor';

-- Optional: Create view for backward compatibility with student_advisors queries
CREATE OR REPLACE VIEW student_advisors AS
SELECT 
    instance_id as advisor_instance_id,
    owner_id as student_id,
    agent_id as advisor_type_id,
    conversation_history,
    progress_notes,
    testing_results,
    strengths_areas,
    growth_areas,
    custom_system_prompt,
    is_active,
    created_at,
    last_interaction
FROM agent_instances
WHERE owner_type = 'student';

-- Create view for admin advisors
CREATE OR REPLACE VIEW admin_advisors AS
SELECT 
    instance_id as advisor_instance_id,
    owner_id as admin_id,
    agent_id as advisor_type_id,
    conversation_history,
    progress_notes,
    custom_system_prompt,
    is_active,
    created_at,
    last_interaction
FROM agent_instances
WHERE owner_type = 'admin';
