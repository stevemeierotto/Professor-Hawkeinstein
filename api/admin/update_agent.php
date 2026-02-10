<?php
/**
 * Update Agent API
 * Modify agent parameters: name, temperature, description, specialization
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['agent_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing agent_id']);
    exit;
}

$agentId = intval($input['agent_id']);

// Validate temperature if provided
if (isset($input['temperature'])) {
    $temp = floatval($input['temperature']);
    if ($temp < 0 || $temp > 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Temperature must be between 0 and 2']);
        exit;
    }
}

try {
    $db = getDB();
    
    // Check agent exists
    $check = $db->prepare("SELECT agent_id FROM agents WHERE agent_id = ?");
    $check->execute([$agentId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Agent not found']);
        exit;
    }
    
    // Build dynamic update query
    $updates = [];
    $params = [];
    
    if (isset($input['agent_name']) && !empty($input['agent_name'])) {
        $updates[] = "agent_name = ?";
        $params[] = $input['agent_name'];
    }
    
    if (isset($input['temperature'])) {
        $updates[] = "temperature = ?";
        $params[] = floatval($input['temperature']);
    }
    
    if (isset($input['specialization'])) {
        $updates[] = "specialization = ?";
        $params[] = $input['specialization'] ?: null;
    }
    
    if (isset($input['system_prompt'])) {
        $updates[] = "system_prompt = ?";
        $params[] = $input['system_prompt'] ?: null;
    }
    
    if (isset($input['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = intval($input['is_active']);
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    // Add agent_id to params for WHERE clause
    $params[] = $agentId;
    
    // Execute update
    $updateQuery = "UPDATE agents SET " . implode(", ", $updates) . " WHERE agent_id = ?";
    $stmt = $db->prepare($updateQuery);
    $stmt->execute($params);
    
    // Log action
    logAdminAction(
        $admin['userId'],
        'AGENT_UPDATED',
        "Updated agent ID $agentId: " . implode(", ", array_keys($input)),
        ['agent_id' => $agentId, 'changes' => $input]
    );
    
    // Return updated agent
    $getAgent = $db->prepare("
        SELECT 
            agent_id, agent_name, agent_type, specialization,
            model_name, system_prompt, temperature, max_tokens, is_active
        FROM agents WHERE agent_id = ?
    ");
    $getAgent->execute([$agentId]);
    $agent = $getAgent->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Agent updated successfully',
        'agent' => $agent
    ]);
    
} catch (Exception $e) {
    error_log("Update agent error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update agent: ' . $e->getMessage()]);
}

?>
