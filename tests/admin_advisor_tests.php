#!/usr/bin/env php
<?php
/**
 * Admin Advisor Instance Tests
 * Validates admin advisor creation, chat, memory isolation from students
 */

require_once __DIR__ . '/../config/database.php';

class AdminAdvisorTests {
    private $db;
    private $results = [];
    private $testAdminId;
    private $testStudentId;
    private $testAdvisorAgentId;
    private $adminInstanceId;
    private $studentInstanceId;
    
    public function __construct() {
        $this->db = getDB();
        echo "=== Admin Advisor Instance Tests ===\n\n";
    }
    
    /**
     * Test 1: Admin can create their own advisor instance
     */
    public function testAdminCanCreateAdvisor() {
        echo "Test 1: Admin Advisor Creation...\n";
        
        try {
            $this->setupTestUsers();
            
            // Create admin advisor instance
            $insertStmt = $this->db->prepare("
                INSERT INTO agent_instances 
                (agent_id, owner_id, owner_type, conversation_history, is_active, last_interaction)
                VALUES (?, ?, 'admin', '[]', 1, NOW())
            ");
            
            $insertStmt->execute([$this->testAdvisorAgentId, $this->testAdminId]);
            $this->adminInstanceId = $this->db->lastInsertId();
            
            if ($this->adminInstanceId > 0) {
                $this->pass("Test 1a: Admin advisor instance created successfully (ID: {$this->adminInstanceId})");
            } else {
                $this->fail("Test 1a: Failed to create admin advisor instance");
                return;
            }
            
            // Verify instance exists
            $checkStmt = $this->db->prepare("
                SELECT instance_id, owner_id, owner_type 
                FROM agent_instances 
                WHERE instance_id = ?
            ");
            $checkStmt->execute([$this->adminInstanceId]);
            $instance = $checkStmt->fetch();
            
            if ($instance && $instance['owner_type'] === 'admin' && $instance['owner_id'] == $this->testAdminId) {
                $this->pass("Test 1b: Admin advisor instance verified in database");
            } else {
                $this->fail("Test 1b: Admin advisor instance not found or incorrect data");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 1: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 2: Admin cannot create multiple advisor instances (UNIQUE constraint)
     */
    public function testAdminUniqueConstraint() {
        echo "Test 2: Admin Unique Advisor Constraint...\n";
        
        try {
            $insertStmt = $this->db->prepare("
                INSERT INTO agent_instances 
                (agent_id, owner_id, owner_type, conversation_history, is_active)
                VALUES (?, ?, 'admin', '[]', 1)
            ");
            
            $duplicateAttempt = false;
            try {
                $insertStmt->execute([$this->testAdvisorAgentId, $this->testAdminId]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false 
                    || strpos($e->getMessage(), 'unique_owner_instance') !== false) {
                    $duplicateAttempt = true;
                }
            }
            
            if ($duplicateAttempt) {
                $this->pass("Test 2: UNIQUE constraint prevents multiple admin advisors");
            } else {
                $this->fail("Test 2: CRITICAL - Multiple admin advisors allowed (data leak risk)");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 2: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 3: Admin advisor memory is separate from student advisors
     */
    public function testMemoryIsolation() {
        echo "Test 3: Admin-Student Memory Isolation...\n";
        
        try {
            // Create student advisor instance
            $insertStmt = $this->db->prepare("
                INSERT INTO agent_instances 
                (agent_id, owner_id, owner_type, conversation_history, is_active, last_interaction)
                VALUES (?, ?, 'student', '[]', 1, NOW())
            ");
            
            $insertStmt->execute([$this->testAdvisorAgentId, $this->testStudentId]);
            $this->studentInstanceId = $this->db->lastInsertId();
            
            // Add conversation to admin instance
            $adminConversation = [
                ['role' => 'admin', 'message' => 'Admin secret message', 'timestamp' => date('Y-m-d H:i:s')]
            ];
            
            $updateStmt = $this->db->prepare("
                UPDATE agent_instances 
                SET conversation_history = ?
                WHERE instance_id = ?
            ");
            $updateStmt->execute([json_encode($adminConversation), $this->adminInstanceId]);
            
            // Add conversation to student instance
            $studentConversation = [
                ['role' => 'student', 'message' => 'Student secret message', 'timestamp' => date('Y-m-d H:i:s')]
            ];
            
            $updateStmt->execute([json_encode($studentConversation), $this->studentInstanceId]);
            
            // Verify admin conversation doesn't leak to student
            $checkStmt = $this->db->prepare("
                SELECT conversation_history 
                FROM agent_instances 
                WHERE instance_id = ?
            ");
            
            $checkStmt->execute([$this->studentInstanceId]);
            $studentData = $checkStmt->fetch();
            $studentHistory = json_decode($studentData['conversation_history'], true);
            
            $hasAdminMessage = false;
            foreach ($studentHistory as $msg) {
                if (isset($msg['message']) && strpos($msg['message'], 'Admin secret') !== false) {
                    $hasAdminMessage = true;
                    break;
                }
            }
            
            if (!$hasAdminMessage) {
                $this->pass("Test 3a: Admin messages do NOT leak to student instances");
            } else {
                $this->fail("Test 3a: SECURITY VIOLATION - Admin messages leaked to student");
            }
            
            // Verify student conversation doesn't leak to admin
            $checkStmt->execute([$this->adminInstanceId]);
            $adminData = $checkStmt->fetch();
            $adminHistory = json_decode($adminData['conversation_history'], true);
            
            $hasStudentMessage = false;
            foreach ($adminHistory as $msg) {
                if (isset($msg['message']) && strpos($msg['message'], 'Student secret') !== false) {
                    $hasStudentMessage = true;
                    break;
                }
            }
            
            if (!$hasStudentMessage) {
                $this->pass("Test 3b: Student messages do NOT leak to admin instances");
            } else {
                $this->fail("Test 3b: SECURITY VIOLATION - Student messages leaked to admin");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 3: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 4: Admin advisor conversation persistence
     */
    public function testConversationPersistence() {
        echo "Test 4: Conversation Persistence...\n";
        
        try {
            // Get existing conversation
            $selectStmt = $this->db->prepare("
                SELECT conversation_history 
                FROM agent_instances 
                WHERE instance_id = ?
            ");
            $selectStmt->execute([$this->adminInstanceId]);
            $result = $selectStmt->fetch();
            
            $conversation = json_decode($result['conversation_history'], true) ?: [];
            $originalCount = count($conversation);
            
            // Append new message
            $conversation[] = [
                'role' => 'admin',
                'message' => 'Test persistence message',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $updateStmt = $this->db->prepare("
                UPDATE agent_instances 
                SET conversation_history = ?
                WHERE instance_id = ?
            ");
            $updateStmt->execute([json_encode($conversation), $this->adminInstanceId]);
            
            // Retrieve and verify
            $selectStmt->execute([$this->adminInstanceId]);
            $updated = $selectStmt->fetch();
            $newConversation = json_decode($updated['conversation_history'], true);
            
            if (count($newConversation) === $originalCount + 1) {
                $this->pass("Test 4a: Conversation updates persist correctly");
            } else {
                $this->fail("Test 4a: Conversation persistence failed");
            }
            
            // Verify specific message
            $lastMessage = end($newConversation);
            if ($lastMessage['message'] === 'Test persistence message') {
                $this->pass("Test 4b: Message content persisted accurately");
            } else {
                $this->fail("Test 4b: Message content corrupted");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 4: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 5: Owner type enforcement
     */
    public function testOwnerTypeEnforcement() {
        echo "Test 5: Owner Type Enforcement...\n";
        
        try {
            // Verify admin instance has owner_type='admin'
            $checkStmt = $this->db->prepare("
                SELECT owner_type, owner_id
                FROM agent_instances 
                WHERE instance_id = ?
            ");
            
            $checkStmt->execute([$this->adminInstanceId]);
            $adminInstance = $checkStmt->fetch();
            
            if ($adminInstance['owner_type'] === 'admin') {
                $this->pass("Test 5a: Admin instance has correct owner_type='admin'");
            } else {
                $this->fail("Test 5a: Admin instance has wrong owner_type: " . $adminInstance['owner_type']);
            }
            
            // Verify student instance has owner_type='student'
            $checkStmt->execute([$this->studentInstanceId]);
            $studentInstance = $checkStmt->fetch();
            
            if ($studentInstance['owner_type'] === 'student') {
                $this->pass("Test 5b: Student instance has correct owner_type='student'");
            } else {
                $this->fail("Test 5b: Student instance has wrong owner_type: " . $studentInstance['owner_type']);
            }
            
        } catch (Exception $e) {
            $this->fail("Test 5: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 6: Query filtering by owner_type
     */
    public function testQueryFiltering() {
        echo "Test 6: Query Filtering by Owner Type...\n";
        
        try {
            // Query admin instances only
            $adminStmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM agent_instances 
                WHERE owner_id = ? AND owner_type = 'admin'
            ");
            $adminStmt->execute([$this->testAdminId]);
            $adminCount = $adminStmt->fetch()['count'];
            
            if ($adminCount === 1) {
                $this->pass("Test 6a: Admin query returns exactly 1 admin instance");
            } else {
                $this->fail("Test 6a: Admin query returned $adminCount instances (expected 1)");
            }
            
            // Query student instances only
            $studentStmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM agent_instances 
                WHERE owner_id = ? AND owner_type = 'student'
            ");
            $studentStmt->execute([$this->testStudentId]);
            $studentCount = $studentStmt->fetch()['count'];
            
            if ($studentCount === 1) {
                $this->pass("Test 6b: Student query returns exactly 1 student instance");
            } else {
                $this->fail("Test 6b: Student query returned $studentCount instances (expected 1)");
            }
            
            // Verify cross-contamination doesn't occur
            $adminStmt->execute([$this->testStudentId]);  // Query student_id for admin instances
            $wrongCount = $adminStmt->fetch()['count'];
            
            if ($wrongCount === 0) {
                $this->pass("Test 6c: No cross-contamination in owner_type queries");
            } else {
                $this->fail("Test 6c: Query returned wrong owner_type instances");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 6: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Test 7: API endpoint accessibility
     */
    public function testAPIEndpoints() {
        echo "Test 7: API Endpoint Validation...\n";
        
        try {
            // Check if API files exist
            $apiFiles = [
                '/var/www/html/Professor_Hawkeinstein/api/admin/create_agent_instance.php',
                '/var/www/html/Professor_Hawkeinstein/api/admin/chat_instance.php',
                '/var/www/html/Professor_Hawkeinstein/api/admin/get_agent_instance.php'
            ];
            
            $allExist = true;
            foreach ($apiFiles as $file) {
                if (!file_exists($file)) {
                    $this->fail("Test 7: API file missing: " . basename($file));
                    $allExist = false;
                }
            }
            
            if ($allExist) {
                $this->pass("Test 7: All admin advisor API endpoints present");
            }
            
        } catch (Exception $e) {
            $this->fail("Test 7: Exception - " . $e->getMessage());
        }
    }
    
    /**
     * Setup helper: Create test users
     */
    private function setupTestUsers() {
        // Get or create advisor agent
        $stmt = $this->db->query("
            SELECT agent_id FROM agents 
            WHERE (is_advisor = 1 OR is_student_advisor = 1) AND is_active = 1 
            LIMIT 1
        ");
        $agent = $stmt->fetch();
        
        if ($agent) {
            $this->testAdvisorAgentId = $agent['agent_id'];
        } else {
            // Create test advisor agent
            $insertStmt = $this->db->prepare("
                INSERT INTO agents 
                (agent_name, agent_type, system_prompt, is_advisor, is_student_advisor, is_active, temperature, max_tokens)
                VALUES ('Test Advisor', 'advisor', 'Test prompt', 1, 1, 1, 0.7, 256)
            ");
            $insertStmt->execute();
            $this->testAdvisorAgentId = $this->db->lastInsertId();
        }
        
        // Create test admin
        $adminUsername = "test_admin_advisor_" . time();
        $deleteStmt = $this->db->prepare("DELETE FROM users WHERE username = ?");
        $deleteStmt->execute([$adminUsername]);
        
        $insertStmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, is_active)
            VALUES (?, ?, ?, 'Test Admin', 'admin', 1)
        ");
        $insertStmt->execute([
            $adminUsername,
            "$adminUsername@test.com",
            hashPassword('test123')
        ]);
        $this->testAdminId = $this->db->lastInsertId();
        
        // Create test student
        $studentUsername = "test_student_advisor_" . time();
        $deleteStmt->execute([$studentUsername]);
        
        $insertStmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, is_active)
            VALUES (?, ?, ?, 'Test Student', 'student', 1)
        ");
        $insertStmt->execute([
            $studentUsername,
            "$studentUsername@test.com",
            hashPassword('test123')
        ]);
        $this->testStudentId = $this->db->lastInsertId();
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup() {
        echo "\nCleaning up test data...\n";
        
        try {
            // Delete test instances
            if ($this->adminInstanceId) {
                $this->db->prepare("DELETE FROM agent_instances WHERE instance_id = ?")->execute([$this->adminInstanceId]);
            }
            if ($this->studentInstanceId) {
                $this->db->prepare("DELETE FROM agent_instances WHERE instance_id = ?")->execute([$this->studentInstanceId]);
            }
            
            // Delete test users
            if ($this->testAdminId) {
                $this->db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$this->testAdminId]);
            }
            if ($this->testStudentId) {
                $this->db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$this->testStudentId]);
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
        $this->testAdminCanCreateAdvisor();
        $this->testAdminUniqueConstraint();
        $this->testMemoryIsolation();
        $this->testConversationPersistence();
        $this->testOwnerTypeEnforcement();
        $this->testQueryFiltering();
        $this->testAPIEndpoints();
        
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
    $tests = new AdminAdvisorTests();
    $tests->runAll();
}
