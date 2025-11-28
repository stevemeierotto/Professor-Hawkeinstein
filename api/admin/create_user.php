<?php
/**
 * Create User API (Root only)
 * Allows root to create admin accounts
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$currentUser = requireAdmin();

// Only root can create admins
if ($currentUser['role'] !== 'root') {
    http_response_code(403);
    echo json_encode(['error' => 'Only root can create admin accounts']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

// Validate required fields
$required = ['username', 'email', 'password', 'full_name', 'role'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate role
if (!in_array($input['role'], ['admin', 'student'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role. Can only create admin or student accounts']);
    exit;
}

try {
    $db = getDB();
    
    // Check if username or email already exists
    $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $checkStmt->execute([$input['username'], $input['email']]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email already exists']);
        exit;
    }
    
    // Hash password
    $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password_hash, full_name, role, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $input['username'],
        $input['email'],
        $passwordHash,
        $input['full_name'],
        $input['role'],
        $currentUser['userId']
    ]);
    
    $userId = $db->lastInsertId();
    
    // Log action
    logAdminAction(
        $currentUser['userId'],
        'USER_CREATED',
        "Created {$input['role']} account: {$input['username']}",
        ['user_id' => $userId, 'role' => $input['role']]
    );
    
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'username' => $input['username']
    ]);
    
} catch (Exception $e) {
    error_log("User creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create user']);
}
