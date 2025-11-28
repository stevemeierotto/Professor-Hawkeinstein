<?php
/**
 * Set Active Agent for User
 * Tracks agent selection and updates last_active timestamp
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireAuth();

$input = getJSONInput();
$agentId = $input['agentId'] ?? null;
$userId = $input['userId'] ?? null;

if (empty($agentId)) {
    sendJSON(['success' => false, 'message' => 'Agent ID required'], 400);
}

try {
    $db = getDB();
    
    // Verify agent exists and is active
    $agentStmt = $db->prepare("SELECT agent_id, agent_name, is_active FROM agents WHERE agent_id = :agentId");
    $agentStmt->execute(['agentId' => $agentId]);
    $agent = $agentStmt->fetch();
    
    if (!$agent) {
        error_log("[Set Active] Agent $agentId not found");
        sendJSON(['success' => false, 'message' => 'Agent not found'], 404);
    }
    
    if (!$agent['is_active']) {
        error_log("[Set Active] Agent $agentId is inactive");
        sendJSON(['success' => false, 'message' => 'Agent is inactive'], 400);
    }
    
    // Update agent last_active timestamp (if column exists)
    try {
        $updateStmt = $db->prepare("UPDATE agents SET last_active = NOW() WHERE agent_id = :agentId");
        $updateStmt->execute(['agentId' => $agentId]);
        error_log("[Set Active] Updated last_active for agent {$agent['agent_name']} (ID: $agentId)");
    } catch (Exception $e) {
        // Column may not exist yet - non-critical, continue
        error_log("[Set Active] Note: last_active update skipped (" . $e->getMessage() . ")");
    }
    
    // Log the selection activity
    if ($userId) {
        logActivity($userId, 'AGENT_SELECTED', "Selected agent: {$agent['agent_name']} (ID: $agentId)");
    }
    
    error_log("[Set Active] User $userId selected agent {$agent['agent_name']} (ID: $agentId)");
    
    sendJSON([
        'success' => true,
        'message' => 'Agent selected successfully',
        'agent' => [
            'id' => $agent['agent_id'],
            'name' => $agent['agent_name'],
            'active' => $agent['is_active']
        ],
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    error_log("[Set Active] Error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to set active agent'], 500);
}
