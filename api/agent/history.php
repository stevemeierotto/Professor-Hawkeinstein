<?php
/**
 * Get Agent Conversation History
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();

$agentId = $_GET['agentId'] ?? null;
$limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100

if (empty($agentId)) {
    sendJSON(['success' => false, 'message' => 'Agent ID required'], 400);
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT 
            memory_id,
            interaction_type,
            user_message,
            agent_response,
            created_at
        FROM agent_memories
        WHERE agent_id = :agentId AND user_id = :userId
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    
    $stmt->bindValue(':agentId', $agentId, PDO::PARAM_INT);
    $stmt->bindValue(':userId', $userData['userId'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $history = $stmt->fetchAll();
    
    sendJSON([
        'success' => true,
        'history' => array_reverse($history)
    ]);
    
} catch (Exception $e) {
    error_log("History fetch error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to fetch history'], 500);
}
