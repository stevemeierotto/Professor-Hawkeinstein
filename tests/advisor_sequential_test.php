<?php
/**
 * Advisor Instance Sequential Test
 * Tests advisor creation logic without requiring pcntl extension
 * Verifies INSERT IGNORE and UNIQUE constraint behavior
 */

require_once __DIR__ . '/../config/database.php';

class AdvisorSequentialTest {
    private $db;
    private $testStudentId;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function setup() {
        echo "Setting up test environment...\n";
        
        // Create test student
        $stmt = $this->db->prepare("
            INSERT INTO users (username, full_name, email, password_hash, role, created_at, is_active)
            VALUES (?, ?, ?, ?, 'student', NOW(), 1)
        ");
        
        $testUsername = 'test_advisor_' . time();
        $testFullName = 'Test Advisor User';
        $testEmail = $testUsername . '@test.local';
        $testPassword = password_hash('test123', PASSWORD_ARGON2ID);
        
        $stmt->execute([$testUsername, $testFullName, $testEmail, $testPassword]);
        $this->testStudentId = $this->db->lastInsertId();
        
        echo "Created test student ID: {$this->testStudentId}\n";
    }
    
    public function testUniqueConstraint() {
        echo "\n=== Test 1: UNIQUE Constraint Enforcement ===\n";
        
        // First insert should succeed
        $stmt1 = $this->db->prepare("
            INSERT IGNORE INTO student_advisors (
                student_id, advisor_type_id, created_at, is_active,
                conversation_history, testing_results
            ) VALUES (?, 1, NOW(), 1, '[]', '[]')
        ");
        
        $stmt1->execute([$this->testStudentId]);
        $rowCount1 = $stmt1->rowCount();
        $instanceId1 = $rowCount1 > 0 ? $this->db->lastInsertId() : null;
        
        echo "First INSERT: rowCount=$rowCount1, instanceId=$instanceId1\n";
        
        // Second insert should be ignored (duplicate student_id)
        $stmt2 = $this->db->prepare("
            INSERT IGNORE INTO student_advisors (
                student_id, advisor_type_id, created_at, is_active,
                conversation_history, testing_results
            ) VALUES (?, 1, NOW(), 1, '[]', '[]')
        ");
        
        $stmt2->execute([$this->testStudentId]);
        $rowCount2 = $stmt2->rowCount();
        
        echo "Second INSERT: rowCount=$rowCount2 (should be 0)\n";
        
        // Verify only one advisor exists
        $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM student_advisors WHERE student_id = ?");
        $countStmt->execute([$this->testStudentId]);
        $count = $countStmt->fetch()['count'];
        
        echo "Total advisors in DB: $count\n";
        
        if ($rowCount1 == 1 && $rowCount2 == 0 && $count == 1) {
            echo "✅ PASS: UNIQUE constraint working correctly\n";
            return true;
        } else {
            echo "❌ FAIL: UNIQUE constraint not enforced properly\n";
            return false;
        }
    }
    
    public function testTransactionRollback() {
        echo "\n=== Test 2: Transaction Rollback ===\n";
        
        // Delete existing advisor for this test
        $this->db->prepare("DELETE FROM student_advisors WHERE student_id = ?")->execute([$this->testStudentId]);
        
        try {
            $this->db->beginTransaction();
            
            // Create advisor
            $stmt = $this->db->prepare("
                INSERT INTO student_advisors (student_id, advisor_type_id, created_at, is_active)
                VALUES (?, 1, NOW(), 1)
            ");
            $stmt->execute([$this->testStudentId]);
            
            echo "Advisor created in transaction\n";
            
            // Intentionally rollback
            $this->db->rollBack();
            echo "Transaction rolled back\n";
            
            // Verify advisor was NOT created
            $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM student_advisors WHERE student_id = ?");
            $countStmt->execute([$this->testStudentId]);
            $count = $countStmt->fetch()['count'];
            
            echo "Advisors after rollback: $count\n";
            
            if ($count == 0) {
                echo "✅ PASS: Transaction rollback working correctly\n";
                return true;
            } else {
                echo "❌ FAIL: Advisor still exists after rollback\n";
                return false;
            }
        } catch (Exception $e) {
            echo "❌ FAIL: Exception during transaction test: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testForUpdateLock() {
        echo "\n=== Test 3: FOR UPDATE Row Locking ===\n";
        
        // Create advisor for test
        $this->db->prepare("
            INSERT IGNORE INTO student_advisors (student_id, advisor_type_id, created_at, is_active)
            VALUES (?, 1, NOW(), 1)
        ")->execute([$this->testStudentId]);
        
        $this->db->beginTransaction();
        
        // Lock the row
        $stmt = $this->db->prepare("
            SELECT advisor_instance_id 
            FROM student_advisors 
            WHERE student_id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$this->testStudentId]);
        $result = $stmt->fetch();
        
        echo "Row locked with FOR UPDATE: instance_id={$result['advisor_instance_id']}\n";
        
        // Verify we can read the data
        if ($result && isset($result['advisor_instance_id'])) {
            echo "✅ PASS: FOR UPDATE successfully locks row for reading\n";
            $this->db->commit();
            return true;
        } else {
            echo "❌ FAIL: FOR UPDATE did not return expected data\n";
            $this->db->rollBack();
            return false;
        }
    }
    
    public function testMultipleSequentialInserts() {
        echo "\n=== Test 4: Multiple Sequential Inserts ===\n";
        
        // Delete existing
        $this->db->prepare("DELETE FROM student_advisors WHERE student_id = ?")->execute([$this->testStudentId]);
        
        $successCount = 0;
        $attempts = 5;
        
        for ($i = 0; $i < $attempts; $i++) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO student_advisors (student_id, advisor_type_id, created_at, is_active)
                VALUES (?, 1, NOW(), 1)
            ");
            $stmt->execute([$this->testStudentId]);
            
            if ($stmt->rowCount() > 0) {
                $successCount++;
                echo "Attempt " . ($i+1) . ": Created advisor (rowCount=1)\n";
            } else {
                echo "Attempt " . ($i+1) . ": Duplicate ignored (rowCount=0)\n";
            }
        }
        
        // Final count check
        $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM student_advisors WHERE student_id = ?");
        $countStmt->execute([$this->testStudentId]);
        $finalCount = $countStmt->fetch()['count'];
        
        echo "Success inserts: $successCount / $attempts\n";
        echo "Final DB count: $finalCount\n";
        
        if ($successCount == 1 && $finalCount == 1) {
            echo "✅ PASS: Only one advisor created despite $attempts attempts\n";
            return true;
        } else {
            echo "❌ FAIL: Expected 1 success and 1 final count\n";
            return false;
        }
    }
    
    public function cleanup() {
        echo "\n=== Cleaning Up ===\n";
        
        $this->db->prepare("DELETE FROM student_advisors WHERE student_id = ?")->execute([$this->testStudentId]);
        $this->db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$this->testStudentId]);
        
        echo "Test data cleaned up.\n";
    }
    
    public function run() {
        $results = [];
        
        try {
            $this->setup();
            
            $results[] = $this->testUniqueConstraint();
            $results[] = $this->testTransactionRollback();
            $results[] = $this->testForUpdateLock();
            $results[] = $this->testMultipleSequentialInserts();
            
            $this->cleanup();
            
            $passed = !in_array(false, $results, true);
            return $passed;
        } catch (Exception $e) {
            echo "\n❌ TEST ERROR: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            $this->cleanup();
            return false;
        }
    }
}

// Run test
echo "==============================================\n";
echo "  Advisor Instance Sequential Test\n";
echo "==============================================\n\n";

$test = new AdvisorSequentialTest();
$passed = $test->run();

echo "\n==============================================\n";
echo $passed ? "✅ ALL TESTS PASSED\n" : "❌ SOME TESTS FAILED\n";
echo "==============================================\n";

exit($passed ? 0 : 1);
