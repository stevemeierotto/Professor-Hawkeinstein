<?php
/**
 * Shared Authentication Middleware
 * 
 * Provides reusable middleware functions for authentication.
 * Subsystems should wrap these with their own authorization logic.
 * 
 * MUST NOT contain:
 * - Subsystem-specific role checks (use subsystem auth layers)
 * - Business logic
 * - Direct UI/response formatting (caller decides)
 * 
 * See: docs/ARCHITECTURE.md
 */

require_once __DIR__ . '/jwt.php';

/**
 * Require a valid JWT token on the current request
 * Returns user data if valid, sends 401 and exits if not
 * 
 * @param callable|null $errorHandler Optional custom error handler
 * @return array User data from valid token
 */
function require_valid_token($errorHandler = null) {
    $userData = jwt_get_current_user();
    
    if (!$userData) {
        if ($errorHandler && is_callable($errorHandler)) {
            $errorHandler('Authentication required', 401);
        } else {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
    }
    
    return $userData;
}

/**
 * Require a specific role (or one of several roles)
 * Must be called after require_valid_token()
 * 
 * @param array $userData User data from JWT
 * @param string|array $allowedRoles Single role or array of allowed roles
 * @param callable|null $errorHandler Optional custom error handler
 * @return bool True if role matches
 */
function require_role($userData, $allowedRoles, $errorHandler = null) {
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = $userData['role'] ?? '';
    
    if (!in_array($userRole, $allowedRoles, true)) {
        if ($errorHandler && is_callable($errorHandler)) {
            $errorHandler('Insufficient permissions', 403);
        } else {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
            exit;
        }
    }
    
    return true;
}

/**
 * Check if user has a specific role (non-blocking)
 * 
 * @param array $userData User data from JWT
 * @param string|array $roles Single role or array of roles to check
 * @return bool True if user has one of the specified roles
 */
function has_role($userData, $roles) {
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    $userRole = $userData['role'] ?? '';
    return in_array($userRole, $roles, true);
}

/**
 * Check if current request has valid authentication (non-blocking)
 * 
 * @return bool True if request has valid JWT
 */
function is_authenticated() {
    return jwt_get_current_user() !== false;
}
