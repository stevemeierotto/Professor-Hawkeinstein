<?php
/**
 * Advisor Instance Concurrency Test
 * Tests that exactly one advisor instance is created per student
 * even when multiple concurrent requests are made
 */

require_once __DIR__ . '/../config/database.php';

class AdvisorConcurrencyTest {
    private $db;
    private $testStudentId;
    private $results = [];
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Setup: Create a test student
     */
    public function setup() {
        echo "Setting up test environment...\n";
        
        // Create test student
        $stmt = $this->db->prepare("
            INSERT INTO users (username, full_name, email, password_hash, role, created_at, is_active)
            VALUES (?, ?, ?, ?, 'student', NOW(), 1)
        ");
        
        $testUsername = 'test_concurrent_' . time();
        $testFullName = 'Test Concurrent User';
        $testEmail = $testUsername . '@test.local';
        $testPassword = password_hash('test123', PASSWORD_ARGON2ID);
        
        $stmt->execute([$testUsername, $testFullName, $testEmail, $testPassword]);
        $this->testStudentId = $this->db->lastInsertId();
        
        echo "Created test student ID: {$this->testStudentId}\n";
        
        // Verify Professor Hawkeinstein exists (agent_id = 1)
        $agentCheck = $this->db->query("SELECT agent_id, agent_name FROM agents WHERE agent_id = 1");
        $agent = $agentCheck->fetch();
        
        if (!$agent) {
            throw new Exception("Professor Hawkeinstein (agent_id=1) not found in database");
        }
        
        echo "Found advisor template: {$agent['agent_name']}\n";
    }
    
    /**
     * Test: Simulate concurrent advisor creation
     */
    public function testConcurrentCreation() {
        echo "\n=== Testing Concurrent Advisor Creation ===\n";
        
        $numRequests = 10; // Simulate 10 concurrent requests
        $processes = [];
        
        echo "Simulating {$numRequests} concurrent requests...\n";
        
        // Fork multiple processes to simulate concurrent requests
        for ($i = 0; $i < $numRequests; $i++) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                die("Could not fork process\n");
            } elseif ($pid == 0) {
                // Child process - attempt to create advisor
                $this->attemptCreateAdvisor($i);
                exit(0);
            } else {
                // Parent process - track child PIDs
                $processes[] = $pid;
            }
        }
        
        // Wait for all child processes to complete
        foreach ($processes as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        echo "All concurrent requests completed.\n";
    }
    
    /**
     * Attempt to create advisor (runs in child process)
     */
    private function attemptCreateAdvisor($requestId) {
        // Reconnect to DB in child process
        $db = getDB();
        
        try {
            // BEGIN TRANSACTION
            $db->beginTransaction();
            
            // Check if student already has an advisor (with row lock)
            $stmt = $db->prepare("
                SELECT advisor_instance_id 
                FROM student_advisors 
                WHERE student_id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$this->testStudentId]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                // Use INSERT IGNORE to handle duplicate key constraint
                $createStmt = $db->prepare("
                    INSERT IGNORE INTO student_advisors (
                        student_id,
                        advisor_type_id,
                        created_at,
                        is_active,
                        conversation_history,
                        testing_results
                    ) VALUES (?, 1, NOW(), 1, '[]', '[]')
                ");
                
                $createStmt->execute([$this->testStudentId]);
                
                if ($createStmt->rowCount() > 0) {
                    $instanceId = $db->lastInsertId();
                    $db->commit();
                    
                    // Log successful creation
                    file_put_contents('/tmp/advisor_test_results.log', 
                        "[Request $requestId] Created advisor instance: $instanceId\n", 
                        FILE_APPEND);
                } else {
                    $db->commit();
                    
                    // Log duplicate detection
                    file_put_contents('/tmp/advisor_test_results.log', 
                        "[Request $requestId] Duplicate detected, INSERT ignored\n", 
                        FILE_APPEND);
                }
            } else {
                $db->commit();
                
                // Log existing advisor found
                file_put_contents('/tmp/advisor_test_results.log', 
                    "[Request $requestId] Advisor already exists: {$existing['advisor_instance_id']}\n", 
                    FILE_APPEND);
            }
        } catch (Exception $e) {
            $db->rollBack();
            
            // Log error
            file_put_contents('/tmp/advisor_test_results.log', 
                "[Request $requestId] ERROR: " . $e->getMessage() . "\n", 
                FILE_APPEND);
        }
    }
    
    /**
     * Verify: Check that exactly one advisor was created
     */
    public function verify() {
        echo "\n=== Verifying Results ===\n";
        
        // Count advisors for test student
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(advisor_instance_id) as instance_ids
            FROM student_advisors 
            WHERE student_id = ?
        ");
        $stmt->execute([$this->testStudentId]);
        $result = $stmt->fetch();
        
        $count = $result['count'];
        $instanceIds = $result['instance_ids'];
        
        echo "Advisors created for student {$this->testStudentId}: $count\n";
        echo "Instance IDs: $instanceIds\n";
        
        // Read log file
        if (file_exists('/tmp/advisor_test_results.log')) {
            echo "\nConcurrent request logs:\n";
            echo file_get_contents('/tmp/advisor_test_results.log');
        }
        
        // Assert exactly one advisor
        if ($count == 1) {
            echo "\n✅ PASS: Exactly one advisor instance created\n";
            return true;
        } else {
            echo "\n❌ FAIL: Expected 1 advisor, found $count\n";
            return false;
        }
    }
    
    /**
     * Cleanup: Remove test data
     */
    public function cleanup() {
        echo "\n=== Cleaning Up ===\n";
        
        // Delete test advisors
        $stmt = $this->db->prepare("DELETE FROM student_advisors WHERE student_id = ?");
        $stmt->execute([$this->testStudentId]);
        
        // Delete test student
        $stmt = $this->db->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$this->testStudentId]);
        
        // Remove log file
        if (file_exists('/tmp/advisor_test_results.log')) {
            unlink('/tmp/advisor_test_results.log');
        }
        
        echo "Test data cleaned up.\n";
    }
    
    /**
     * Run full test suite
     */
    public function run() {
        try {
            $this->setup();
            $this->testConcurrentCreation();
            $passed = $this->verify();
            $this->cleanup();
            
            return $passed;
        } catch (Exception $e) {
            echo "\n❌ TEST ERROR: " . $e->getMessage() . "\n";
            $this->cleanup();
            return false;
        }
    }
}

// Check if pcntl extension is available
if (!function_exists('pcntl_fork')) {
    echo "❌ pcntl extension not available. Install with: pecl install pcntl\n";
    echo "Alternative: Run sequential simulation test instead.\n";
    exit(1);
}

// Run test
echo "==============================================\n";
echo "  Advisor Instance Concurrency Test\n";
echo "==============================================\n\n";

$test = new AdvisorConcurrencyTest();
$passed = $test->run();

echo "\n==============================================\n";
echo $passed ? "✅ ALL TESTS PASSED\n" : "❌ TESTS FAILED\n";
echo "==============================================\n";

exit($passed ? 0 : 1);
