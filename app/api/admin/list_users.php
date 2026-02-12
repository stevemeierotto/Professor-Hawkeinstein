<?php
/**
 * List Users API
 * Returns list of all users (admins only)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization (server-side, never trust client role)
$currentUser = requireAdmin(); // 401 if not authenticated, 403 if not admin/root

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_list_users');

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        user_id, username, email, full_name, role,
        created_at, last_login, is_active
    FROM users
    ORDER BY 
        CASE role
            WHEN 'root' THEN 1
            WHEN 'admin' THEN 2
            WHEN 'student' THEN 3
        END,
        created_at DESC
");

$stmt->execute();

$users = [];
while ($row = $stmt->fetch()) {
    $users[] = $row;
}

echo json_encode($users);
