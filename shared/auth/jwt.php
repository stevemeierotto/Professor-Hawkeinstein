<?php
/**
 * Shared JWT Authentication Utilities
 * 
 * This file provides JWT token generation and verification.
 * Used by both Student Portal and Course Factory subsystems.
 * 
 * MUST NOT contain:
 * - Subsystem-specific business logic
 * - Role-based authorization (that belongs in subsystem auth layers)
 * - Direct database queries
 * 
 * See: docs/ARCHITECTURE.md
 */

require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Load configuration (JWT_SECRET, SESSION_LIFETIME)
require_once __DIR__ . '/../../config/database.php';

/**
 * Generate a JWT token
 * 
 * @param int $userId User's database ID
 * @param string $username User's username
 * @param string $role User's role (student, observer, admin, root)
 * @param array $additionalClaims Optional additional claims to include
 * @return string Encoded JWT token
 */
function jwt_generate($userId, $username, $role, $additionalClaims = []) {
    $payload = array_merge([
        'userId' => $userId,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + SESSION_LIFETIME
    ], $additionalClaims);
    
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

/**
 * Verify and decode a JWT token
 * 
 * @param string $token The JWT token to verify
 * @return array|false Decoded payload as array, or false if invalid/expired
 */
function jwt_verify($token) {
    if (empty($token)) {
        error_log("[jwt_verify] Empty token");
        return false;
    }
    
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $payloadData = (array)$decoded;
        
        // Check expiration
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            error_log("[jwt_verify] Token expired. Exp: " . $payloadData['exp'] . " Now: " . time());
            return false;
        }
        
        error_log("[jwt_verify] Token valid for user: " . ($payloadData['username'] ?? 'unknown'));
        return $payloadData;
        
    } catch (Exception $e) {
        error_log("[jwt_verify] JWT decode error: " . $e->getMessage());
        return false;
    }
}

/**
 * Extract JWT token from Authorization header
 * 
 * @param string|null $authHeader The Authorization header value
 * @return string|null The extracted token, or null if not found
 */
function jwt_extract_from_header($authHeader = null) {
    if ($authHeader === null) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }
    
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Get user data from current request's JWT token
 * Does NOT enforce any role - just extracts and verifies
 * 
 * @return array|false User data from token, or false if no valid token
 */
function jwt_get_current_user() {
    $token = jwt_extract_from_header();
    
    if (!$token) {
        return false;
    }
    
    return jwt_verify($token);
}

/**
 * Create a Bearer authorization header string
 * 
 * @param string $token JWT token
 * @return string Header string "Authorization: Bearer <token>"
 */
function jwt_bearer_header($token) {
    return 'Authorization: Bearer ' . $token;
}
