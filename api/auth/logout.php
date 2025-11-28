<?php
/**
 * Logout API Endpoint
 * Pure JWT - no server-side session to clear
 */

header('Content-Type: application/json');

// CORS headers if needed
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Since we're using pure JWT (stateless), logout is handled client-side
// by clearing the token from sessionStorage. No server-side action needed.
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>
