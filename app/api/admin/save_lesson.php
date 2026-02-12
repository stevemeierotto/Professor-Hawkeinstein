<?php
/**
 * Save Lesson API Endpoint
 * 
 * Safely inserts or updates a generated lesson into the correct course → unit → lesson slot
 * in the JSON metadata structure.
 * 
 * POST /api/admin/save_lesson.php
 * 
 * Required fields:
 * - courseId: ID or filename of the course metadata file
 * - unitNumber: Target unit number
 * - lesson: Complete lesson object (must include lessonNumber)
 * 
 * Optional fields:
 * - createBackup: Boolean, whether to create backup before saving (default: true)
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "action": "inserted"|"updated",
 *   "message": "...",
 *   "unitNumber": 1,
 *   "lessonNumber": 2,
 *   "backupPath": "/path/to/backup.json" (if backup created)
 * }
 */

require_once __DIR__ . '/../admin/auth_check.php';
$adminUser = requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_save_lesson');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../course/CourseMetadata.php';

header('Content-Type: application/json');

// Get JSON input
$input = getJSONInput();

// Validate required fields
$required = ['courseId', 'unitNumber', 'lesson'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit;
    }
}

$courseId = $input['courseId'];
$unitNumber = (int)$input['unitNumber'];
$lessonData = $input['lesson'];
$createBackup = $input['createBackup'] ?? true;

// Validate unitNumber
if ($unitNumber <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'unitNumber must be a positive integer'
    ]);
    exit;
}

// Validate lesson has lessonNumber
if (!isset($lessonData['lessonNumber'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Lesson object must include a lessonNumber field'
    ]);
    exit;
}

$lessonNumber = (int)$lessonData['lessonNumber'];

// Validate lessonNumber
if ($lessonNumber <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'lessonNumber must be a positive integer'
    ]);
    exit;
}

// Determine course file path
// Support both direct file paths and course IDs
$courseDir = __DIR__ . '/../course/courses/';
if (!is_dir($courseDir)) {
    mkdir($courseDir, 0755, true);
}

// If courseId looks like a filename, use it directly
// Otherwise, treat it as an ID and construct the filename
if (strpos($courseId, '.json') !== false) {
    $courseFile = $courseDir . basename($courseId);
} else {
    $courseFile = $courseDir . 'course_' . preg_replace('/[^a-z0-9_-]/i', '_', $courseId) . '.json';
}

// Check if course file exists
if (!file_exists($courseFile)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => "Course file not found: $courseFile",
        'courseId' => $courseId
    ]);
    exit;
}

try {
    // Create backup if requested
    $backupPath = null;
    if ($createBackup) {
        $backupDir = __DIR__ . '/../course/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . basename($courseFile, '.json') . '_backup_' . $timestamp . '.json';
        
        if (!copy($courseFile, $backupPath)) {
            error_log("[Save Lesson] Failed to create backup: $backupPath");
            $backupPath = null; // Continue without backup
        }
    }
    
    // Load course metadata
    $course = new CourseMetadata($courseFile);
    
    // Validate course has the target unit
    $unit = $course->getUnit($unitNumber);
    if (!$unit) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Unit $unitNumber not found in course",
            'availableUnits' => array_map(function($u) {
                return $u['unitNumber'];
            }, $course->getUnits())
        ]);
        exit;
    }
    
    // Insert or update the lesson
    $result = $course->insertLesson($unitNumber, $lessonData);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }
    
    // Save the updated course metadata
    if (!$course->saveToFile($courseFile, true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save course metadata file',
            'courseFile' => $courseFile
        ]);
        exit;
    }
    
    // Add backup path to result if created
    if ($backupPath) {
        $result['backupPath'] = $backupPath;
    }
    
    // Log the activity
    logActivity(
        $adminUser['userId'],
        'SAVE_LESSON',
        "Saved lesson $lessonNumber to unit $unitNumber in course $courseId (action: {$result['action']})"
    );
    
    // Return success
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("[Save Lesson] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
