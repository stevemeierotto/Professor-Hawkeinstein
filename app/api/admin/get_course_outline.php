<?php
/**
 * Get Course Outline API
 * 
 * Retrieves the outline.json and metadata.json for a course.
 * 
 * @endpoint GET /api/admin/get_course_outline.php?courseId=xxx
 * @requires Admin authentication
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authentication
$adminUser = requireAdmin();

header('Content-Type: application/json');

// Get courseId from query parameter
$courseId = $_GET['courseId'] ?? null;

if (!$courseId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'courseId parameter is required'
    ]);
    exit;
}

// Sanitize courseId to prevent directory traversal
$courseId = preg_replace('/[^a-zA-Z0-9_-]/', '', $courseId);

try {
    // Validate course directory exists
    $courseDir = __DIR__ . '/../../course/' . $courseId;
    if (!is_dir($courseDir)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Course not found: ' . $courseId
        ]);
        exit;
    }
    
    // Load outline.json
    $outlineFile = $courseDir . '/outline.json';
    if (!file_exists($outlineFile)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Outline not found for course: ' . $courseId,
            'hint' => 'Generate an outline first using the standards fetch tool'
        ]);
        exit;
    }
    
    $outlineContent = file_get_contents($outlineFile);
    $outline = json_decode($outlineContent, true);
    
    if (!$outline) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to parse outline.json'
        ]);
        exit;
    }
    
    // Load metadata.json if it exists
    $metadataFile = $courseDir . '/metadata.json';
    $metadata = ['outlineStatus' => 'generated_pending_approval'];
    
    if (file_exists($metadataFile)) {
        $metadataContent = file_get_contents($metadataFile);
        $parsedMetadata = json_decode($metadataContent, true);
        if ($parsedMetadata) {
            $metadata = $parsedMetadata;
        }
    }
    
    // Load generation status if it exists
    $statusFile = $courseDir . '/generation_status.json';
    $generationStatus = null;
    
    if (file_exists($statusFile)) {
        $statusContent = file_get_contents($statusFile);
        $generationStatus = json_decode($statusContent, true);
    }
    
    echo json_encode([
        'success' => true,
        'courseId' => $courseId,
        'outline' => $outline,
        'metadata' => $metadata,
        'generationStatus' => $generationStatus
    ]);
    
} catch (Exception $e) {
    error_log("Error getting course outline: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load course outline: ' . $e->getMessage()
    ]);
}
