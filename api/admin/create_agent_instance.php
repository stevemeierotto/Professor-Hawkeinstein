<?php
/**
 * Create Agent Instance API (Admin/Student Advisors)
 * Creates advisor instances for both admins and students
 * Supports owner_type='admin' or owner_type='student'
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

setCORSHeaders();

// Require authentication (admin for creating any instance, student for their own)
$user = requireAuth();
$userId = $user['userId'];
$userRole = $user['role'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['agent_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: agent_id']);
    exit;
}

$agentId = intval($input['agent_id']);
$ownerType = $input['owner_type'] ?? ($userRole === 'admin' || $userRole === 'root' ? 'admin' : 'student');
$ownerId = $input['owner_id'] ?? $userId;  // Admin can specify owner_id, user gets their own
$modelPath = $input['model_path'] ?? null;
$customPrompt = $input['custom_system_prompt'] ?? null;

// Authorization check
if ($userRole === 'student' && ($ownerId != $userId || $ownerType !== 'student')) {
    http_response_code(403);
    echo json_encode(['error' => 'Students can only create instances for themselves']);
    exit;
}

if ($userRole === 'admin' && $ownerType === 'admin' && $ownerId != $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Admins can only create admin instances for themselves']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Verify agent exists and is an advisor type
        $agentStmt = $db->prepare("
            SELECT agent_id, agent_name, system_prompt, model_name, temperature, max_tokens
            FROM agents 
            WHERE agent_id = ? AND (is_advisor = 1 OR is_student_advisor = 1) AND is_active = 1
        ");
        $agentStmt->execute([$agentId]);
        $agent = $agentStmt->fetch();
        
        if (!$agent) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Agent not found or is not an advisor type']);
            exit;
        }
        
        // Verify owner exists
        $ownerStmt = $db->prepare("SELECT user_id, username, role FROM users WHERE user_id = ?");
        $ownerStmt->execute([$ownerId]);
        $owner = $ownerStmt->fetch();
        
        if (!$owner) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Owner user not found']);
            exit;
        }
        
        // Verify owner role matches owner_type
        if ($ownerType === 'student' && $owner['role'] !== 'student') {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Owner must be a student for owner_type=student']);
            exit;
        }
        
        if ($ownerType === 'admin' && !in_array($owner['role'], ['admin', 'root'])) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Owner must be an admin or root for owner_type=admin']);
            exit;
        }
        
        // Check if instance already exists (with row lock)
        $checkStmt = $db->prepare("
            SELECT instance_id 
            FROM agent_instances 
            WHERE owner_id = ? AND owner_type = ? 
            FOR UPDATE
        ");
        $checkStmt->execute([$ownerId, $ownerType]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $db->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => ucfirst($ownerType) . ' already has an advisor instance',
                'existing_instance_id' => $existing['instance_id']
            ]);
            exit;
        }
        
        // Create the instance
        $insertStmt = $db->prepare("
            INSERT INTO agent_instances 
            (agent_id, owner_id, owner_type, model_path, custom_system_prompt, conversation_history, is_active, last_interaction)
            VALUES (?, ?, ?, ?, ?, '[]', 1, NOW())
        ");
        
        $insertStmt->execute([
            $agentId,
            $ownerId,
            $ownerType,
            $modelPath,
            $customPrompt
        ]);
        
        $instanceId = $db->lastInsertId();
        $db->commit();
        
        // Log activity
        logActivity($userId, 'CREATE_AGENT_INSTANCE', "Created $ownerType advisor instance $instanceId for user $ownerId");
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => ucfirst($ownerType) . ' advisor instance created successfully',
            'instance' => [
                'instance_id' => $instanceId,
                'agent_id' => $agentId,
                'agent_name' => $agent['agent_name'],
                'owner_id' => $ownerId,
                'owner_username' => $owner['username'],
                'owner_type' => $ownerType,
                'model_path' => $modelPath,
                'model_name' => $agent['model_name'],
                'conversation_history' => [],
                'last_interaction' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (PDOException $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Create agent instance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to create advisor instance',
        'details' => $e->getMessage()
    ]);
}
?>
