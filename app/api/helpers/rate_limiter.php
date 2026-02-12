<?php
/**
 * Centralized Rate Limiting System
 * 
 * Provides database-backed rate limiting for all API endpoints
 * with configurable profiles for different user roles and endpoint types.
 * 
 * Architecture: DEFAULT-ON (Phase 8 - February 2026)
 * All API endpoints are rate limited by default unless explicitly exempted.
 * Automatic role detection from JWT/session context.
 * 
 * Created: February 2026
 * Security hardening initiative
 * Updated: February 2026 (Default-on architecture)
 */

require_once __DIR__ . '/../../config/database.php';

// Rate limit profiles (requests per time window)
// Format: [requests, window_seconds]
define('RATE_LIMITS', [
    'PUBLIC'        => [60, 60],      // 60 requests per minute
    'AUTHENTICATED' => [120, 60],     // 120 requests per minute
    'ADMIN'         => [300, 60],     // 300 requests per minute
    'ROOT'          => [600, 60],     // 600 requests per minute
    'GENERATION'    => [10, 3600],    // 10 requests per hour
]);

// Rate limit log file
define('RATE_LIMIT_LOG', '/tmp/rate_limit.log');

// Double-invocation prevention flag
$GLOBALS['RATE_LIMIT_APPLIED'] = false;

/**
 * Enforce rate limit for an API endpoint
 * 
 * This is the primary function called by all endpoints.
 * Terminates request with 429 if rate limit is exceeded.
 * 
 * @param string $profile Rate limit profile (PUBLIC, AUTHENTICATED, ADMIN, ROOT, GENERATION)
 * @param string|null $identifier Client identifier (IP for public, user_id for authenticated)
 * @param string $endpointLabel Endpoint identifier for logging (e.g., 'login', 'generate_course')
 * @throws Exception if database connection fails
 * @return void Returns normally if limit not exceeded, exits with 429 if exceeded
 */
