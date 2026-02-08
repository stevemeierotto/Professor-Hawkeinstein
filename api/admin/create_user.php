<?php
/**
 * Create User API (Root only)
 * 
 * SECURITY POLICY CHANGE (February 2026):
 * Admin accounts can ONLY be created via invitation flow.
 * This endpoint now blocks direct admin creation and redirects to invite_admin API.
 * 
 * Allowed:
 * - Student account creation (if needed for testing)
 * 
 * Blocked:
 * - Admin account creation (must use invite_admin.php)
 * - Staff account creation (must use invite_admin.php)
 * - Root account creation (must use invite_admin.php)
 * 
 * Rationale:
 * - Enforces Google SSO for all admin accounts
 * - Creates audit trail via invitation system
 * - Prevents password-based admin backdoors
 * - Ensures consistent onboarding lifecycle
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$currentUser = requireAdmin();

// Only root can create users
if ($currentUser['role'] !== 'root') {
    http_response_code(403);
    echo json_encode(['error' => 'Only root can create user accounts']);
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

// ============================================================================
// SECURITY ENFORCEMENT: Block admin creation, redirect to invitation flow
// ============================================================================
if (in_array($input['role'], ['admin', 'staff', 'root'])) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Direct admin creation is disabled for security',
        'error_code' => 'ADMIN_CREATION_BLOCKED',
        'message' => 'Admin accounts must be created via invitation system',
        'instructions' => [
            '1. Use the "Invite Admin" form instead',
            '2. Admin will receive a secure invitation link',
            '3. Admin authenticates via Google SSO',
            '4. Account is created automatically upon acceptance'
        ],
        'alternative_endpoint' => 'api/admin/invite_admin.php',
        'documentation' => 'See admin_accept_invite.html for invitation flow'
    ]);
    exit;
}

// Validate role (only students allowed for direct creation)
if ($input['role'] !== 'student') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role. Only student accounts can be created directly']);
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
