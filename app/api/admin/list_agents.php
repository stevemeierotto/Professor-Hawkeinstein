<?php
/**
 * List Agents API
 * Returns all agents with their parameters
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_list_agents');

try {
    $db = getDB();
    
    // Get all agents
    $stmt = $db->query("
        SELECT 
            agent_id,
            agent_name,
            agent_type,
            specialization,
            model_name,
            system_prompt,
            temperature,
            max_tokens,
            is_active,
            created_at
        FROM agents
        ORDER BY agent_id ASC
    ");
    
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert numeric strings to proper types
    foreach ($agents as &$agent) {
        $agent['agent_id'] = intval($agent['agent_id']);
        $agent['temperature'] = floatval($agent['temperature']);
        $agent['max_tokens'] = intval($agent['max_tokens']);
        $agent['is_active'] = boolval($agent['is_active']);
    }
    
    echo json_encode([
        'success' => true,
        'agents' => $agents,
        'count' => count($agents)
    ]);
    
} catch (Exception $e) {
    error_log("List agents error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve agents: ' . $e->getMessage()]);
}

?>
