<?php
/**
 * Admin Invitation API Endpoint
 * 
 * Purpose: Allow root users to invite new admins via email
 * 
 * Security:
 * - Root-only access (requireRoot() enforces this)
 * - Validates email format
 * - Generates cryptographically secure tokens
 * - Logs all invitation attempts to audit trail
 * - Rate limiting recommended (not implemented in v1)
 * 
 * Flow:
 * 1. Root provides email + role
 * 2. System generates unique invite token
 * 3. Token stored with 7-day expiration
 * 4. Invite link returned (or email sent in production)
 * 5. Invitee clicks link → Google OAuth → account created/linked
 * 
 * SAFETY: This endpoint is PURELY ADDITIVE
 * - Does not modify existing users
 * - Does not change authentication flows
 * - Only writes to admin_invitations table
 * - Existing logins completely unaffected
 * 
 * Date: February 6, 2026
 */

require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/invite_admin.log');

try {
    // SECURITY: Only root users can invite admins
    // This terminates with 401/403 if not authenticated as root
    $rootUser = requireRoot();
    
    // Rate limiting
    require_once __DIR__ . '/../helpers/rate_limiter.php';
    require_rate_limit_auto('admin_invite_admin');
    
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }
    
    // Extract and validate inputs
    $email = trim($input['email'] ?? '');
    $role = $input['role'] ?? 'admin';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }
    
    // Validate role (only admin/staff/root can be invited)
    $allowedRoles = ['admin', 'staff', 'root'];
    if (!in_array($role, $allowedRoles)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid role. Must be one of: admin, staff, root'
        ]);
        exit;
    }
    
    error_log("[invite_admin] Root user {$rootUser['userId']} inviting $email as $role");
    
    $db = getDB();
    
    // Check if user already exists with this email
    $existingUserStmt = $db->prepare("
        SELECT user_id, username, email, role, auth_provider_required 
        FROM users 
        WHERE email = :email
    ");
    $existingUserStmt->execute(['email' => $email]);
    $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        // User exists - check if already an admin with Google auth
        if (in_array($existingUser['role'], ['admin', 'staff', 'root'])) {
            if ($existingUser['auth_provider_required'] === 'google') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'User already exists as admin with Google SSO enabled'
                ]);
                exit;
            }
            // Existing admin without Google - can re-invite to migrate them
            error_log("[invite_admin] Re-inviting existing admin {$existingUser['username']} to migrate to Google SSO");
        }
    }
    
    // Check for existing pending invitation
    $pendingInviteStmt = $db->prepare("
        SELECT invitation_id, expires_at 
        FROM admin_invitations 
        WHERE email = :email 
        AND used_at IS NULL 
        AND expires_at > NOW()
    ");
    $pendingInviteStmt->execute(['email' => $email]);
    $pendingInvite = $pendingInviteStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pendingInvite) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'A pending invitation already exists for this email',
            'expires_at' => $pendingInvite['expires_at']
        ]);
        exit;
    }
    
    // Generate cryptographically secure invite token (64 characters)
    $inviteToken = bin2hex(random_bytes(32));
    
    // Calculate expiration (7 days from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Insert invitation into database
    $insertStmt = $db->prepare("
        INSERT INTO admin_invitations 
        (email, invite_token, role, invited_by, expires_at)
        VALUES (:email, :token, :role, :invited_by, :expires_at)
    ");
    
    $success = $insertStmt->execute([
        'email' => $email,
        'token' => $inviteToken,
        'role' => $role,
        'invited_by' => $rootUser['userId'],
        'expires_at' => $expiresAt
    ]);
    
    if (!$success) {
        throw new Exception('Failed to store invitation in database');
    }
    
    $invitationId = $db->lastInsertId();
    
    // Log activity for audit trail
    logActivity(
        $rootUser['userId'], 
        'admin_invite_created',
        "Invited $email as $role (invitation_id: $invitationId)"
    );
    
    // Build invitation URL
    // In production, this would be the base URL of your application
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $inviteUrl = "$protocol://$host/course_factory/admin_accept_invite.html?token=$inviteToken";
    
    error_log("[invite_admin] Invitation created: ID=$invitationId, Token=" . substr($inviteToken, 0, 16) . "...");
    
    // In production, send email here
    // For now, return the invite link in the response
    // 
    // Example email sending (commented out):
    // $emailSent = sendInvitationEmail($email, $inviteUrl, $role, $rootUser['username']);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Admin invitation created successfully',
        'invitation' => [
            'invitation_id' => $invitationId,
            'email' => $email,
            'role' => $role,
            'expires_at' => $expiresAt,
            'invite_url' => $inviteUrl
        ],
        // Development-only field (remove in production or when email sending works)
        'dev_note' => 'Send this URL to the invitee. In production, this would be emailed automatically.'
    ]);
    
} catch (Exception $e) {
    error_log("[invite_admin] Error: " . $e->getMessage());
    error_log("[invite_admin] Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create invitation',
        'details' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}

/**
 * Send invitation email (stub for future implementation)
 * 
 * @param string $email Recipient email
 * @param string $inviteUrl Full invitation URL
 * @param string $role Role being invited to
 * @param string $invitedBy Username of person who sent invite
 * @return bool Success
 */
function sendInvitationEmail($email, $inviteUrl, $role, $invitedBy) {
    // TODO: Implement email sending
    // Options:
    // 1. PHP mail() function
    // 2. PHPMailer library
    // 3. External email service (SendGrid, Amazon SES, etc.)
    
    $subject = "Admin Invitation - Professor Hawkeinstein's Educational Foundation";
    $message = "
        You have been invited by $invitedBy to join as a $role.
        
        Click the link below to accept:
        $inviteUrl
        
        This invitation expires in 7 days.
        
        You will be asked to sign in with your Google account.
    ";
    
    // Placeholder return
    return false;
}
