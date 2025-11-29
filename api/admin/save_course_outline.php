<?php
/**
 * Save Course Outline API Endpoint
 * 
 * Saves an approved course outline as a course metadata JSON file.
 * Creates the course structure with empty lesson content slots ready for generation.
 * 
 * POST /api/admin/save_course_outline.php
 * 
 * Required fields:
 * - courseId: Identifier for the course file (e.g., "algebra_1", "biology_intro")
 * - outline: Complete outline object from generate_course_outline.php
 * 
 * Optional fields:
 * - overwrite: Allow overwriting existing course file (default: false)
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "courseId": "...",
 *   "courseFile": "/path/to/course.json",
 *   "totalUnits": 6,
 *   "totalLessons": 30,
 *   "message": "..."
 * }
 */

require_once __DIR__ . '/../admin/auth_check.php';
requireAdmin();

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Get JSON input
$input = getJSONInput();

// Validate required fields
$required = ['courseId', 'outline'];
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
$outline = $input['outline'];
$overwrite = $input['overwrite'] ?? false;

// Validate courseId
if (!preg_match('/^[a-z0-9_-]+$/i', $courseId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'courseId must contain only letters, numbers, hyphens, and underscores'
    ]);
    exit;
}

// Validate outline structure
if (!isset($outline['units']) || !is_array($outline['units'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'outline must contain a units array'
    ]);
    exit;
}

try {
    // Determine course file path
    $courseDir = __DIR__ . '/../course/courses/';
    if (!is_dir($courseDir)) {
        mkdir($courseDir, 0755, true);
    }
    
    $courseFile = $courseDir . 'course_' . $courseId . '.json';
    
    // Check if file exists
    if (file_exists($courseFile) && !$overwrite) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Course file already exists. Set overwrite=true to replace it.',
            'courseFile' => $courseFile
        ]);
        exit;
    }
    
    // Build course metadata structure
    $courseMetadata = [
        'courseName' => $outline['courseName'] ?? $outline['subject'] ?? 'Untitled Course',
        'subject' => $outline['subject'] ?? '',
        'level' => $outline['level'] ?? '',
        'description' => $outline['description'] ?? '',
        'units' => [],
        'version' => '1.0.0',
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ];
    
    $totalLessons = 0;
    
    // Transform outline units into course metadata structure
    foreach ($outline['units'] as $unit) {
        $unitData = [
            'unitNumber' => $unit['unitNumber'],
            'unitTitle' => $unit['unitTitle'],
            'description' => $unit['description'] ?? '',
            'standards' => $unit['standards'] ?? [],
            'lessons' => []
        ];
        
        // Add lesson placeholders (no content yet)
        if (isset($unit['lessons']) && is_array($unit['lessons'])) {
            foreach ($unit['lessons'] as $lesson) {
                $lessonPlaceholder = [
                    'lessonNumber' => $lesson['lessonNumber'],
                    'lessonTitle' => $lesson['lessonTitle'],
                    'description' => $lesson['description'] ?? '',
                    'objectives' => [],
                    'explanation' => '',
                    'guidedExamples' => [],
                    'practiceProblems' => [],
                    'quizQuestions' => [],
                    'videoPlaceholder' => '',
                    'summary' => '',
                    'estimatedDuration' => 45,
                    'difficulty' => 'intermediate',
                    'status' => 'outline' // Mark as not yet generated
                ];
                
                $unitData['lessons'][] = $lessonPlaceholder;
                $totalLessons++;
            }
        }
        
        $courseMetadata['units'][] = $unitData;
    }
    
    // Save to file
    $json = json_encode($courseMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($courseFile, $json) === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to write course file',
            'courseFile' => $courseFile
        ]);
        exit;
    }
    
    // Log the activity
    $admin = getAdminFromToken();
    logActivity(
        $admin['userId'],
        'SAVE_COURSE_OUTLINE',
        "Saved course outline: $courseId with " . count($courseMetadata['units']) . " units, $totalLessons lessons"
    );
    
    // Return success
    $response = [
        'success' => true,
        'courseId' => $courseId,
        'courseFile' => $courseFile,
        'courseName' => $courseMetadata['courseName'],
        'totalUnits' => count($courseMetadata['units']),
        'totalLessons' => $totalLessons,
        'message' => "Course outline saved successfully. Ready for lesson generation."
    ];
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("[Save Course Outline] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
