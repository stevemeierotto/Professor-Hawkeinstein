<?php
/**
 * Approve Course Outline API
 * 
 * Approves a course outline with optional edits and marks it ready for content generation.
 * 
 * @endpoint POST /api/admin/approve_outline.php
 * @requires Admin authentication
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authentication (server-side, never trust client role)
$adminUser = requireAdmin(); // 401 if not authenticated, 403 if not admin/root

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_approve_outline');

header('Content-Type: application/json');

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit;
}

// Validate required fields
$courseId = $data['courseId'] ?? null;
$outline = $data['outline'] ?? null;

if (!$courseId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'courseId is required'
    ]);
    exit;
}

if (!$outline || !is_array($outline)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Valid outline object is required'
    ]);
    exit;
}

try {
    // Validate course directory exists
    $courseDir = __DIR__ . '/../../course/' . $courseId;
    if (!is_dir($courseDir)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Course directory not found: ' . $courseId
        ]);
        exit;
    }
    
    // Backup existing outline
    $outlineFile = $courseDir . '/outline.json';
    if (file_exists($outlineFile)) {
        $backupFile = $courseDir . '/outline.backup.' . time() . '.json';
        copy($outlineFile, $backupFile);
    }
    
    // Save the approved outline
    $outline['approvedAt'] = date('Y-m-d H:i:s');
    $outline['approvedBy'] = $adminUser['user_id'];
    
    file_put_contents($outlineFile, json_encode($outline, JSON_PRETTY_PRINT));
    
    // Update metadata
    $metadataFile = $courseDir . '/metadata.json';
    $metadata = [];
    if (file_exists($metadataFile)) {
        $metadata = json_decode(file_get_contents($metadataFile), true) ?: [];
    }
    
    $metadata['outlineStatus'] = 'approved';
    $metadata['outlineApprovedAt'] = date('Y-m-d H:i:s');
    $metadata['outlineApprovedBy'] = $adminUser['user_id'];
    $metadata['approvedByUsername'] = $adminUser['username'];
    
    // Calculate outline statistics
    $totalUnits = count($outline['units']);
    $totalLessons = 0;
    $totalSkills = 0;
    $totalObjectives = 0;
    
    foreach ($outline['units'] as $unit) {
        $totalLessons += count($unit['lessons']);
        $totalSkills += count($unit['skills']);
        foreach ($unit['lessons'] as $lesson) {
            $totalObjectives += count($lesson['objectives']);
        }
    }
    
    $metadata['outlineStats'] = [
        'totalUnits' => $totalUnits,
        'totalLessons' => $totalLessons,
        'totalSkills' => $totalSkills,
        'totalObjectives' => $totalObjectives
    ];
    
    file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    
    // Create a course status file for generation tracking
    $statusFile = $courseDir . '/generation_status.json';
    $generationStatus = [
        'outlineApproved' => true,
        'contentGenerationEnabled' => true,
        'lastUpdated' => date('Y-m-d H:i:s'),
        'generationPhases' => [
            'outline' => 'completed',
            'lessons' => 'ready',
            'exercises' => 'pending',
            'assessments' => 'pending'
        ]
    ];
    
    file_put_contents($statusFile, json_encode($generationStatus, JSON_PRETTY_PRINT));
    
    // Log the approval
    error_log(sprintf(
        "Outline approved: course=%s, units=%d, lessons=%d, skills=%d, admin=%d (%s)",
        $courseId,
        $totalUnits,
        $totalLessons,
        $totalSkills,
        $adminUser['user_id'],
        $adminUser['username']
    ));
    
    echo json_encode([
        'success' => true,
        'message' => 'Outline approved successfully',
        'courseId' => $courseId,
        'status' => 'approved',
        'approvedBy' => $adminUser['username'],
        'approvedAt' => $metadata['outlineApprovedAt'],
        'stats' => $metadata['outlineStats'],
        'generationEnabled' => true,
        'files' => [
            'outline' => 'course/' . $courseId . '/outline.json',
            'metadata' => 'course/' . $courseId . '/metadata.json',
            'status' => 'course/' . $courseId . '/generation_status.json'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error approving outline: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to approve outline: ' . $e->getMessage()
    ]);
}
