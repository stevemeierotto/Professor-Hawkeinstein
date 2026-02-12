<?php
/**
 * Get Available AI Agents
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../config/database.php';


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireAuth();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('agent_list');

try {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT 
            agent_id,
            agent_name,
            agent_type,
            specialization,
            personality_config,
            is_active
        FROM agents
        WHERE is_active = 1
        ORDER BY agent_name
    ");
    
    $agents = $stmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'agents' => $agents
    ]);
    
} catch (Exception $e) {
    error_log("Agents fetch error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch agents'], 500);
}
