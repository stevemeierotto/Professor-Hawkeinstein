<?php
/**
 * Get Available AI Agents
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireAuth();

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
