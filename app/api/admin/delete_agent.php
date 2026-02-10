<?php
/**
 * Delete Agent API
 * Remove an agent (with safety checks)
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
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

try {
    $db = getDB();
    
    // Check agent exists
    $check = $db->prepare("SELECT agent_name FROM agents WHERE agent_id = ?");
    $check->execute([$agentId]);
    $agentRow = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$agentRow) {
        http_response_code(404);
        echo json_encode(['error' => 'Agent not found']);
        exit;
    }
    
    $agentName = $agentRow['agent_name'];
    
    // Don't allow deleting core system agents (Professor Hawkeinstein, Summary Agent)
    $coreAgents = [1, 2];
    if (in_array($agentId, $coreAgents)) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete core system agents. These agents are required for platform functionality.']);
        exit;
    }
    
    // Delete agent
    $stmt = $db->prepare("DELETE FROM agents WHERE agent_id = ?");
    $stmt->execute([$agentId]);
    
    // Log action
    logAdminAction(
        $admin['userId'],
        'AGENT_DELETED',
        "Deleted agent: $agentName (ID: $agentId)",
        ['agent_id' => $agentId, 'agent_name' => $agentName]
    );
    
    echo json_encode([
        'success' => true,
        'message' => "Agent '$agentName' deleted successfully"
    ]);
    
} catch (Exception $e) {
    error_log("Delete agent error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete agent: ' . $e->getMessage()]);
}

?>
