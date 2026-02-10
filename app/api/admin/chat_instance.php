<?php
/**
 * Chat with Agent Instance API
 * Handles chat for both admin and student advisor instances
 * Routes to C++ agent service with instance-specific memory
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../config/database.php';



$user = requireAuth();
$userId = $user['userId'];
$userRole = $user['role'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: message']);
    exit;
}

$message = trim($input['message']);
$instanceId = $input['instance_id'] ?? null;
$ownerType = $input['owner_type'] ?? ($userRole === 'student' ? 'student' : 'admin');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

try {
    $db = getDB();
    
    // Get user's advisor instance
    $instanceStmt = $db->prepare("
        SELECT 
            ai.instance_id,
            ai.agent_id,
            ai.owner_id,
            ai.owner_type,
            ai.model_path,
            ai.conversation_history,
            ai.custom_system_prompt,
            a.agent_name,
            a.system_prompt,
            a.model_name,
            a.temperature,
            a.max_tokens
        FROM agent_instances ai
        JOIN agents a ON ai.agent_id = a.agent_id
        WHERE ai.owner_id = ? AND ai.owner_type = ? AND ai.is_active = 1
        LIMIT 1
    ");
    
    $instanceStmt->execute([$userId, $ownerType]);
    $instance = $instanceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instance) {
        http_response_code(404);
        echo json_encode([
            'error' => 'No advisor instance found',
            'message' => 'You do not have an advisor instance yet. Please create one first.'
        ]);
        exit;
    }
    
    // Parse conversation history
    $conversationHistory = [];
    if ($instance['conversation_history']) {
        $conversationHistory = json_decode($instance['conversation_history'], true) ?: [];
    }
    
    // Prepare request for C++ agent service
    $systemPrompt = $instance['custom_system_prompt'] ?: $instance['system_prompt'];
    $modelName = $instance['model_path'] ?: $instance['model_name'];
    
    $agentRequest = [
        'agentId' => $instance['agent_id'],
        'userId' => $userId,
        'instanceId' => $instance['instance_id'],
        'message' => $message,
        'agentConfig' => [
            'model' => $modelName,
            'systemPrompt' => $systemPrompt,
            'temperature' => (float)$instance['temperature'],
            'maxTokens' => (int)$instance['max_tokens']
        ],
        'conversationHistory' => array_slice($conversationHistory, -10),  // Last 10 turns for context
        'context' => []
    ];
    
    // Call C++ agent microservice
    $agentResponse = callAgentService('/agent/chat', $agentRequest);
    
    if (!$agentResponse || !isset($agentResponse['success']) || !$agentResponse['success']) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Agent service communication failed',
            'details' => $agentResponse['message'] ?? 'Unknown error'
        ]);
        exit;
    }
    
    $agentReply = $agentResponse['response'] ?? 'Sorry, I could not generate a response.';
    
    // Update conversation history
    $conversationHistory[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'role' => 'user',
        'message' => $message,
        'metadata' => null
    ];
    
    $conversationHistory[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'role' => 'advisor',
        'message' => $agentReply,
        'metadata' => [
            'model' => $modelName,
            'tokens_used' => $agentResponse['tokensUsed'] ?? 0
        ]
    ];
    
    // Update instance in database
    $updateStmt = $db->prepare("
        UPDATE agent_instances 
        SET conversation_history = ?,
            last_interaction = NOW()
        WHERE instance_id = ?
    ");
    
    $updateStmt->execute([
        json_encode($conversationHistory),
        $instance['instance_id']
    ]);
    
    // Log activity
    logActivity($userId, 'AGENT_INSTANCE_CHAT', "Chat with {$ownerType} advisor instance {$instance['instance_id']}");
    
    echo json_encode([
        'success' => true,
        'response' => $agentReply,
        'agent_name' => $instance['agent_name'],
        'instance_id' => $instance['instance_id'],
        'timestamp' => date('c'),
        'conversation_length' => count($conversationHistory)
    ]);
    
} catch (Exception $e) {
    error_log("Agent instance chat error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Chat failed',
        'details' => $e->getMessage()
    ]);
}
?>
