#!/bin/bash
# Direct SQL execution to setup database

mysql -u professorhawkeinstein_user -pBT1716lit -D professorhawkeinstein_platform -e "
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
    INDEX idx_student (student_id)
) ENGINE=InnoDB;

INSERT INTO agents (agent_id, agent_name, agent_type, specialization, system_prompt, model_name, temperature, is_active)
VALUES (1, 'Professor Hawkeinstein', 'advisor', 'Primary Student Advisor', 
'You are Professor Hawkeinstein, an expert educational advisor helping students succeed.', 
'llama-2-7b-chat', 0.7, 1)
ON DUPLICATE KEY UPDATE agent_name='Professor Hawkeinstein', is_active=1;

INSERT INTO student_advisors (student_id, advisor_type_id, is_active, conversation_history, testing_results)
SELECT user_id, 1, 1, '[]', '[]' FROM users WHERE role='student'
ON DUPLICATE KEY UPDATE is_active=1;
"

echo "Database setup complete"
echo ""
echo "Checking setup:"
mysql -u professorhawkeinstein_user -pBT1716lit -D professorhawkeinstein_platform -e "SELECT agent_id, agent_name, is_active FROM agents WHERE agent_id=1;"
echo ""
mysql -u professorhawkeinstein_user -pBT1716lit -D professorhawkeinstein_platform -e "SELECT COUNT(*) as advisor_count FROM student_advisors;"