function enforceRateLimit($profile, $identifier = null, $endpointLabel = 'unknown') {
    // Validate profile
    if (!isset(RATE_LIMITS[$profile])) {
        error_log("[RATE_LIMIT] Invalid profile: $profile");
        $profile = 'PUBLIC'; // Default to most restrictive
    }
    
    // Auto-detect identifier if not provided
    if ($identifier === null) {
        $identifier = ($profile === 'PUBLIC') ? getClientIP() : 'unknown';
    }
    
    // Get profile configuration
    list($maxRequests, $windowSeconds) = RATE_LIMITS[$profile];
    
    try {
        $db = getDB();
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $windowStart = (clone $now)->modify("-{$windowSeconds} seconds");
        
        // Clean up expired entries (older than 2x window)
        $cleanupThreshold = (clone $now)->modify("-" . ($windowSeconds * 2) . " seconds");
        $cleanupStmt = $db->prepare("
            DELETE FROM rate_limits 
            WHERE window_start < :cleanup_threshold
        ");
        $cleanupStmt->execute([
            ':cleanup_threshold' => $cleanupThreshold->format('Y-m-d H:i:s')
        ]);
        
        // Get current request count within the window
        $stmt = $db->prepare("
            SELECT COUNT(*) as request_count,
                   MIN(window_start) as oldest_request
            FROM rate_limits
            WHERE identifier = :identifier
              AND endpoint_class = :profile
              AND window_start >= :window_start
        ");
        
        $stmt->execute([
            ':identifier' => $identifier,
            ':profile' => $profile,
            ':window_start' => $windowStart->format('Y-m-d H:i:s')
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentCount = (int)$result['request_count'];
        
        // Check if limit exceeded
        if ($currentCount >= $maxRequests) {
            // Calculate retry after (time until oldest request expires)
            $oldestRequest = new DateTime($result['oldest_request'], new DateTimeZone('UTC'));
            $resetTime = (clone $oldestRequest)->modify("+{$windowSeconds} seconds");
            $retryAfter = max(1, $resetTime->getTimestamp() - $now->getTimestamp());
            
            // Log violation
            logRateLimitViolation($identifier, $profile, $endpointLabel, $currentCount, $maxRequests);
            
            // Return 429 response
            http_response_code(429);
            header("Retry-After: $retryAfter");
            header("X-RateLimit-Limit: $maxRequests");
            header("X-RateLimit-Remaining: 0");
            header("X-RateLimit-Reset: " . $resetTime->getTimestamp());
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'retry_after_seconds' => $retryAfter,
                'limit' => $maxRequests,
                'window_seconds' => $windowSeconds
            ]);
            exit;
        }
        
        // Record this request
        $insertStmt = $db->prepare("
            INSERT INTO rate_limits 
            (identifier, endpoint_class, window_start, request_count)
            VALUES (:identifier, :profile, :now, 1)
        ");
        
        $insertStmt->execute([
            ':identifier' => $identifier,
            ':profile' => $profile,
            ':now' => $now->format('Y-m-d H:i:s')
        ]);
        
        // Add rate limit headers to response
        $remaining = max(0, $maxRequests - $currentCount - 1);
        header("X-RateLimit-Limit: $maxRequests");
        header("X-RateLimit-Remaining: $remaining");
        header("X-RateLimit-Reset: " . $windowStart->modify("+{$windowSeconds} seconds")->getTimestamp());
        
    } catch (Exception $e) {
        // Log error but don't block request if rate limiting fails
        error_log("[RATE_LIMIT] Database error: " . $e->getMessage());
        // In production, you might want to fail closed (block request) instead
    }
}

/**
 * Automatic Rate Limiting - DEFAULT-ON Architecture
 * 
 * Automatically detects user role and applies appropriate rate limit profile.
 * This is the primary function that should be called by all API endpoints.
 * 
 * SECURITY ARCHITECTURE (Phase 8):
 * - All endpoints are rate limited by default
 * - Automatic role detection from JWT/session
 * - No manual profile selection needed
 * - Prevents double invocation
 * - Supports explicit exemption via RATE_LIMIT_EXEMPT constant
 * 
 * @param string|null $endpointLabel Optional endpoint identifier for logging
 * @return void Returns normally if limit not exceeded, exits with 429 if exceeded
 */
function require_rate_limit_auto($endpointLabel = null) {
    // Check for explicit exemption
    if (defined('RATE_LIMIT_EXEMPT') && RATE_LIMIT_EXEMPT === true) {
        error_log("[RATE_LIMIT] Endpoint explicitly exempted: " . ($endpointLabel ?? 'unknown'));
        return;
    }
    
    // Prevent double invocation
    if ($GLOBALS['RATE_LIMIT_APPLIED'] === true) {
        return;
    }
    
    // Mark as applied
    $GLOBALS['RATE_LIMIT_APPLIED'] = true;
    
    // Auto-detect endpoint label from script name if not provided
    if ($endpointLabel === null) {
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'unknown';
        $endpointLabel = basename($scriptPath, '.php');
    }
    
    // Attempt to detect authenticated user from database.php helpers
    // This relies on validateToken() and JWT verification already being executed
    $userData = null;
    $profile = 'PUBLIC';
    $identifier = getClientIP();
    
    // Try to get user data from JWT if Authorization header exists
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        // Use verifyToken from database.php if available
        if (function_exists('verifyToken')) {
            $userData = verifyToken($token);
        }
    }
    
    // If JWT verification failed, try session-based auth (fallback)
    if (!$userData && isset($_SESSION['user_id'])) {
        $userData = [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'student'
        ];
    }
    
    // Determine profile and identifier based on authentication
    if ($userData) {
        $userId = $userData['user_id'] ?? $userData['userId'] ?? null;
        $role = $userData['role'] ?? 'student';
        
        // Map role to profile
        switch ($role) {
            case 'root':
                $profile = 'ROOT';
                break;
            case 'admin':
                $profile = 'ADMIN';
                break;
            case 'student':
            default:
                $profile = 'AUTHENTICATED';
                break;
        }
        
        // Use user ID as identifier for authenticated users
        if ($userId) {
            $identifier = (string)$userId;
        }
    }
    
    // Apply rate limiting with detected profile
    enforceRateLimit($profile, $identifier, $endpointLabel);
}

/**
 * Get client IP address
 * Handles proxied requests and various server configurations
 * 
 * @return string Client IP address
 */
function getClientIP() {
    // Check for forwarded IP (when behind proxy/load balancer)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Log rate limit violation to file
 * 
 * @param string $identifier Client identifier (IP or user_id)
 * @param string $profile Rate limit profile
 * @param string $endpointLabel Endpoint identifier
 * @param int $currentCount Current request count
 * @param int $maxRequests Maximum allowed requests
 */
function logRateLimitViolation($identifier, $profile, $endpointLabel, $currentCount, $maxRequests) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf(
        "[%s] RATE_LIMIT_EXCEEDED | Profile: %s | Identifier: %s | Endpoint: %s | Count: %d/%d\n",
        $timestamp,
        $profile,
        $identifier,
        $endpointLabel,
        $currentCount,
        $maxRequests
    );
    
    // Write to log file
    @file_put_contents(RATE_LIMIT_LOG, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also log to PHP error log for visibility
    error_log($logEntry);
}

/**
 * Get current rate limit status for an identifier
 * Non-terminating version for informational purposes
 * 
 * @param string $profile Rate limit profile
 * @param string $identifier Client identifier
 * @return array [limit, remaining, reset_time]
 */
function getRateLimitStatus($profile, $identifier) {
    if (!isset(RATE_LIMITS[$profile])) {
        $profile = 'PUBLIC';
    }
    
    list($maxRequests, $windowSeconds) = RATE_LIMITS[$profile];
    
    try {
        $db = getDB();
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $windowStart = (clone $now)->modify("-{$windowSeconds} seconds");
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as request_count,
                   MIN(window_start) as oldest_request
            FROM rate_limits
            WHERE identifier = :identifier
              AND endpoint_class = :profile
              AND window_start >= :window_start
        ");
        
        $stmt->execute([
            ':identifier' => $identifier,
            ':profile' => $profile,
            ':window_start' => $windowStart->format('Y-m-d H:i:s')
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentCount = (int)$result['request_count'];
        $remaining = max(0, $maxRequests - $currentCount);
        
        $resetTime = $now->getTimestamp() + $windowSeconds;
        if ($result['oldest_request']) {
            $oldestRequest = new DateTime($result['oldest_request'], new DateTimeZone('UTC'));
            $resetTime = (clone $oldestRequest)->modify("+{$windowSeconds} seconds")->getTimestamp();
        }
        
        return [
            'limit' => $maxRequests,
            'remaining' => $remaining,
            'reset_time' => $resetTime
        ];
        
    } catch (Exception $e) {
        error_log("[RATE_LIMIT] Status check error: " . $e->getMessage());
        return [
            'limit' => $maxRequests,
            'remaining' => $maxRequests,
            'reset_time' => time() + $windowSeconds
        ];
    }
}

/**
 * Helper: Determine rate limit profile based on user authentication
 * 
 * Common usage pattern:
 * - Call this with validated user data from JWT
 * - Use returned profile in enforceRateLimit()
 * 
 * @param array|null $userData User data from JWT (with 'role' key)
 * @return string Rate limit profile name
 */
function getRateLimitProfile($userData = null) {
    if ($userData === null) {
        return 'PUBLIC';
    }
    
    $role = $userData['role'] ?? 'student';
    
    switch ($role) {
        case 'root':
            return 'ROOT';
        case 'admin':
            return 'ADMIN';
        case 'student':
        default:
            return 'AUTHENTICATED';
    }
}

/**
 * Wrapper for generation endpoints
 * Shorthand for: enforceRateLimit('GENERATION', $identifier, $label)
 * 
 * USE THIS FOR GENERATION ENDPOINTS TO OVERRIDE AUTO DETECTION
 * Generation endpoints require strict 10 requests/hour limit.
 * 
 * @param string $identifier User ID (generation endpoints are always authenticated)
 * @param string $endpointLabel Endpoint identifier
 */
function enforceGenerationRateLimit($identifier, $endpointLabel) {
    // Mark as applied to prevent double invocation if require_rate_limit_auto() was called
    $GLOBALS['RATE_LIMIT_APPLIED'] = true;
    enforceRateLimit('GENERATION', $identifier, $endpointLabel);
}

/**
 * Manual rate limit enforcement with explicit profile
 * Use this ONLY when you need to override automatic detection.
 * 
 * Most endpoints should use require_rate_limit_auto() instead.
 * 
 * @param string $profile Rate limit profile (PUBLIC, AUTHENTICATED, ADMIN, ROOT, GENERATION)
 * @param string $endpointLabel Endpoint identifier for logging
 */
function require_rate_limit($profile, $endpointLabel = null) {
    // Prevent double invocation
    if ($GLOBALS['RATE_LIMIT_APPLIED'] === true) {
        return;
    }
    
    // Mark as applied
    $GLOBALS['RATE_LIMIT_APPLIED'] = true;
    
    // Auto-detect endpoint label if not provided
    if ($endpointLabel === null) {
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'unknown';
        $endpointLabel = basename($scriptPath, '.php');
    }
    
    // Detect identifier based on profile
    $identifier = null;
    if ($profile === 'PUBLIC') {
        $identifier = getClientIP();
    } else {
        // Try to get user ID for authenticated profiles
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            if (function_exists('verifyToken')) {
                $userData = verifyToken($token);
                if ($userData) {
                    $identifier = (string)($userData['user_id'] ?? $userData['userId'] ?? getClientIP());
                }
            }
        }
        
        // Fallback to IP if no user ID found
        if (!$identifier) {
            $identifier = getClientIP();
        }
    }
    
    enforceRateLimit($profile, $identifier, $endpointLabel);
}
