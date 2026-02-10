<?php
/**
 * Authentication Header Helpers
 * Centralizes creation of Authorization headers to prevent format confusion
 * 
 * Two distinct authentication formats:
 * 1. CSP API: "Authorization: Token token=<API_KEY>"
 * 2. Internal JWT: "Authorization: Bearer <JWT_TOKEN>"
 */

/**
 * Create CSP API authorization header
 * Format: "Authorization: Token token=<API_KEY>"
 * 
 * @param string $apiKey The CSP API key
 * @return string Authorization header string
 * @throws InvalidArgumentException if API key is invalid
 */
function csp_auth_header($apiKey) {
    if (empty($apiKey)) {
        throw new InvalidArgumentException('CSP API key cannot be empty');
    }
    
    if (!is_string($apiKey)) {
        throw new InvalidArgumentException('CSP API key must be a string');
    }
    
    // Validate key format (basic check)
    if (strlen($apiKey) < 10) {
        throw new InvalidArgumentException('CSP API key appears invalid (too short)');
    }
    
    // CRITICAL: Detect if someone passed a JWT by mistake
    if (strpos($apiKey, '.') !== false && substr_count($apiKey, '.') >= 2) {
        error_log("[AUTH ERROR] JWT token passed to csp_auth_header() - this is a Bearer token, not a CSP API key!");
        throw new InvalidArgumentException('Invalid API key format - looks like a JWT token. Use bearer_auth_header() instead.');
    }
    
    return 'Authorization: Token token=' . $apiKey;
}

/**
 * Create internal JWT authorization header
 * Format: "Authorization: Bearer <JWT_TOKEN>"
 * 
 * @param string $jwt The JWT token
 * @return string Authorization header string
 * @throws InvalidArgumentException if JWT is invalid
 */
function bearer_auth_header($jwt) {
    if (empty($jwt)) {
        throw new InvalidArgumentException('JWT token cannot be empty');
    }
    
    if (!is_string($jwt)) {
        throw new InvalidArgumentException('JWT token must be a string');
    }
    
    // Validate JWT format (should have 3 parts separated by dots)
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        error_log("[AUTH ERROR] Invalid JWT format in bearer_auth_header() - expected 3 parts, got " . count($parts));
        throw new InvalidArgumentException('Invalid JWT format - must have 3 parts (header.payload.signature)');
    }
    
    // CRITICAL: Detect if someone is using this for CSP API
    if (strlen($jwt) < 20 || strlen($jwt) > 2000) {
        error_log("[AUTH WARNING] Suspicious JWT token length: " . strlen($jwt));
    }
    
    return 'Authorization: Bearer ' . $jwt;
}

/**
 * Validate that a header array uses correct format for CSP API
 * 
 * @param array $headers Array of HTTP headers
 * @throws RuntimeException if Bearer token detected in CSP context
 */
function validate_csp_headers($headers) {
    foreach ($headers as $header) {
        if (is_string($header) && stripos($header, 'Authorization:') !== false) {
            // Check for Bearer in CSP context (WRONG)
            if (stripos($header, 'Bearer') !== false) {
                error_log("[AUTH ERROR] Bearer token used for CSP API call - should use 'Token token=' format!");
                throw new RuntimeException('SECURITY ERROR: Bearer token used for CSP API. Use csp_auth_header() instead.');
            }
            
            // Verify correct format
            if (stripos($header, 'Token token=') === false) {
                error_log("[AUTH WARNING] CSP authorization header missing 'Token token=' format");
            }
        }
    }
}

/**
 * Validate that a header array uses correct format for internal API
 * 
 * @param array $headers Array of HTTP headers
 * @throws RuntimeException if Token token= detected in internal context
 */
function validate_bearer_headers($headers) {
    foreach ($headers as $header) {
        if (is_string($header) && stripos($header, 'Authorization:') !== false) {
            // Check for Token token= in internal context (WRONG)
            if (stripos($header, 'Token token=') !== false) {
                error_log("[AUTH ERROR] CSP 'Token token=' format used for internal API call - should use Bearer!");
                throw new RuntimeException('SECURITY ERROR: CSP auth format used for internal API. Use bearer_auth_header() instead.');
            }
            
            // Verify correct format
            if (stripos($header, 'Bearer') === false) {
                error_log("[AUTH WARNING] Internal authorization header missing Bearer format");
            }
        }
    }
}

/**
 * Get formatted header array for CSP API calls
 * Includes authorization, accept, and user-agent headers
 * 
 * @param string $apiKey The CSP API key
 * @return array Array of headers ready for curl
 */
function get_csp_headers($apiKey) {
    $headers = [
        csp_auth_header($apiKey),
        'Accept: application/json',
        'User-Agent: ProfessorHawkeinstein/1.0'
    ];
    
    // Validate format
    validate_csp_headers($headers);
    
    return $headers;
}

/**
 * Get formatted header array for internal API calls
 * Includes authorization and content-type headers
 * 
 * @param string $jwt The JWT token
 * @return array Array of headers ready for curl
 */
function get_bearer_headers($jwt) {
    $headers = [
        bearer_auth_header($jwt),
        'Content-Type: application/json'
    ];
    
    // Validate format
    validate_bearer_headers($headers);
    
    return $headers;
}

/**
 * Extract API key from environment with validation
 * 
 * @return string CSP API key
 * @throws RuntimeException if key not found or invalid
 */
function get_csp_api_key() {
    $apiKey = getenv('CSP_API_KEY');
    
    if (empty($apiKey)) {
        // Try from defined constant
        if (defined('CSP_API_KEY')) {
            $apiKey = CSP_API_KEY;
        }
    }
    
    if (empty($apiKey)) {
        throw new RuntimeException('CSP_API_KEY not found in environment or config');
    }
    
    return $apiKey;
}
