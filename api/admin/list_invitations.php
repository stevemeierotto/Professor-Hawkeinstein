<?php
/**
 * List Admin Invitations API Endpoint
 * 
 * Purpose: Allow root/admin users to view invitation status
 * 
 * Security:
 * - Admin-only access (requireAdmin() enforces this)
 * - Read-only operation (no mutations)
 * - Useful for management UI
 * 
 * Query Parameters:
 * - pending_only=true - Show only unused, non-expired invitations
 * 
 * Response includes:
 * - Invitation details
 * - Who created the invitation
 * - Whether it was used (and by whom)
 * - Expiration status
 * 
 * SAFETY: This endpoint is READ-ONLY
 * - Does not modify any data
 * - Does not affect authentication flows
 * - Only queries admin_invitations table
 * 
 * Date: February 6, 2026
 */

require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

try {
    // SECURITY: Only admin/root users can view invitations
    $adminUser = requireAdmin();
    
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Check query parameters
    $pendingOnly = isset($_GET['pending_only']) && $_GET['pending_only'] === 'true';
    
    // Fetch invitations
    $invitations = listAdminInvitations($pendingOnly);
    
    // Add computed fields for frontend convenience
    $now = time();
    foreach ($invitations as &$invite) {
        $expiresAt = strtotime($invite['expires_at']);
        $invite['is_expired'] = $expiresAt < $now;
        $invite['is_used'] = !is_null($invite['used_at']);
        $invite['status'] = $invite['is_used'] ? 'used' : 
                           ($invite['is_expired'] ? 'expired' : 'pending');
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'invitations' => $invitations,
        'total' => count($invitations),
        'filter' => $pendingOnly ? 'pending_only' : 'all'
    ]);
    
} catch (Exception $e) {
    error_log("[list_invitations] Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch invitations',
        'details' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}
