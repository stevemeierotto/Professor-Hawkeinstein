<?php
/**
 * Toggle User Status API (Root only)
 * Activate/deactivate user accounts
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$currentUser = requireAdmin();

if ($currentUser['role'] !== 'root') {
    http_response_code(403);
    echo json_encode(['error' => 'Only root can modify user status']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['user_id']) || !isset($input['activate'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $db = getDB();
    
    // Prevent deactivating root
    $checkStmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
    $checkStmt->execute([$input['user_id']]);
    $user = $checkStmt->fetch();
    
    if ($user && $user['role'] === 'root') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot modify root account']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $stmt->execute([$input['activate'] ? 1 : 0, $input['user_id']]);
    
    logAdminAction(
        $currentUser['userId'],
        'USER_STATUS_CHANGED',
        ($input['activate'] ? 'Activated' : 'Deactivated') . " user ID: {$input['user_id']}",
        ['user_id' => $input['user_id'], 'activate' => $input['activate']]
    );
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Status update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update status']);
}
