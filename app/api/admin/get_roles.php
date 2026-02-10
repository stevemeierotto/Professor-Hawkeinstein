<?php
/**
 * Get Roles API
 * Returns list of available roles in the system
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';

// Require admin authorization
requireAdmin();

// Return available roles
echo json_encode([
    ['value' => 'student', 'label' => 'Student'],
    ['value' => 'admin', 'label' => 'Admin'],
    ['value' => 'root', 'label' => 'Root']
]);
