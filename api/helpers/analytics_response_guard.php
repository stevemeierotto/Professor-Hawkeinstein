<?php
// 🚨 PRIVACY ENFORCEMENT BOUNDARY
// Analytics responses MUST pass this validator
// 
// This module enforces FERPA and COPPA compliance by blocking any analytics response
// that contains personally identifiable information (PII).
//
// CRITICAL: All analytics endpoints must call validateAnalyticsResponse() before output.
//
// Created: 2026-02-08 (Phase 2: API-Layer Privacy Enforcement)

/**
 * Validate analytics response payload for PII leakage
 * 
 * This is a HARD PRIVACY BOUNDARY - blocks any response containing PII fields
 * 
 * @param mixed $payload The response data to validate (array or object)
 * @param string $contextLabel Endpoint identifier for logging (e.g. 'admin_analytics_overview')
 * @return void Throws exception if validation fails
 * @throws AnalyticsPrivacyViolationException if PII detected
 */
function validateAnalyticsResponse($payload, $contextLabel = 'unknown_endpoint') {
    $violations = [];
    
    // Convert objects to arrays for uniform processing
    if (is_object($payload)) {
        $payload = json_decode(json_encode($payload), true);
    }
    
    // Skip validation if payload is not array-like
    if (!is_array($payload)) {
        return; // Scalars and null are safe
    }
    
    // Recursively scan for PII keys
    $violations = scanForPII($payload, '');
    
    // Check structural patterns that indicate per-user data
    $structuralViolations = detectPerUserDataStructure($payload);
    $violations = array_merge($violations, $structuralViolations);
    
    // If violations detected, fail immediately
    if (!empty($violations)) {
        handlePrivacyViolation($violations, $contextLabel, $payload);
    }
}

/**
 * Recursively scan payload for PII field names
 * 
 * @param array $data The data structure to scan
 * @param string $keyPath Current path in nested structure (e.g. 'data.users[0].email')
 * @param int $depth Current nesting depth
 * @return array List of violation messages
 */
function scanForPII($data, $keyPath, $depth = 0) {
    $violations = [];
    
    // Enforce maximum nesting depth (prevents deeply nested per-user structures)
    if ($depth > 3) {
        $violations[] = "Excessive nesting depth ($depth levels) at path: $keyPath";
        return $violations;
    }
    
    // PII field names that are NEVER allowed in analytics responses
    $forbiddenKeys = [
        'user_id',
        'email',
        'username',
        'name',
        'first_name',
        'last_name',
        'full_name',
        'phone',
        'phone_number',
        'address',
        'street',
        'city',
        'zip',
        'postal_code',
        'ip',
        'ip_address',
        'session_id',
        'session_token',
        'auth_token',
        'password',
        'ssn',
        'date_of_birth',
        'dob',
        'birthdate'
    ];
    
    foreach ($data as $key => $value) {
        $currentPath = $keyPath ? "$keyPath.$key" : $key;
        
        // Check if key name itself is forbidden
        $lowerKey = strtolower($key);
        if (in_array($lowerKey, $forbiddenKeys)) {
            $violations[] = "FORBIDDEN KEY DETECTED: '$key' at path: $currentPath";
        }
        
        // Recursively check nested structures
        if (is_array($value)) {
            $nestedViolations = scanForPII($value, $currentPath, $depth + 1);
            $violations = array_merge($violations, $nestedViolations);
        } elseif (is_object($value)) {
            $nestedViolations = scanForPII((array)$value, $currentPath, $depth + 1);
            $violations = array_merge($violations, $nestedViolations);
        }
    }
    
    return $violations;
}

/**
 * Detect structural patterns indicating per-user data
 * 
 * Analytics should return AGGREGATES, not individual user records
 * 
 * @param array $data The data structure to analyze
 * @return array List of violation messages
 */
