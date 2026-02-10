#!/bin/bash
# Setup student advisors and Professor Hawkeinstein

echo "Setting up student_advisors table and Professor Hawkeinstein agent..."

mysql -u professorhawkeinstein_user -p'BT1716lit' professorhawkeinstein_platform <<'EOF'

-- Create student_advisors table
CREATE TABLE IF NOT EXISTS student_advisors (
    advisor_instance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    advisor_type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    conversation_history JSON,
    progress_notes TEXT,
    testing_results JSON,
    strengths_areas TEXT,
    growth_areas TEXT,
    custom_system_prompt TEXT,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_type_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Professor Hawkeinstein agent
INSERT INTO agents (agent_id, agent_name, agent_type, specialization, system_prompt, model_name, temperature, is_active)
VALUES (
    1,
    'Professor Hawkeinstein',
    'advisor',
    'Primary Student Advisor and Homeroom Teacher',
    'You are Professor Hawkeinstein, an expert educational advisor. You help students with their learning journey, provide guidance, and support their academic growth.',
    'llama-2-7b-chat',
    0.7,
    1
)
ON DUPLICATE KEY UPDATE 
    agent_name = 'Professor Hawkeinstein',
    is_active = 1,
    model_name = 'llama-2-7b-chat';

-- Show current agents
SELECT agent_id, agent_name, agent_type, is_active FROM agents WHERE agent_id = 1;

-- Create advisor assignments for all students
INSERT INTO student_advisors (student_id, advisor_type_id, is_active, conversation_history, testing_results)
SELECT user_id, 1, 1, '[]', '[]'
FROM users
WHERE role = 'student'
ON DUPLICATE KEY UPDATE is_active = 1;

-- Show student advisor assignments
SELECT sa.advisor_instance_id, sa.student_id, u.username, a.agent_name, sa.is_active
FROM student_advisors sa
JOIN users u ON sa.student_id = u.user_id
JOIN agents a ON sa.advisor_type_id = a.agent_id
LIMIT 5;

EOF

echo "Setup complete!"
