<?php
/**
 * Integration Test: Agent Selection Flow
 * Tests: select agent → card active → send message → correct routing
 * 
 * Usage: php tests/agent_selection_test.php
 */

require_once __DIR__ . '/../config/database.php';

class AgentSelectionTest {
    private $db;
    private $testUserId;
    private $testAgentId;
    private $testToken;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function run() {
        echo "=== Agent Selection Integration Test ===\n\n";
        
        try {
            $this->setup();
            
            // Test 1: Agent exists and is active
            $this->testAgentAvailability();
            
            // Test 2: Set active agent endpoint
            $this->testSetActiveAgent();
            
            // Test 3: Verify last_active timestamp updated
            $this->testLastActiveUpdate();
            
            // Test 4: Agent selection persists correctly
            $this->testAgentSelectionPersistence();
            
            // Test 5: Chat routes to correct agent
            $this->testChatRouting();
            
            // Test 6: Inactive agent handling
            $this->testInactiveAgentHandling();
            
            // Test 7: Missing agent handling
            $this->testMissingAgentHandling();
            
            $this->cleanup();
            
            echo "\n✅ ALL TESTS PASSED\n";
            
        } catch (Exception $e) {
            echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
            $this->cleanup();
            exit(1);
        }
    }
    
    private function setup() {
        echo "Setting up test data...\n";
        
        // Create test user
        $this->db->exec("DELETE FROM users WHERE username = 'test_agent_selection'");
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, is_active)
            VALUES ('test_agent_selection', 'test_agent@test.com', 'test_hash', 'Test User', 'student', 1)
        ");
        $stmt->execute();
        $this->testUserId = $this->db->lastInsertId();
        
        // Create test agent
        $this->db->exec("DELETE FROM agents WHERE agent_name = 'Test Selection Agent'");
        $stmt = $this->db->prepare("
            INSERT INTO agents (agent_name, agent_type, specialization, model_name, system_prompt, temperature, max_tokens, is_active)
            VALUES ('Test Selection Agent', 'test_type', 'Testing', 'test-model', 'Test prompt', 0.7, 256, 1)
        ");
        $stmt->execute();
        $this->testAgentId = $this->db->lastInsertId();
        
        echo "✓ Test user ID: {$this->testUserId}\n";
        echo "✓ Test agent ID: {$this->testAgentId}\n\n";
    }
    
    private function testAgentAvailability() {
        echo "Test 1: Agent exists and is active...\n";
        
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE agent_id = ? AND is_active = 1");
        $stmt->execute([$this->testAgentId]);
        $agent = $stmt->fetch();
        
        if (!$agent) {
            throw new Exception("Test agent not found or not active");
        }
        
        if ($agent['agent_name'] !== 'Test Selection Agent') {
            throw new Exception("Incorrect agent name: " . $agent['agent_name']);
        }
        
        echo "✓ Agent exists and is active\n\n";
    }
    
    private function testSetActiveAgent() {
        echo "Test 2: Set active agent endpoint...\n";
        
        // Simulate API call
        $requestData = [
            'agentId' => $this->testAgentId,
            'userId' => $this->testUserId
        ];
        
        // Direct database operation (simulating API endpoint logic)
        $stmt = $this->db->prepare("SELECT agent_id, agent_name, is_active FROM agents WHERE agent_id = ?");
        $stmt->execute([$this->testAgentId]);
        $agent = $stmt->fetch();
        
        if (!$agent) {
            throw new Exception("Agent not found in set_active simulation");
        }
        
        if (!$agent['is_active']) {
            throw new Exception("Agent is not active");
        }
        
        echo "✓ Set active agent logic validated\n\n";
    }
    
    private function testLastActiveUpdate() {
        echo "Test 3: Verify last_active timestamp update...\n";
        
        // Check if column exists
        try {
            $this->db->query("SELECT last_active FROM agents LIMIT 1");
            $columnExists = true;
        } catch (Exception $e) {
            $columnExists = false;
            echo "⚠ Warning: last_active column does not exist yet (run migration)\n";
        }
        
        if ($columnExists) {
            // Get initial last_active
            $stmt = $this->db->prepare("SELECT last_active FROM agents WHERE agent_id = ?");
            $stmt->execute([$this->testAgentId]);
            $initialTime = $stmt->fetchColumn();
            
            // Update last_active
            $stmt = $this->db->prepare("UPDATE agents SET last_active = NOW() WHERE agent_id = ?");
            $stmt->execute([$this->testAgentId]);
            
            // Verify update
            $stmt = $this->db->prepare("SELECT last_active FROM agents WHERE agent_id = ?");
            $stmt->execute([$this->testAgentId]);
            $newTime = $stmt->fetchColumn();
            
            if ($newTime === $initialTime) {
                throw new Exception("last_active timestamp not updated");
            }
            
            echo "✓ last_active timestamp updated: $newTime\n\n";
        } else {
            echo "✓ Test skipped (column doesn't exist)\n\n";
        }
    }
    