function detectPerUserDataStructure($data) {
    $violations = [];
    
    // Check if response contains an array of objects with user-like fields
    foreach ($data as $key => $value) {
        if (is_array($value) && !empty($value)) {
            // If it's a numeric array (list of items)
            if (array_keys($value) === range(0, count($value) - 1)) {
                $firstItem = $value[0];
                
                // Check if items look like user records (multiple identifying fields)
                if (is_array($firstItem)) {
                    $suspiciousFieldCount = 0;
                    $suspiciousFields = ['id', 'created_at', 'updated_at', 'status', 'role'];
                    
                    foreach ($suspiciousFields as $field) {
                        if (array_key_exists($field, $firstItem)) {
                            $suspiciousFieldCount++;
                        }
                    }
                    
                    // If array contains objects with 3+ typical record fields, flag it
                    if ($suspiciousFieldCount >= 3 && count($value) > 0) {
                        $violations[] = "SUSPICIOUS STRUCTURE: Array '$key' contains " . count($value) . " object(s) resembling individual records (fields: " . implode(', ', array_keys($firstItem)) . ")";
                    }
                }
            }
        }
    }
    
    return $violations;
}

/**
 * Handle privacy violation - block response and log incident
 * 
 * @param array $violations List of violation messages
 * @param string $contextLabel Endpoint identifier
 * @param mixed $payload The offending payload (for logging only)
 * @throws AnalyticsPrivacyViolationException
 */
function handlePrivacyViolation($violations, $contextLabel, $payload) {
    // Log to security/privacy log
    $logMessage = sprintf(
        "[PRIVACY VIOLATION] Endpoint: %s | Violations: %s | Time: %s",
        $contextLabel,
        json_encode($violations),
        date('Y-m-d H:i:s')
    );
    
    error_log($logMessage);
    
    // In non-production: log payload structure for debugging
    if (getenv('APP_ENV') !== 'production') {
        error_log("[PRIVACY VIOLATION DEBUG] Payload keys: " . json_encode(array_keys($payload)));
    }
    
    // Throw exception with appropriate detail based on environment
    throw new AnalyticsPrivacyViolationException(
        $violations,
        $contextLabel,
        getenv('APP_ENV') === 'production'
    );
}

/**
 * Custom exception for analytics privacy violations
 */
class AnalyticsPrivacyViolationException extends Exception {
    private $violations;
    private $contextLabel;
    private $isProduction;
    
    public function __construct($violations, $contextLabel, $isProduction = false) {
        $this->violations = $violations;
        $this->contextLabel = $contextLabel;
        $this->isProduction = $isProduction;
        
        $message = $isProduction
            ? 'Analytics response blocked: privacy policy violation'
            : 'PII detected in analytics response: ' . implode(' | ', $violations);
        
        parent::__construct($message, 403);
    }
    
    public function getViolations() {
        return $this->violations;
    }
    
    public function getContextLabel() {
        return $this->contextLabel;
    }
    
    public function toResponse() {
        $response = [
            'success' => false,
            'error' => 'privacy_violation',
            'message' => $this->getMessage()
        ];
        
        // Include violation details in non-production
        if (!$this->isProduction) {
            $response['violations'] = $this->violations;
            $response['endpoint'] = $this->contextLabel;
            $response['timestamp'] = date('Y-m-d H:i:s');
        }
        
        return $response;
    }
}

/**
 * Wrapper for sendJSON that enforces analytics validation
 * 
 * Use this instead of sendJSON() for all analytics endpoints
 * 
 * @param mixed $data Response payload
 * @param int $statusCode HTTP status code
 * @param string $contextLabel Endpoint identifier for logging
 */
function sendAnalyticsJSON($data, $statusCode = 200, $contextLabel = 'analytics_endpoint') {
    try {
        // Validate response before sending
        validateAnalyticsResponse($data, $contextLabel);
        
        // If validation passes, send normally
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
        
    } catch (AnalyticsPrivacyViolationException $e) {
        // Block response and return error
        http_response_code(403);
        echo json_encode($e->toResponse(), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
