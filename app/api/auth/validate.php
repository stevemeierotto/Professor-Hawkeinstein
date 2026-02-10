<?php
/**
 * Token Validation API Endpoint
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();



$userData = requireAuth();

sendJSON([
    'valid' => true,
    'user' => [
        'userId' => $userData['userId'],
        'username' => $userData['username']
    ]
]);
