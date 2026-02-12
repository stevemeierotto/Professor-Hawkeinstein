<?php
/**
 * Token Validation API Endpoint
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

require_once __DIR__ . '/../helpers/rate_limiter.php';

$userData = requireAuth();

// Enforce automatic rate limiting (AUTHENTICATED - detected from JWT)
require_rate_limit_auto('auth_validate');

sendJSON([
    'valid' => true,
    'user' => [
        'userId' => $userData['userId'],
        'username' => $userData['username']
    ]
]);
