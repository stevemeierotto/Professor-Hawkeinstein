<?php
/**
 * Update User Role API
 * Allows root to change user roles
 * Root-only access
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require root authorization
$currentUser = requireRoot(); // 401 if not authenticated, 403 if not root

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_update_user_role');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id']) || !isset($data['role'])) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id and role are required']);
    exit;
}

$userId = (int)$data['user_id'];
$newRole = $data['role'];

// Validate role
$validRoles = ['student', 'admin', 'root'];
if (!in_array($newRole, $validRoles)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role. Must be one of: student, admin, root']);
    exit;
}

$db = getDB();

// Prevent changing root user's own role
if ($userId === $currentUser['user_id'] && $newRole !== 'root') {
    http_response_code(403);
    echo json_encode(['error' => 'Cannot change your own role from root']);
    exit;
}

// Check if user exists
$stmt = $db->prepare("SELECT user_id, username, role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Update role
$stmt = $db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
$stmt->execute([$newRole, $userId]);

echo json_encode([
    'success' => true,
    'message' => 'Role updated successfully',
    'user_id' => $userId,
    'old_role' => $targetUser['role'],
    'new_role' => $newRole
]);
