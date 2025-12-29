<?php
/**
 * Course Factory Admin API Proxy
 * 
 * This file acts as a catch-all proxy for admin API requests.
 * It forwards requests from /course_factory/api/admin/* to /api/admin/*
 * 
 * Apache/nginx should be configured to route requests here, or
 * use .htaccess rewrite rules.
 * 
 * See: docs/ARCHITECTURE.md
 */

// Get the requested endpoint from the URL
$requestUri = $_SERVER['REQUEST_URI'];

// Extract the endpoint name (everything after /course_factory/api/admin/)
if (preg_match('#/course_factory/api/admin/(.+)$#', $requestUri, $matches)) {
    $endpoint = $matches[1];
} else {
    // Fallback: try to get from query string or path info
    $endpoint = $_GET['endpoint'] ?? basename($_SERVER['SCRIPT_NAME']);
}

// Remove query string from endpoint if present
$endpoint = strtok($endpoint, '?');

// Security: Only allow .php files
if (!preg_match('/^[a-z_]+\.php$/i', $endpoint)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint']);
    exit;
}

// Build path to actual admin API
$targetFile = __DIR__ . '/../../../api/admin/' . $endpoint;

if (!file_exists($targetFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found: ' . $endpoint]);
    exit;
}

// Forward to the actual endpoint
require_once $targetFile;
