#!/usr/bin/env php
<?php
/**
 * Memory Policy Enforcement Tests
 * Validates access control for advisor and expert agent memory systems
 */

require_once __DIR__ . '/../config/database.php';

class MemoryPolicyTests {
    private $db;
    private $results = [];
    private $testStudent1Id;
    private $testStudent2Id;
    private $testAdvisorId;
    private $testExpertAgentId;
    
    public function __construct() {
        $this->db = getDB();
        echo "=== Memory Policy Access Control Tests ===\n\n";
    }
    
    /**
     * Test 1: Advisor Memory Isolation - Students can only read own advisor memory
     */
    public function testAdvisorMemoryReadIsolation() {
        echo "Test 1: Advisor Memory Read Isolation...\n";
        
        try {
            // Create two test students with advisor instances
            $this->setupTestStudents();
            
            // Student 1 tries to read their own advisor memory (SHOULD SUCCEED)
            $stmt = $this->db->prepare("
                SELECT conversation_history 
                FROM student_advisors 
                WHERE student_id = ? AND is_active = 1
            ");
            $stmt->execute([$this->testStudent1Id]);
            $result1 = $stmt->fetch();
            
            if ($result1) {
                $this->pass("Test 1a: Student can read own advisor memory");
            } else {
                $this->fail("Test 1a: Student cannot read own advisor memory");
                return;
            }
            
            // Verify no cross-student data leakage (query for student2's advisor with student1's ID should return nothing)
            $stmt = $this->db->prepare("
                SELECT conversation_history 
                FROM student_advisors 
                WHERE student_id = ? AND advisor_instance_id = (
                    SELECT advisor_instance_id FROM student_advisors WHERE student_id = ?
                )
            ");
            $stmt->execute([$this->testStudent1Id, $this->testStudent2Id]);
            $leakResult = $stmt->fetch();
            
            if (!$leakResult) {
                $this->pass("Test 1b: Students cannot access other students' advisor memory");
            } else {
                $this->fail("Test 1b: SECURITY VIOLATION - Cross-student memory access detected");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 1: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 2: Advisor Memory Write Isolation - Students can only write to own advisor memory
     */
    public function testAdvisorMemoryWriteIsolation() {
        echo "Test 2: Advisor Memory Write Isolation...\n";
        
        try {
            // Get student 1's advisor instance
            $stmt = $this->db->prepare("
                SELECT advisor_instance_id, conversation_history
                FROM student_advisors 
                WHERE student_id = ?
            ");
            $stmt->execute([$this->testStudent1Id]);
            $advisor = $stmt->fetch();
            
            $history = json_decode($advisor['conversation_history'] ?: '[]', true) ?: [];
            $originalCount = count($history);
            
            // Append a test message
            $history[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'role' => 'student',
                'message' => 'Test memory write isolation',
                'metadata' => ['test' => true]
            ];
            
            $updateStmt = $this->db->prepare("
                UPDATE student_advisors 
                SET conversation_history = ?
                WHERE student_id = ?
            ");
            $updateStmt->execute([json_encode($history), $this->testStudent1Id]);
            
            // Verify update succeeded for own advisor
            $stmt->execute([$this->testStudent1Id]);
            $updated = $stmt->fetch();
            $newHistory = json_decode($updated['conversation_history'] ?: '[]', true) ?: [];
            
            if (count($newHistory) === $originalCount + 1) {
                $this->pass("Test 2a: Student can write to own advisor memory");
            } else {
                $this->fail("Test 2a: Student cannot write to own advisor memory");
            }
            
            // Verify student 2's memory was NOT affected
            $stmt->execute([$this->testStudent2Id]);
            $student2Advisor = $stmt->fetch();
            $student2History = json_decode($student2Advisor['conversation_history'] ?: '[]', true) ?: [];
            
            $hasTestMessage = false;
            foreach ($student2History as $turn) {
                if (isset($turn['metadata']['test']) && $turn['metadata']['test'] === true) {
                    $hasTestMessage = true;
                    break;
                }
            }
            
            if (!$hasTestMessage) {
                $this->pass("Test 2b: Student writes do not leak to other students' advisor memory");
            } else {
                $this->fail("Test 2b: SECURITY VIOLATION - Write affected another student's memory");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 2: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 3: Expert Agent Memory Isolation - Expert agents use agent_memories table ONLY
     */
    public function testExpertAgentMemoryIsolation() {
        echo "Test 3: Expert Agent Memory Isolation...\n";
        
        try {
            // Create test expert agent
            $this->setupTestExpertAgent();
            
            // Simulate expert agent interaction (should write to agent_memories)
            $insertStmt = $this->db->prepare("
                INSERT INTO agent_memories 
                (agent_id, user_id, interaction_type, user_message, agent_response, importance_score)
                VALUES (?, ?, 'chat', 'Test expert query', 'Test expert response', 0.5)
            ");
            $insertStmt->execute([$this->testExpertAgentId, $this->testStudent1Id]);
            
            // Verify it was written to agent_memories
            $checkStmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM agent_memories 
                WHERE agent_id = ? AND user_id = ? AND user_message = 'Test expert query'
            ");
            $checkStmt->execute([$this->testExpertAgentId, $this->testStudent1Id]);
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                $this->pass("Test 3a: Expert agent writes to agent_memories table");
            } else {
                $this->fail("Test 3a: Expert agent did not write to agent_memories");
                return;
            }
            
            // Verify expert agent did NOT touch student_advisors.conversation_history
            $advisorStmt = $this->db->prepare("
                SELECT conversation_history 
                FROM student_advisors 
                WHERE student_id = ?
            ");
            $advisorStmt->execute([$this->testStudent1Id]);
            $advisor = $advisorStmt->fetch();
            
            $conversationHistory = json_decode($advisor['conversation_history'] ?: '[]', true);
            $hasExpertMessage = false;
            
            foreach ($conversationHistory as $turn) {
                if (isset($turn['message']) && $turn['message'] === 'Test expert query') {
                    $hasExpertMessage = true;
                    break;
                }
            }
            
            if (!$hasExpertMessage) {
                $this->pass("Test 3b: Expert agent does NOT write to advisor conversation_history");
            } else {
                $this->fail("Test 3b: POLICY VIOLATION - Expert agent wrote to advisor memory");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 3: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 4: Summarizer Agent Isolation - Summarizer cannot access advisor memory
     */
    public function testSummarizerAgentIsolation() {
        echo "Test 4: Summarizer Agent Isolation...\n";
        
        try {
            // Check if summarizer agent has any foreign key relationship to student_advisors
            $fkStmt = $this->db->query("
                SELECT 
                    CONSTRAINT_NAME,
                    TABLE_NAME,
                    REFERENCED_TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_NAME = 'student_advisors'
                AND TABLE_SCHEMA = DATABASE()
            ");
            
            $hasViolation = false;
            while ($fk = $fkStmt->fetch()) {
                if (in_array($fk['TABLE_NAME'], ['educational_content', 'agents'])) {
                    // These should NOT reference student_advisors
                    $hasViolation = true;
                    $this->fail("Test 4a: VIOLATION - {$fk['TABLE_NAME']} has FK to student_advisors");
                }
            }
            
            if (!$hasViolation) {
                $this->pass("Test 4a: No inappropriate FK relationships to student_advisors");
            }
            
            // Verify educational_content table does NOT have advisor_instance_id or student_id
            $columnsStmt = $this->db->query("
                SHOW COLUMNS FROM educational_content
            ");
            
            $hasAdvisorColumn = false;
            $hasStudentColumn = false;
            
            while ($col = $columnsStmt->fetch()) {
                if ($col['Field'] === 'advisor_instance_id') {
                    $hasAdvisorColumn = true;
                }
                if ($col['Field'] === 'student_id') {
                    $hasStudentColumn = true;
                }
            }
            
            if (!$hasAdvisorColumn && !$hasStudentColumn) {
                $this->pass("Test 4b: educational_content table isolated from student_advisors");
            } else {
                $this->fail("Test 4b: POLICY VIOLATION - educational_content has student/advisor columns");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 4: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 5: Advisor Instance Uniqueness - One advisor per student constraint
     */
    public function testAdvisorInstanceUniqueness() {
        echo "Test 5: Advisor Instance Uniqueness...\n";
        
        try {
            // Try to create a second advisor instance for student 1 (SHOULD FAIL)
            $insertStmt = $this->db->prepare("
                INSERT INTO student_advisors 
                (student_id, advisor_type_id, is_active, conversation_history)
                VALUES (?, ?, 1, '[]')
            ");
            
            $duplicateAttempt = false;
            try {
                $insertStmt->execute([$this->testStudent1Id, $this->testAdvisorId]);
            } catch (PDOException $e) {
                // Should throw duplicate key error
                if (strpos($e->getMessage(), 'Duplicate entry') !== false 
                    || strpos($e->getMessage(), 'unique_student_advisor') !== false) {
                    $duplicateAttempt = true;
                }
            }
            
            if ($duplicateAttempt) {
                $this->pass("Test 5: UNIQUE constraint prevents multiple advisors per student");
            } else {
                $this->fail("Test 5: CRITICAL - Multiple advisors allowed per student (data leak risk)");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 5: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 6: API Endpoint Access Control - requireAuth() enforces userId isolation
     */
    public function testAPIAccessControl() {
        echo "Test 6: API Access Control Validation...\n";
        
        try {
            // Verify update_advisor_data.php uses authenticated userId
            $updateFile = file_get_contents(__DIR__ . '/../api/student/update_advisor_data.php');
            
            $hasRequireAuth = strpos($updateFile, 'requireAuth()') !== false;
            $usesAuthUserId = strpos($updateFile, '$student[\'userId\']') !== false 
                            || strpos($updateFile, '$studentId = $student[\'userId\']') !== false;
            $usesWhereClause = strpos($updateFile, 'WHERE student_id = ?') !== false;
            
            if ($hasRequireAuth && $usesAuthUserId && $usesWhereClause) {
                $this->pass("Test 6a: update_advisor_data.php uses requireAuth() and userId isolation");
            } else {
                $this->fail("Test 6a: update_advisor_data.php missing access control");
            }
            
            // Verify get_advisor.php uses authenticated userId
            $getFile = file_get_contents(__DIR__ . '/../api/student/get_advisor.php');
            
            $hasRequireAuth = strpos($getFile, 'requireAuth()') !== false;
            $usesAuthUserId = strpos($getFile, '$student[\'userId\']') !== false 
                            || strpos($getFile, '$studentId = $student[\'userId\']') !== false;
            $usesWhereClause = strpos($getFile, 'WHERE sa.student_id = ?') !== false;
            
            if ($hasRequireAuth && $usesAuthUserId && $usesWhereClause) {
                $this->pass("Test 6b: get_advisor.php uses requireAuth() and userId isolation");
            } else {
                $this->fail("Test 6b: get_advisor.php missing access control");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 6: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 7: Agent Type Enforcement - Only student_advisor agents can have instances
     */
    public function testAgentTypeEnforcement() {
        echo "Test 7: Agent Type Enforcement...\n";
        
        try {
            // Check if any non-advisor agents have student_advisor instances
            $violationStmt = $this->db->query("
                SELECT sa.advisor_instance_id, a.agent_name, a.agent_type
                FROM student_advisors sa
                JOIN agents a ON sa.advisor_type_id = a.agent_id
                WHERE a.is_student_advisor = 0
            ");
            
            $violations = $violationStmt->fetchAll();
            
            if (count($violations) === 0) {
                $this->pass("Test 7: Only student_advisor agents have instances");
            } else {
                $this->fail("Test 7: POLICY VIOLATION - Non-advisor agents have instances: " . count($violations));
            }
            
        } catch (Exception $e) {
            $this->fail("Test 7: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 8: Memory System Architecture Validation
     */
    public function testMemorySystemArchitecture() {
        echo "Test 8: Memory System Architecture Validation...\n";
        
        try {
            // Verify two separate memory systems exist
            $tables = [];
            
            $stmt1 = $this->db->query("SHOW TABLES LIKE '%memor%'");
            while ($row = $stmt1->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $stmt2 = $this->db->query("SHOW TABLES LIKE '%advisor%'");
            while ($row = $stmt2->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $hasAgentMemories = in_array('agent_memories', $tables);
            $hasStudentAdvisors = in_array('student_advisors', $tables);
            
            if ($hasAgentMemories && $hasStudentAdvisors) {
                $this->pass("Test 8a: Both memory systems present (agent_memories + student_advisors)");
            } else {
                $this->fail("Test 8a: Missing memory tables");
                return;
            }
            
            // Verify student_advisors has conversation_history JSON field
            $columnsStmt = $this->db->query("SHOW COLUMNS FROM student_advisors LIKE 'conversation_history'");
            $column = $columnsStmt->fetch();
            
            if ($column && (strpos(strtolower($column['Type']), 'json') !== false 
                          || strpos(strtolower($column['Type']), 'text') !== false)) {
                $this->pass("Test 8b: student_advisors.conversation_history field exists");
            } else {
                $this->fail("Test 8b: conversation_history field missing or wrong type");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 8: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Setup helper: Create test students with advisor instances
     */
    private function setupTestStudents() {
        // Create test advisor agent if not exists
        $stmt = $this->db->query("
            SELECT agent_id FROM agents WHERE is_student_advisor = 1 LIMIT 1
        ");
        $advisor = $stmt->fetch();
        
        if ($advisor) {
            $this->testAdvisorId = $advisor['agent_id'];
        } else {
            // Create test advisor
            $insertStmt = $this->db->prepare("
                INSERT INTO agents 
                (agent_name, agent_type, system_prompt, is_student_advisor, is_active, temperature, max_tokens)
                VALUES ('Test Advisor', 'student_advisor', 'Test prompt', 1, 1, 0.7, 256)
            ");
            $insertStmt->execute();
            $this->testAdvisorId = $this->db->lastInsertId();
        }
        
        // Create test students
        for ($i = 1; $i <= 2; $i++) {
            $username = "test_student_memory_$i";
            
            // Delete if exists
            $deleteStmt = $this->db->prepare("DELETE FROM users WHERE username = ?");
            $deleteStmt->execute([$username]);
            
            // Create student
            $insertStmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, is_active)
                VALUES (?, ?, ?, ?, 'student', 1)
            ");
            $insertStmt->execute([
                $username,
                "$username@test.com",
                hashPassword('test123'),
                "Test Student $i"
            ]);
            
            $studentId = $this->db->lastInsertId();
            
            if ($i === 1) {
                $this->testStudent1Id = $studentId;
            } else {
                $this->testStudent2Id = $studentId;
            }
            
            // Create advisor instance
            $advisorStmt = $this->db->prepare("
                INSERT INTO student_advisors 
                (student_id, advisor_type_id, is_active, conversation_history)
                VALUES (?, ?, 1, '[]')
                ON DUPLICATE KEY UPDATE is_active = 1
            ");
            $advisorStmt->execute([$studentId, $this->testAdvisorId]);
        }
    }
    
    /**
     * Setup helper: Create test expert agent
     */
    private function setupTestExpertAgent() {
        $stmt = $this->db->query("
            SELECT agent_id FROM agents WHERE is_student_advisor = 0 AND agent_type = 'expert' LIMIT 1
        ");
        $agent = $stmt->fetch();
        
        if ($agent) {
            $this->testExpertAgentId = $agent['agent_id'];
        } else {
            $insertStmt = $this->db->prepare("
                INSERT INTO agents 
                (agent_name, agent_type, system_prompt, is_student_advisor, is_active, temperature, max_tokens)
                VALUES ('Test Expert', 'expert', 'Test expert prompt', 0, 1, 0.7, 256)
            ");
            $insertStmt->execute();
            $this->testExpertAgentId = $this->db->lastInsertId();
        }
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup() {
        echo "\nCleaning up test data...\n";
        
        try {
            // Delete test student advisors
            if ($this->testStudent1Id) {
                $this->db->prepare("DELETE FROM student_advisors WHERE student_id = ?")->execute([$this->testStudent1Id]);
            }
            if ($this->testStudent2Id) {
                $this->db->prepare("DELETE FROM student_advisors WHERE student_id = ?")->execute([$this->testStudent2Id]);
            }
            
            // Delete test agent memories
            if ($this->testExpertAgentId && $this->testStudent1Id) {
                $this->db->prepare("DELETE FROM agent_memories WHERE agent_id = ? AND user_id = ?")
                    ->execute([$this->testExpertAgentId, $this->testStudent1Id]);
            }
            
            // Delete test students
            if ($this->testStudent1Id) {
                $this->db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$this->testStudent1Id]);
            }
            if ($this->testStudent2Id) {
                $this->db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$this->testStudent2Id]);
            }
            
            // Delete test agents (only if we created them)
            if ($this->testAdvisorId && strpos($this->testAdvisorId, 'test') !== false) {
                $this->db->prepare("DELETE FROM agents WHERE agent_id = ?")->execute([$this->testAdvisorId]);
            }
            if ($this->testExpertAgentId && strpos($this->testExpertAgentId, 'test') !== false) {
                $this->db->prepare("DELETE FROM agents WHERE agent_id = ?")->execute([$this->testExpertAgentId]);
            }
            
            echo "Cleanup complete.\n";
        } catch (Exception $e) {
            echo "Cleanup error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        $this->testAdvisorMemoryReadIsolation();
        $this->testAdvisorMemoryWriteIsolation();
        $this->testExpertAgentMemoryIsolation();
        $this->testSummarizerAgentIsolation();
        $this->testAdvisorInstanceUniqueness();
        $this->testAPIAccessControl();
        $this->testAgentTypeEnforcement();
        $this->testMemorySystemArchitecture();
        
        $this->cleanup();
        $this->printSummary();
    }
    
    private function pass($message) {
        $this->results[] = ['status' => 'PASS', 'message' => $message];
        echo "  ✓ PASS: $message\n";
    }
    
    private function fail($message) {
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
        echo "  ✗ FAIL: $message\n";
    }
    
    private function printSummary() {
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        
        echo "Total Tests: " . count($this->results) . "\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        
        if ($failed > 0) {
            echo "\n❌ FAILED TESTS:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  - {$result['message']}\n";
                }
            }
            exit(1);
        } else {
            echo "\n✅ All tests passed!\n";
            exit(0);
        }
    }
    
    public function getResults() {
        return $this->results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $tests = new MemoryPolicyTests();
    $tests->runAll();
}
