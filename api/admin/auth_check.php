<?php
/**
 * Admin Authorization Middleware
 * Provides reusable functions to verify admin access
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Validate user session/token
 * @return array|false User data if valid, false otherwise
 */
function validateToken() {
    // Check for JWT token in Authorization header
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    error_log("[auth_check] Auth header: " . ($authHeader ? substr($authHeader, 0, 50) . '...' : 'MISSING'));
    
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        error_log("[auth_check] Token extracted, length: " . strlen($token));
        $userData = verifyToken($token);
        error_log("[auth_check] verifyToken result: " . ($userData ? json_encode($userData) : 'FALSE'));
        if ($userData) {
            return $userData;
        }
    } else {
        error_log("[auth_check] No Bearer token found in auth header");
    }
    
    return false;
}

/**
 * Require admin role for the current request
 * Terminates with 403 if user is not an admin
 * 
 * @return array User data if authorized
 */
function requireAdmin() {
    // Validate JWT token and get user data
    $userData = validateToken();
    
    if (!$userData) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    
    // Check if user has admin or root role
    if (!isset($userData['role']) || ($userData['role'] !== 'admin' && $userData['role'] !== 'root')) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin privileges required']);
        exit;
    }
    
    return $userData;
}

/**
 * Check if current user is an admin (non-terminating)
 * 
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    $userData = validateToken();
    
    if (!$userData) {
        return false;
    }
    
    return isset($userData['role']) && ($userData['role'] === 'admin' || $userData['role'] === 'root');
}

/**
 * Get admin user data from database
 * 
 * @param int $userId User ID
 * @return array|null User data or null if not found/not admin
 */
function getAdminUser($userId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT user_id, username, email, name, role, created_at
        FROM users
        WHERE user_id = ? AND role = 'admin'
    ");
    
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result ?: null;
}

/**
 * Log admin action to activity log
 * 
 * @param int $userId Admin user ID
 * @param string $action Action type (e.g., 'AGENT_CREATED', 'CONTENT_APPROVED')
 * @param string $details Action details
 * @param array $metadata Optional additional metadata
 */
function logAdminAction($userId, $action, $details, $metadata = []) {
    $db = getDB();
    
    $metadataJson = json_encode($metadata);
    
    $stmt = $db->prepare("
        INSERT INTO admin_activity_log (user_id, action, details, metadata, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$userId, $action, $details, $metadataJson]);
}

/**
 * Validate and sanitize URL for web scraping
 * 
 * @param string $url URL to validate
 * @return string|false Sanitized URL or false if invalid
 */
function validateScraperUrl($url) {
    // Check if URL is well-formed
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Parse URL
    $parsed = parse_url($url);
    
    // Only allow HTTP and HTTPS
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
        return false;
    }
    
    // Block private/local IPs for security
    if (isset($parsed['host'])) {
        $ip = gethostbyname($parsed['host']);
        
        // Block localhost and private ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }
    
    return $url;
}
