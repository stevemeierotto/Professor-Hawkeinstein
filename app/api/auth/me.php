<?php
require_once '../../../config/database.php';


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

// Get JWT from cookie
$token = $_COOKIE['auth_token'] ?? '';
if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userData = verifyToken($token);
if (!$userData) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
    exit;
}

// Only return minimal user info for dashboard
$response = [
    'success' => true,
    'user' => [
        'role' => $userData['role'] ?? null,
        'name' => $userData['name'] ?? null
    ]
];

echo json_encode($response);
exit;
