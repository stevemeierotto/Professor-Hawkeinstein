<?php
/**
 * Create Course API
 * Creates a new course with metadata and empty unit structure
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

// Extract and validate parameters
$subject = trim($input['subject'] ?? '');
$level = trim($input['level'] ?? '');
$courseName = trim($input['courseName'] ?? '');
$description = trim($input['description'] ?? '');
$icon = trim($input['icon'] ?? '');
$numUnits = intval($input['numUnits'] ?? 6);

// Validation
if (empty($subject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Subject is required']);
    exit();
}

if (empty($level)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Level is required']);
    exit();
}

if ($numUnits < 1 || $numUnits > 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Number of units must be between 1 and 12']);
    exit();
}

// Generate course name if not provided
if (empty($courseName)) {
    $courseName = ucfirst($subject) . ' ' . $level;
}

// Generate course ID (used for filenames and URLs)
// Format: subject-level (lowercase, spaces to hyphens)
$courseId = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $subject . '_' . $level));

// Define paths
$coursesDir = __DIR__ . '/../course/courses';
$courseFile = $coursesDir . '/course_' . $courseId . '.json';
$indexFile = __DIR__ . '/../course/courses_index.json';

// Check if course already exists
if (file_exists($courseFile)) {
    http_response_code(409);
    echo json_encode([
        'success' => false, 
        'message' => "Course already exists: $subject $level",
        'courseId' => $courseId
    ]);
    exit();
}

// Create courses directory if it doesn't exist
if (!is_dir($coursesDir)) {
    mkdir($coursesDir, 0755, true);
}

// Create empty units structure
$units = [];
for ($i = 1; $i <= $numUnits; $i++) {
    $units[] = [
        'unitNumber' => $i,
        'unitTitle' => "Unit $i",
        'description' => '',
        'lessons' => []
    ];
}

// Create course metadata structure
$courseData = [
    'courseId' => $courseId,
    'courseName' => $courseName,
    'subject' => $subject,
    'level' => $level,
    'description' => $description,
    'icon' => $icon ?: '📚',
    'units' => $units,
    'version' => '1.0.0',
    'createdAt' => date('Y-m-d\TH:i:s\Z'),
    'updatedAt' => date('Y-m-d\TH:i:s\Z')
];

// Write course JSON file
$jsonContent = json_encode($courseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents($courseFile, $jsonContent) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to write course file']);
    exit();
}

// Update or create courses index
$coursesIndex = ['courses' => []];

if (file_exists($indexFile)) {
    $existingIndex = json_decode(file_get_contents($indexFile), true);
    if ($existingIndex && isset($existingIndex['courses'])) {
        $coursesIndex = $existingIndex;
    }
}

// Check if course already in index (shouldn't happen, but just in case)
$existsInIndex = false;
foreach ($coursesIndex['courses'] as $course) {
    if ($course['courseId'] === $courseId) {
        $existsInIndex = true;
        break;
    }
}

if (!$existsInIndex) {
    $coursesIndex['courses'][] = [
        'courseId' => $courseId,
        'courseName' => $courseName,
        'subject' => $subject,
        'level' => $level,
        'description' => $description,
        'icon' => $icon ?: '📚',
        'path' => 'api/course/courses/course_' . $courseId . '.json',
        'createdAt' => date('Y-m-d\TH:i:s\Z')
    ];
}

// Write updated index
$indexContent = json_encode($coursesIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents($indexFile, $indexContent) === false) {
    // Log error but don't fail - course file was created successfully
    error_log('Warning: Failed to update courses index');
}

// Success response
http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Course created successfully',
    'course' => [
        'courseId' => $courseId,
        'courseName' => $courseName,
        'subject' => $subject,
        'level' => $level,
        'description' => $description,
        'icon' => $icon ?: '📚',
        'numUnits' => $numUnits,
        'filePath' => $courseFile
    ]
]);