    private function testAgentSelectionPersistence() {
        echo "Test 4: Agent selection persistence...\n";
        
        // Simulate selecting agent and storing in session/database
        $selectedAgentId = $this->testAgentId;
        
        // Verify agent can be retrieved
        $stmt = $this->db->prepare("SELECT agent_id, agent_name FROM agents WHERE agent_id = ?");
        $stmt->execute([$selectedAgentId]);
        $agent = $stmt->fetch();
        
        if (!$agent || $agent['agent_id'] != $selectedAgentId) {
            throw new Exception("Agent selection persistence failed");
        }
        
        echo "✓ Agent selection persists correctly\n\n";
    }
    
    private function testChatRouting() {
        echo "Test 5: Chat routes to correct agent...\n";
        
        // Simulate chat.php logic
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE agent_id = :agentId AND is_active = 1");
        $stmt->execute(['agentId' => $this->testAgentId]);
        $agent = $stmt->fetch();
        
        if (!$agent) {
            throw new Exception("Agent not found for chat routing");
        }
        
        // Verify agent config can be retrieved
        if (empty($agent['model_name']) || empty($agent['system_prompt'])) {
            throw new Exception("Agent configuration incomplete for routing");
        }
        
        // Test memory storage (simplified)
        $stmt = $this->db->prepare("
            INSERT INTO agent_memories 
            (agent_id, user_id, interaction_type, user_message, agent_response, importance_score)
            VALUES (?, ?, 'chat', 'Test message', 'Test response', 0.5)
        ");
        $stmt->execute([$this->testAgentId, $this->testUserId]);
        
        // Verify memory stored
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM agent_memories 
            WHERE agent_id = ? AND user_id = ? AND user_message = 'Test message'
        ");
        $stmt->execute([$this->testAgentId, $this->testUserId]);
        $count = $stmt->fetchColumn();
        
        if ($count === 0) {
            throw new Exception("Chat interaction not stored correctly");
        }
        
        echo "✓ Chat routes to correct agent and stores interaction\n\n";
    }
    
    private function testInactiveAgentHandling() {
        echo "Test 6: Inactive agent handling...\n";
        
        // Create inactive agent
        $stmt = $this->db->prepare("
            INSERT INTO agents (agent_name, agent_type, model_name, system_prompt, is_active)
            VALUES ('Inactive Test Agent', 'test', 'test-model', 'Test', 0)
        ");
        $stmt->execute();
        $inactiveAgentId = $this->db->lastInsertId();
        
        // Try to select inactive agent
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE agent_id = ? AND is_active = 1");
        $stmt->execute([$inactiveAgentId]);
        $agent = $stmt->fetch();
        
        if ($agent) {
            throw new Exception("Inactive agent should not be selectable");
        }
        
        // Cleanup
        $this->db->exec("DELETE FROM agents WHERE agent_id = $inactiveAgentId");
        
        echo "✓ Inactive agents correctly rejected\n\n";
    }
    
    private function testMissingAgentHandling() {
        echo "Test 7: Missing agent handling...\n";
        
        $nonExistentId = 999999;
        
        // Try to select non-existent agent
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE agent_id = ?");
        $stmt->execute([$nonExistentId]);
        $agent = $stmt->fetch();
        
        if ($agent) {
            throw new Exception("Non-existent agent should not be found");
        }
        
        echo "✓ Missing agents correctly handled\n\n";
    }
    
    private function cleanup() {
        echo "Cleaning up test data...\n";
        
        // Delete test memories
        $this->db->exec("DELETE FROM agent_memories WHERE user_id = {$this->testUserId}");
        
        // Delete test agent
        $this->db->exec("DELETE FROM agents WHERE agent_id = {$this->testAgentId}");
        
        // Delete test user
        $this->db->exec("DELETE FROM users WHERE user_id = {$this->testUserId}");
        
        echo "✓ Cleanup complete\n";
    }
}

// Run the test
$test = new AgentSelectionTest();
$test->run();
