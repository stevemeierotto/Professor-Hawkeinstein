<?php
/**
 * 🔒 ROLE-BASED ACCESS CONTROL
 * Enforces role hierarchy for audit and compliance endpoints
 * 
 * Role Hierarchy:
 * - admin: Standard administrative access
 * - root: Compliance and full audit access (superset of admin)
 * 
 * SECURITY: These functions enforce strict role checks.
 * NO role can bypass privacy safeguards (Phases 1-5).
 * 
 * Created: 2026-02-09 (Phase 6: Audit Access)
 */

define('ROLE_ADMIN', 'admin');
define('ROLE_ROOT', 'root');

/**
 * Require ROOT role for compliance-level access
 * 
 * Root users have:
 * - Full audit log access
 * - Audit export capabilities
 * - Compliance review tools
 * 
 * Root users CANNOT:
 * - Bypass PII validation (Phase 2)
 * - Override cohort minimums (Phase 3)
 * - Skip rate limiting (Phase 4)
 * - Disable CI checks (Phase 5)
 * 
 * @return array User data with user_id, username, role
 * @throws Exception with HTTP 403 if not root
 */
function requireRoot() {
    // First verify they're authenticated as admin
    $user = requireAdmin();
    
    // Then verify they have root role specifically
    if (!isset($user['role']) || $user['role'] !== ROLE_ROOT) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'insufficient_privileges',
            'message' => 'Root access required for this operation',
            'required_role' => 'root',
            'your_role' => $user['role'] ?? 'unknown'
        ]);
        exit;
    }
    
    return $user;
}

/**
 * Check if user has root role (non-blocking)
 * 
 * @param array $user User data from requireAdmin()
 * @return bool True if user has root role
 */
function hasRootRole($user) {
    return isset($user['role']) && $user['role'] === ROLE_ROOT;
}

/**
 * Check if user has admin role (includes root)
 * 
 * @param array $user User data from requireAdmin()
 * @return bool True if user has admin or root role
 */
function hasAdminRole($user) {
    if (!isset($user['role'])) {
        return false;
    }
    
    return in_array($user['role'], [ROLE_ADMIN, ROLE_ROOT]);
}

/**
 * Get role hierarchy level (for comparison)
 * 
 * @param string $role Role name
 * @return int Hierarchy level (higher = more privileged)
 */
function getRoleLevel($role) {
    switch ($role) {
        case ROLE_ROOT:
            return 100;
        case ROLE_ADMIN:
            return 50;
        default:
            return 0;
    }
}

/**
 * Log privileged audit access
 * 
 * Records when admins/root access audit data for compliance tracking
 * 
 * @param string $action Action performed (view_summary, view_logs, export_audit)
 * @param int $userId User ID performing action
 * @param string $userRole User's role (admin or root)
 * @param array $parameters Request parameters
 */
function logAuditAccess($action, $userId, $userRole, $parameters = []) {
    $logFile = '/tmp/audit_access.log';
    
    $entry = [
        'timestamp' => time(),
        'iso_timestamp' => date('c'),
        'action' => $action,
        'user_id' => $userId,
        'user_role' => $userRole,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'parameters' => $parameters,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    // Append to audit access log
    $json = json_encode($entry, JSON_UNESCAPED_SLASHES);
    file_put_contents($logFile, $json . "\n", FILE_APPEND | LOCK_EX);
    
    // Also log to main analytics audit log for centralized tracking
    if (function_exists('logAnalyticsAccess')) {
        logAnalyticsAccess(
            'audit_access',
            $action,
            $userId,
            $userRole,
            $parameters,
            true,
            ['audit_type' => 'privileged_access']
        );
    }
}
