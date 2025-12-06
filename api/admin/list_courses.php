<?php
/**
 * List Courses API
 * Returns all available courses from the courses index
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/auth_check.php';
requireAdmin();

$indexFile = __DIR__ . '/../course/courses_index.json';
$coursesDir = __DIR__ . '/../course/courses';

// If index doesn't exist, scan directory and create it
if (!file_exists($indexFile)) {
    $courses = [];
    
    if (is_dir($coursesDir)) {
        $files = glob($coursesDir . '/course_*.json');
        
        foreach ($files as $file) {
            $courseData = json_decode(file_get_contents($file), true);
            if ($courseData) {
                $courses[] = [
                    'courseId' => $courseData['courseId'] ?? basename($file, '.json'),
                    'courseName' => $courseData['courseName'] ?? 'Untitled Course',
                    'subject' => $courseData['subject'] ?? 'Unknown',
                    'level' => $courseData['level'] ?? 'Unknown',
                    'description' => $courseData['description'] ?? '',
                    'icon' => $courseData['icon'] ?? '📚',
                    'path' => 'api/course/courses/' . basename($file),
                    'units' => $courseData['units'] ?? [],
                    'createdAt' => $courseData['createdAt'] ?? date('Y-m-d\TH:i:s\Z')
                ];
            }
        }
    }
    
    // Create the index file
    $indexData = ['courses' => $courses];
    file_put_contents($indexFile, json_encode($indexData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses),
        'source' => 'directory_scan'
    ]);
    exit();
}

// Load from index file
$indexData = json_decode(file_get_contents($indexFile), true);

if (!$indexData || !isset($indexData['courses'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid courses index file',
        'courses' => []
    ]);
    exit();
}

// Enrich with unit count from actual files
$courses = [];
foreach ($indexData['courses'] as $courseInfo) {
    $courseId = $courseInfo['courseId'];
    $courseFile = $coursesDir . '/course_' . $courseId . '.json';
    
    if (file_exists($courseFile)) {
        $courseData = json_decode(file_get_contents($courseFile), true);
        $courseInfo['units'] = $courseData['units'] ?? [];
        $courseInfo['version'] = $courseData['version'] ?? '1.0.0';
        $courseInfo['updatedAt'] = $courseData['updatedAt'] ?? $courseInfo['createdAt'];
    }
    
    $courses[] = $courseInfo;
}

echo json_encode([
    'success' => true,
    'courses' => $courses,
    'count' => count($courses),
    'source' => 'index_file'
]);
