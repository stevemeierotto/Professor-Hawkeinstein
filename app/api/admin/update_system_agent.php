<?php
/**
 * Update System Agent API
 * 
 * Updates an existing system agent
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['agent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Agent ID is required']);
    exit;
}

if (empty($input['agent_name'])) {
    echo json_encode(['success' => false, 'message' => 'Agent name is required']);
    exit;
}

$db = getDb();

try {
    $agentId = (int)$input['agent_id'];
    $agentName = trim($input['agent_name']);
    $agentRole = $input['agent_role'] ?? 'other';
    $specialization = $input['specialization'] ?? '';
    $modelName = $input['model_name'] ?? 'default';
    $systemPrompt = $input['system_prompt'] ?? '';
    $temperature = isset($input['temperature']) ? (float)$input['temperature'] : 0.7;
    $maxTokens = isset($input['max_tokens']) ? (int)$input['max_tokens'] : 2048;
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;

    // Verify agent exists and is a system agent
    $stmt = $db->prepare("SELECT agent_id FROM agents WHERE agent_id = ? AND agent_type = 'system'");
    $stmt->execute([$agentId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'System agent not found']);
        exit;
    }

    // Store role in personality_config JSON
    $personalityConfig = json_encode(['role' => $agentRole]);

    $stmt = $db->prepare("
        UPDATE agents SET
            agent_name = ?,
            personality_config = ?,
            specialization = ?,
            model_name = ?,
            system_prompt = ?,
            temperature = ?,
            max_tokens = ?,
            is_active = ?
        WHERE agent_id = ? AND agent_type = 'system'
    ");

    $stmt->execute([
        $agentName,
        $personalityConfig,
        $specialization,
        $modelName,
        $systemPrompt,
        $temperature,
        $maxTokens,
        $isActive ? 1 : 0,
        $agentId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'System agent updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
