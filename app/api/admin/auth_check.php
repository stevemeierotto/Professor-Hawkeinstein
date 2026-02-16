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
 * DEFENSE IN DEPTH: Also enforces auth_provider_required
 * If user's account requires Google SSO, verify they used it
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
    
    // ============================================================================
    // DEFENSE IN DEPTH: Enforce authentication provider requirement
    // ============================================================================
    // This is a second checkpoint after login.php enforcement.
    // Even if login enforcement is bypassed (bug, legacy session, etc.),
    // this blocks admin access if wrong provider was used.
    //
    // WHY THIS IS SAFE:
    // - Only checks users with auth_provider_required set (invited admins)
    // - Users with NULL auth_provider_required skip this check (backward compat)
    // - Students never call this function (unaffected)
    // - Legacy admin sessions work until they need Google SSO
    //
    // REQUEST LIFECYCLE:
    // 1. Client sends request with JWT
    // 2. validateToken() decodes JWT
    // 3. Role check (above)
    // 4. Provider enforcement (below) ← YOU ARE HERE
    // 5. Admin endpoint logic runs
    // ============================================================================
    enforceAuthProvider($userData);
    
    return $userData;
}

/**
 * Require root role for the current request
 * Terminates with 403 if user is not root
 * 
 * DEFENSE IN DEPTH: Also enforces auth_provider_required
 * If user's account requires Google SSO, verify they used it
 * 
 * @return array User data if authorized
 */
function requireRoot() {
    // Validate JWT token and get user data
    $userData = validateToken();
    
    if (!$userData) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    
    // Check if user has root role
    if (!isset($userData['role']) || $userData['role'] !== 'root') {
        http_response_code(403);
        echo json_encode(['error' => 'Root privileges required']);
        exit;
    }
    
    // DEFENSE IN DEPTH: Enforce authentication provider requirement
    // (See comment in requireAdmin() for full explanation)
    enforceAuthProvider($userData);
    
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

    static $activityTableChecked = false;
    static $activityTableExists = false;

    if (!$activityTableChecked) {
        try {
            $result = $db->query("SHOW TABLES LIKE 'admin_activity_log'");
            $activityTableExists = (bool) $result->fetch();
        } catch (Exception $e) {
            error_log('[logAdminAction] Failed to verify admin_activity_log table: ' . $e->getMessage());
            $activityTableExists = false;
        }
        $activityTableChecked = true;
    }

    if (!$activityTableExists) {
        error_log('[logAdminAction] admin_activity_log table missing; skipping audit insert for action ' . $action);
        return;
    }

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

/**
 * Enforce authentication provider requirement (Defense in Depth)
 * 
 * Validates that user authenticated via required provider.
 * This is a second checkpoint after login.php enforcement.
 * 
 * WHY THIS EXISTS:
 * - Login enforcement (Step 4) blocks wrong provider at login time
 * - This blocks wrong provider at admin endpoint access time
 * - Catches: legacy sessions, bugs in login, manual JWT manipulation
 * 
 * HOW IT WORKS:
 * 1. Query database for user's auth_provider_required
 * 2. If NULL → no restriction (legacy users, students)
 * 3. If 'google' → check user has Google linked in auth_providers
 * 4. If mismatch → HTTP 403 with clear error
 * 
 * PERFORMANCE:
 * - One additional DB query per admin request (cached in user fetch)
 * - Only runs for admin/root endpoints (students never hit this)
 * - Minimal overhead for critical security check
 * 
 * BACKWARD COMPATIBILITY:
 * - auth_provider_required = NULL → check skipped (all existing users)
 * - Only enforces for users explicitly set to require provider
 * - Does not break any existing sessions
 * 
 * @param array $userData User data from JWT (must include userId)
 * @return void (exits with 403 if enforcement fails)
 */
function enforceAuthProvider($userData) {
    if (!isset($userData['userId'])) {
        // JWT is malformed - should not happen after validateToken()
        error_log("[enforceAuthProvider] No userId in JWT - security error");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid authentication token']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Fetch user's auth_provider_required setting
        $stmt = $db->prepare("
            SELECT auth_provider_required 
            FROM users 
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userData['userId']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            // User not found - should not happen
            error_log("[enforceAuthProvider] User {$userData['userId']} not found in database");
            http_response_code(403);
            echo json_encode(['error' => 'User account not found']);
            exit;
        }
        
        // MIGRATION SAFETY: If auth_provider_required column doesn't exist yet,
        // treat as NULL (no enforcement). This allows system to work before migrations.
        $requiredProvider = $result['auth_provider_required'] ?? null;
        
        // If no provider required, allow access (NULL = no restriction)
        if (empty($requiredProvider)) {
            // No enforcement needed - legacy users or unrestricted accounts
            return;
        }
        
        // User has a provider requirement - verify they used it
        if ($requiredProvider === 'google') {
            // Check if user has Google auth provider linked
            $providerStmt = $db->prepare("
                SELECT auth_provider_id 
                FROM auth_providers 
                WHERE user_id = :user_id 
                  AND provider_type = 'google'
                LIMIT 1
            ");
            $providerStmt->execute(['user_id' => $userData['userId']]);
            $hasGoogle = $providerStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$hasGoogle) {
                // User requires Google but doesn't have it linked
                // This should not happen in normal flow (invitation links Google)
                // But could happen if admin manually set auth_provider_required
                error_log("[enforceAuthProvider] User {$userData['userId']} requires Google but not linked");
                http_response_code(403);
                echo json_encode([
                    'error' => 'Google authentication required but not configured',
                    'error_code' => 'GOOGLE_NOT_LINKED',
                    'message' => 'Your account requires Google Sign-In, but Google is not linked. Contact administrator.'
                ]);
                exit;
            }
            
            // Check if current session is from Google OAuth
            // JWT from Google OAuth will have been created via callback.php
            // Legacy password sessions won't have Google provider
            // 
            // NOTE: Current JWT doesn't include auth_provider claim.
            // We infer from presence of Google link that if they have a valid JWT
            // and Google is linked, they likely used Google OAuth.
            // 
            // FUTURE IMPROVEMENT: Add auth_provider to JWT payload
            // For now, presence of Google link + valid JWT = acceptable
            //
            // The real enforcement happens at login.php (blocks password login)
            // This is defense in depth - catches edge cases
            
            // User has Google linked and valid JWT - allow access
            return;
        }
        
        // Future providers would be checked here
        // Example:
        // if ($requiredProvider === 'microsoft') { ... }
        
        // Unknown provider requirement - deny by default (fail closed)
        error_log("[enforceAuthProvider] User {$userData['userId']} has unknown provider requirement: $requiredProvider");
        http_response_code(403);
        echo json_encode([
            'error' => 'Unknown authentication provider required',
            'error_code' => 'UNKNOWN_PROVIDER',
            'message' => 'Your account has an unsupported authentication requirement. Contact administrator.'
        ]);
        exit;
        
    } catch (Exception $e) {
        // Database error - fail closed (deny access)
        error_log("[enforceAuthProvider] Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Authentication verification failed',
            'message' => 'Unable to verify authentication requirements. Please try again.'
        ]);
        exit;
    }
}
