<?php
// � PRIVACY REGRESSION PROTECTED (Phase 5)
// Changes require privacy review: docs/ANALYTICS_PRIVACY_VALIDATION.md
//
// �🚨 RATE LIMITING & ABUSE PREVENTION
// Analytics endpoints MUST enforce rate limits to prevent abuse
// 
// This module provides IP-based rate limiting for public analytics endpoints
// to prevent abuse, timing attacks, and excessive resource consumption.
//
// Created: 2026-02-08 (Phase 4: Operational Safeguards)

// Rate limit defaults
define('PUBLIC_ANALYTICS_RATE_LIMIT', 60);  // requests per minute
define('ADMIN_ANALYTICS_RATE_LIMIT', 300);  // requests per minute
define('RATE_LIMIT_WINDOW', 60);  // seconds

// Rate limit storage file
define('RATE_LIMIT_FILE', '/tmp/analytics_rate_limits.json');

/**
 * Enforce rate limit for analytics endpoint
 * 
 * @param string $identifier Client identifier (IP address or user ID)
 * @param int $limit Maximum requests allowed per window
 * @param string $contextLabel Endpoint identifier for logging
 * @return void Throws exception if rate limit exceeded
 * @throws RateLimitExceededException
 */
function enforceRateLimit($identifier, $limit, $contextLabel = 'analytics_endpoint') {
    // Get current rate limit state
    $state = loadRateLimitState();
    $now = time();
    $windowKey = "$identifier:$contextLabel";
    
    // Initialize or retrieve window data
    if (!isset($state[$windowKey])) {
        $state[$windowKey] = [
            'count' => 0,
            'window_start' => $now,
            'first_request' => $now
        ];
    }
    
    $window = $state[$windowKey];
    
    // Check if window has expired - reset if so
    if ($now - $window['window_start'] >= RATE_LIMIT_WINDOW) {
        $window = [
            'count' => 0,
            'window_start' => $now,
            'first_request' => $window['first_request']
        ];
    }
    
    // Check if limit exceeded
    if ($window['count'] >= $limit) {
        $resetTime = $window['window_start'] + RATE_LIMIT_WINDOW;
        logRateLimitViolation($identifier, $contextLabel, $window['count'], $limit);
        throw new RateLimitExceededException($limit, $resetTime, $contextLabel);
    }
    
    // Increment count and save
    $window['count']++;
    $state[$windowKey] = $window;
    saveRateLimitState($state);
}

/**
 * Load rate limit state from storage
 * 
 * @return array Rate limit state data
 */
function loadRateLimitState() {
    if (!file_exists(RATE_LIMIT_FILE)) {
        return [];
    }
    
    $json = @file_get_contents(RATE_LIMIT_FILE);
    if ($json === false) {
        return [];
    }
    
    $state = json_decode($json, true);
    if (!is_array($state)) {
        return [];
    }
    
    // Clean up expired entries (older than 5 minutes)
    $now = time();
    foreach ($state as $key => $window) {
        if (isset($window['window_start']) && $now - $window['window_start'] > 300) {
            unset($state[$key]);
        }
    }
    
    return $state;
}

/**
 * Save rate limit state to storage
 * 
 * @param array $state Rate limit state data
 */
function saveRateLimitState($state) {
    $json = json_encode($state, JSON_UNESCAPED_UNICODE);
    @file_put_contents(RATE_LIMIT_FILE, $json, LOCK_EX);
}

/**
 * Get client IP address
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
 * Log rate limit violation
 * 
 * @param string $identifier Client identifier
 * @param string $contextLabel Endpoint identifier
 * @param int $currentCount Current request count
 * @param int $limit Rate limit threshold
 */
function logRateLimitViolation($identifier, $contextLabel, $currentCount, $limit) {
    $logMessage = sprintf(
        "[RATE LIMIT EXCEEDED] IP: %s | Endpoint: %s | Count: %d/%d | Time: %s",
        $identifier,
        $contextLabel,
        $currentCount,
        $limit,
        date('Y-m-d H:i:s')
    );
    
    error_log($logMessage);
}

/**
 * Add rate limit headers to response
 * 
 * @param int $limit Maximum requests per window
 * @param int $remaining Remaining requests in current window
 * @param int $resetTime Unix timestamp when limit resets
 */
function addRateLimitHeaders($limit, $remaining, $resetTime) {
    header("X-RateLimit-Limit: $limit");
    header("X-RateLimit-Remaining: $remaining");
    header("X-RateLimit-Reset: $resetTime");
}

/**
 * Get current rate limit status
 * 
 * @param string $identifier Client identifier
 * @param string $contextLabel Endpoint identifier
 * @return array [limit, remaining, reset_time]
 */
function getRateLimitStatus($identifier, $contextLabel) {
    $state = loadRateLimitState();
    $windowKey = "$identifier:$contextLabel";
    
    if (!isset($state[$windowKey])) {
        return [
            'limit' => PUBLIC_ANALYTICS_RATE_LIMIT,
            'remaining' => PUBLIC_ANALYTICS_RATE_LIMIT,
            'reset' => time() + RATE_LIMIT_WINDOW
        ];
    }
    
    $window = $state[$windowKey];
    $resetTime = $window['window_start'] + RATE_LIMIT_WINDOW;
    
    return [
        'limit' => PUBLIC_ANALYTICS_RATE_LIMIT,
        'remaining' => max(0, PUBLIC_ANALYTICS_RATE_LIMIT - $window['count']),
        'reset' => $resetTime
    ];
}

/**
 * Custom exception for rate limit violations
 */
class RateLimitExceededException extends Exception {
    private $limit;
    private $resetTime;
    private $contextLabel;
    
    public function __construct($limit, $resetTime, $contextLabel) {
        $this->limit = $limit;
        $this->resetTime = $resetTime;
        $this->contextLabel = $contextLabel;
        
        $secondsUntilReset = max(0, $resetTime - time());
        $message = "Rate limit exceeded. Try again in $secondsUntilReset seconds.";
        
        parent::__construct($message, 429);
    }
    
    public function getLimit() {
        return $this->limit;
    }
    
    public function getResetTime() {
        return $this->resetTime;
    }
    
    public function getContextLabel() {
        return $this->contextLabel;
    }
    
    public function toResponse() {
        return [
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => $this->getMessage(),
            'limit' => $this->limit,
            'reset_time' => $this->resetTime,
            'retry_after' => max(0, $this->resetTime - time())
        ];
    }
}
