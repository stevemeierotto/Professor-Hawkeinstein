<?php
/**
 * Logout API Endpoint
 * Pure JWT - no server-side session to clear
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('auth_logout');

// CORS headers if needed


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Clear secure httpOnly cookie by setting it to expire in the past

// --- Phase 2 Security: Environment-aware cookie settings ---
// ENV detection: Use ENV or APP_ENV environment variable, default to 'production'.
// In production: secure=true, httpOnly=true, SameSite=Strict.
// In development: secure=false allowed for localhost, httpOnly=true, SameSite=Strict.
// If Strict breaks login, fallback to Lax only for affected flows (see OAuth callback).
$env = getenv('ENV') ?: (getenv('APP_ENV') ?: 'production');
$isProd = ($env === 'production');
$isSecure = $isProd ? true : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$sameSite = 'Strict';
// If Strict breaks login (e.g., for OAuth or cross-site flows), fallback to Lax below (see OAuth callback)
setcookie('auth_token', '', [
    'expires' => time() - 3600,  // Expire 1 hour ago
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => $sameSite
]);


// PHASE 5: Log only minimal info, no sensitive data
error_log("User logged out, cookie cleared (secure=$isSecure, SameSite=$sameSite, ENV=$env)");

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>
