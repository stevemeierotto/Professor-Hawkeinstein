#!/bin/bash
set -x

echo "=== Database Setup ==="
mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform << 'EOSQL'
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
    INDEX idx_student (student_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO agents (agent_id, agent_name, agent_type, specialization, system_prompt, model_name, temperature, is_active)
VALUES (1, 'Professor Hawkeinstein', 'advisor', 'Primary Student Advisor', 
'You are Professor Hawkeinstein, an expert educational advisor.', 'llama-2-7b-chat', 0.7, 1)
ON DUPLICATE KEY UPDATE agent_name='Professor Hawkeinstein', is_active=1;

INSERT INTO student_advisors (student_id, advisor_type_id, is_active, conversation_history, testing_results)
SELECT user_id, 1, 1, '[]', '[]' FROM users WHERE role='student'
ON DUPLICATE KEY UPDATE is_active=1;

SELECT 'Agents:' as info;
SELECT agent_id, agent_name, is_active FROM agents WHERE agent_id=1;

SELECT 'Student Advisors:' as info;
SELECT COUNT(*) as count FROM student_advisors;
EOSQL

echo ""
echo "=== Starting C++ Agent Service ==="
cd ~/Professor_Hawkeinstein/cpp_agent
pkill -9 -f agent_service 2>/dev/null
sleep 1
./bin/agent_service > /tmp/agent.log 2>&1 &
SERVICE_PID=$!
echo "Service started with PID: $SERVICE_PID"
sleep 3

echo ""
echo "=== Testing Service ==="
curl -s http://localhost:8080/health || echo "Service not responding"

echo ""
echo "=== Service Log (last 20 lines) ==="
tail -20 /tmp/agent.log

echo ""
echo "Setup complete! Visit: http://localhost/Professor_Hawkeinstein/test_db_setup.php"
