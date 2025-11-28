<?php
/**
 * Token Validation API Endpoint
 */

require_once '../../config/database.php';

setCORSHeaders();

$userData = requireAuth();

sendJSON([
    'valid' => true,
    'user' => [
        'userId' => $userData['userId'],
        'username' => $userData['username']
    ]
]);
