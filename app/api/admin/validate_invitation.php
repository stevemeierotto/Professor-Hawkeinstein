<?php
/**
 * Validate Admin Invitation API Endpoint
 * 
 * Purpose: Check if an invitation token is valid without consuming it
 * 
 * Security:
 * - Public endpoint (no auth required for validation)
 * - Read-only operation
 * - Does not expose sensitive data (only basic invite info)
 * - Does not mark invitation as used
 * 
 * Used by: admin_accept_invite.html to show invitation details
 * 
 * Query Parameters:
 * - token=<invite_token> - The invitation token to validate
 * 
 * Response:
 * - success: true/false
 * - invitation: {email, role, expires_at} if valid
 * - error: Error message if invalid
 * 
 * SAFETY: This endpoint is READ-ONLY
 * - Does not modify any data
 * - Does not affect authentication flows
 * - Only validates invitation existence and expiration
 * 
 * Date: February 6, 2026
 */

require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

// Rate limiting (public endpoint but still rate-limited)
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_validate_invitation');

require_once __DIR__ . '/../../config/database.php';

try {
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Get token from query string
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitation token required']);
        exit;
    }
    
    // Find invitation (checks expiration automatically)
    $invitation = findPendingInvitation($token);
    
    if (!$invitation) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Invitation not found, expired, or already used'
        ]);
        exit;
    }
    
    // Return safe subset of invitation data (don't expose internal IDs)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'invitation' => [
            'email' => $invitation['email'],
            'role' => $invitation['role'],
            'expires_at' => $invitation['expires_at'],
            'created_at' => $invitation['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("[validate_invitation] Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to validate invitation',
        'details' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}
