<?php
/**
 * Integration Test: Dynamic Model Loading
 * Tests: agent creation with model selection, model validation, fallback logic
 * 
 * Usage: php tests/model_loading_test.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/helpers/model_validation.php';

class ModelLoadingTest {
    private $db;
    private $testAgentId;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function run() {
        echo "=== Dynamic Model Loading Integration Test ===\n\n";
        
        try {
            $this->setup();
            
            // Test 1: Get available models
            $this->testGetAvailableModels();
            
            // Test 2: Validate existing model
            $this->testValidateExistingModel();
            
            // Test 3: Validate non-existent model (fallback)
            $this->testValidateNonExistentModel();
            
            // Test 4: Create agent with valid model
            $this->testCreateAgentWithValidModel();
            
            // Test 5: Create agent with invalid model (should fallback)
            $this->testCreateAgentWithInvalidModel();
            
            // Test 6: Verify agent loads model from database
            $this->testAgentLoadsModelFromDB();
            
            // Test 7: Model info retrieval
            $this->testGetModelInfo();
            
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
        
        // Clean up any existing test agents
        $this->db->exec("DELETE FROM agents WHERE agent_name LIKE 'Test Model%'");
        
        echo "✓ Setup complete\n\n";
    }
    
    private function testGetAvailableModels() {
        echo "Test 1: Get available models...\n";
        
        $models = getAvailableModels();
        
        if (empty($models)) {
            throw new Exception("No models found - check MODELS_BASE_PATH");
        }
        
        echo "  Found " . count($models) . " models:\n";
        foreach ($models as $model) {
            echo "    - $model\n";
        }
        
        // Verify expected models exist
        $hasQwen = false;
        $hasLlama = false;
        foreach ($models as $model) {
            if (strpos($model, 'qwen') !== false) $hasQwen = true;
            if (strpos($model, 'llama') !== false) $hasLlama = true;
        }
        
        if (!$hasQwen && !$hasLlama) {
            echo "  ⚠ Warning: No expected models found, but test can continue\n";
        }
        
        echo "✓ Available models retrieved\n\n";
    }
    
    private function testValidateExistingModel() {
        echo "Test 2: Validate existing model...\n";
        
        $testModel = 'qwen2.5-1.5b-instruct-q4_k_m.gguf';
        $validatedModel = validateModelOrFallback($testModel);
        
        if ($validatedModel !== $testModel) {
            throw new Exception("Model validation failed: expected $testModel, got $validatedModel");
        }
        
        echo "  Validated: $validatedModel\n";
        echo "✓ Existing model validated correctly\n\n";
    }
    
    private function testValidateNonExistentModel() {
        echo "Test 3: Validate non-existent model (fallback)...\n";
        
        $invalidModel = 'nonexistent-model-xyz.gguf';
        $validatedModel = validateModelOrFallback($invalidModel);
        
        if ($validatedModel === $invalidModel) {
            throw new Exception("Fallback failed: invalid model was not rejected");
        }
        
        echo "  Invalid model: $invalidModel\n";
        echo "  Fallback to: $validatedModel\n";
        
        // Verify fallback model exists
        $defaultModel = getenv('DEFAULT_MODEL') ?: 'qwen2.5-1.5b-instruct-q4_k_m.gguf';
        if ($validatedModel !== $defaultModel) {
            throw new Exception("Fallback model mismatch: expected $defaultModel, got $validatedModel");
        }
        
        echo "✓ Fallback logic works correctly\n\n";
    }
    
    private function testCreateAgentWithValidModel() {
        echo "Test 4: Create agent with valid model...\n";
        
        $modelName = 'qwen2.5-1.5b-instruct-q4_k_m.gguf';
        $validatedModel = validateModelOrFallback($modelName);
        
        $stmt = $this->db->prepare("
            INSERT INTO agents (agent_name, agent_type, specialization, model_name, system_prompt, is_active)
            VALUES (?, 'test', 'Testing dynamic models', ?, 'Test prompt', 1)
        ");
        $stmt->execute(['Test Model Agent 1', $validatedModel]);
        
        $agentId = $this->db->lastInsertId();
        
        // Verify model stored correctly
        $stmt = $this->db->prepare("SELECT model_name FROM agents WHERE agent_id = ?");
        $stmt->execute([$agentId]);
        $storedModel = $stmt->fetchColumn();
        
        if ($storedModel !== $validatedModel) {
            throw new Exception("Model not stored correctly: expected $validatedModel, got $storedModel");
        }
        
        echo "  Agent ID: $agentId\n";
        echo "  Model: $storedModel\n";
        echo "✓ Agent created with valid model\n\n";
        
        $this->testAgentId = $agentId;
    }
    
    private function testCreateAgentWithInvalidModel() {
        echo "Test 5: Create agent with invalid model (fallback)...\n";
        
        $invalidModel = 'fake-model-does-not-exist.gguf';
        $validatedModel = validateModelOrFallback($invalidModel);
        
        $stmt = $this->db->prepare("
            INSERT INTO agents (agent_name, agent_type, specialization, model_name, system_prompt, is_active)
            VALUES (?, 'test', 'Testing fallback', ?, 'Test prompt', 1)
        ");
        $stmt->execute(['Test Model Agent 2', $validatedModel]);
        
        $agentId = $this->db->lastInsertId();
        
        // Verify fallback model was used
        $stmt = $this->db->prepare("SELECT model_name FROM agents WHERE agent_id = ?");
        $stmt->execute([$agentId]);
        $storedModel = $stmt->fetchColumn();
        
        $defaultModel = getenv('DEFAULT_MODEL') ?: 'qwen2.5-1.5b-instruct-q4_k_m.gguf';
        if ($storedModel !== $defaultModel) {
            throw new Exception("Fallback not applied: expected $defaultModel, got $storedModel");
        }
        
        echo "  Requested: $invalidModel\n";
        echo "  Stored: $storedModel (fallback)\n";
        echo "✓ Fallback applied during agent creation\n\n";
        
        // Clean up this test agent
        $this->db->exec("DELETE FROM agents WHERE agent_id = $agentId");
    }
    
    private function testAgentLoadsModelFromDB() {
        echo "Test 6: Verify agent loads model from database...\n";
        
        if (!$this->testAgentId) {
            throw new Exception("Test agent not created");
        }
        
        // Simulate loading agent (like database.cpp does)
        $stmt = $this->db->prepare("
            SELECT agent_id, agent_name, model_name
            FROM agents 
            WHERE agent_id = ?
        ");
        $stmt->execute([$this->testAgentId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agent) {
            throw new Exception("Test agent not found");
        }
        
        // Verify model name is present
        if (empty($agent['model_name'])) {
            throw new Exception("Model name not loaded from database");
        }
        
        // Construct full path (like C++ code does)
        $modelsBasePath = getenv('MODELS_BASE_PATH') ?: '/home/steve/Professor_Hawkeinstein/models';
        $fullPath = $modelsBasePath . '/' . $agent['model_name'];
        
        echo "  Agent: {$agent['agent_name']}\n";
        echo "  Model filename: {$agent['model_name']}\n";
        echo "  Full path: $fullPath\n";
        
        // Verify file exists
        if (!file_exists($fullPath)) {
            throw new Exception("Model file does not exist at: $fullPath");
        }
        
        echo "✓ Agent successfully loads model from database\n\n";
    }
    
    private function testGetModelInfo() {
        echo "Test 7: Get model info...\n";
        
        $modelName = 'qwen2.5-1.5b-instruct-q4_k_m.gguf';
        $info = getModelInfo($modelName);
        
        if (!$info) {
            throw new Exception("Failed to get model info");
        }
        
        if (!$info['exists']) {
            throw new Exception("Model reported as not existing");
        }
        
        echo "  Filename: {$info['filename']}\n";
        echo "  Path: {$info['path']}\n";
        echo "  Size: {$info['size_gb']} GB ({$info['size_mb']} MB)\n";
        
        if ($info['size_bytes'] <= 0) {
            throw new Exception("Invalid model size");
        }
        
        echo "✓ Model info retrieved correctly\n\n";
    }
    
    private function cleanup() {
        echo "Cleaning up test data...\n";
        
        // Delete test agents
        $this->db->exec("DELETE FROM agents WHERE agent_name LIKE 'Test Model%'");
        
        echo "✓ Cleanup complete\n";
    }
}

// Run the test
$test = new ModelLoadingTest();
$test->run();
