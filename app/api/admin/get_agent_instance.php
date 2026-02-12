<?php
/**
 * Get Agent Instance API
 * Retrieves advisor instance for authenticated user (admin or student)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

setCORSHeaders();

$user = requireAuth();
$userId = $user['userId'];
$userRole = $user['role'];

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_get_agent_instance');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$ownerType = $_GET['owner_type'] ?? ($userRole === 'student' ? 'student' : 'admin');

try {
    $db = getDB();
    
    // Get user's advisor instance
    $stmt = $db->prepare("
        SELECT 
            ai.instance_id,
            ai.agent_id,
            ai.owner_id,
            ai.owner_type,
            ai.model_path,
            ai.conversation_history,
            ai.progress_notes,
            ai.testing_results,
            ai.strengths_areas,
            ai.growth_areas,
            ai.custom_system_prompt,
            ai.is_active,
            ai.created_at,
            ai.last_interaction,
            a.agent_name,
            a.agent_type,
            a.system_prompt,
            a.model_name,
            a.temperature,
            a.max_tokens
        FROM agent_instances ai
        JOIN agents a ON ai.agent_id = a.agent_id
        WHERE ai.owner_id = ? AND ai.owner_type = ? AND ai.is_active = 1
        LIMIT 1
    ");
    
    $stmt->execute([$userId, $ownerType]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instance) {
        http_response_code(404);
        echo json_encode([
            'error' => 'No advisor instance found',
            'message' => 'You do not have an advisor instance yet.'
        ]);
        exit;
    }
    
    // Parse JSON fields
    if ($instance['conversation_history']) {
        $instance['conversation_history'] = json_decode($instance['conversation_history'], true) ?: [];
    } else {
        $instance['conversation_history'] = [];
    }
    
    if ($instance['testing_results']) {
        $instance['testing_results'] = json_decode($instance['testing_results'], true) ?: [];
    } else {
        $instance['testing_results'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'instance' => $instance
    ]);
    
} catch (Exception $e) {
    error_log("Get agent instance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve advisor instance',
        'details' => $e->getMessage()
    ]);
}
?>
