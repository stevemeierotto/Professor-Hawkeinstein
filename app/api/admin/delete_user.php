<?php
/**
 * Delete User API (Root only)
 * Permanently delete user accounts
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$currentUser = requireAdmin();

if ($currentUser['role'] !== 'root') {
    http_response_code(403);
    echo json_encode(['error' => 'Only root can delete users']);
    exit;
}

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_delete_user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

try {
    $db = getDB();
    
    // Prevent deleting root
    $checkStmt = $db->prepare("SELECT role, username FROM users WHERE user_id = ?");
    $checkStmt->execute([$input['user_id']]);
    $user = $checkStmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    if ($user['role'] === 'root') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete root account']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$input['user_id']]);
    
    logAdminAction(
        $currentUser['userId'],
        'USER_DELETED',
        "Deleted user: {$user['username']} (ID: {$input['user_id']})",
        ['user_id' => $input['user_id'], 'username' => $user['username']]
    );
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Delete user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete user']);
}
