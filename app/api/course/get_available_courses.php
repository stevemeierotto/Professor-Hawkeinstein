<?php
/**
 * Get Available Courses API (Student Access)
 * Returns courses that students can access through the workbook
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$indexFile = __DIR__ . '/courses_index.json';
$coursesDir = __DIR__ . '/courses';

// Check if index exists
if (!file_exists($indexFile)) {
    // Fallback: scan directory
    $courses = [];
    
    if (is_dir($coursesDir)) {
        $files = glob($coursesDir . '/course_*.json');
        
        foreach ($files as $file) {
            $courseData = json_decode(file_get_contents($file), true);
            if ($courseData) {
                // Extract courseId from filename
                $filename = basename($file, '.json');
                $courseId = str_replace('course_', '', $filename);
                
                $courses[] = [
                    'courseId' => $courseId,
                    'courseName' => $courseData['courseName'] ?? 'Untitled Course',
                    'subject' => $courseData['subject'] ?? 'Unknown',
                    'level' => $courseData['level'] ?? 'Unknown',
                    'description' => $courseData['description'] ?? '',
                    'icon' => $courseData['icon'] ?? '📚',
                    'units' => count($courseData['units'] ?? []),
                    'available' => true
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses)
    ]);
    exit();
}

// Load from index
$indexData = json_decode(file_get_contents($indexFile), true);

if (!$indexData || !isset($indexData['courses'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No courses available',
        'courses' => []
    ]);
    exit();
}

// Return courses with student-relevant information
$courses = [];
foreach ($indexData['courses'] as $course) {
    $courseId = $course['courseId'];
    $courseFile = $coursesDir . '/course_' . $courseId . '.json';
    
    // Check if course file exists
    if (file_exists($courseFile)) {
        $courseData = json_decode(file_get_contents($courseFile), true);
        
        $courses[] = [
            'courseId' => $courseId,
            'courseName' => $course['courseName'],
            'subject' => $course['subject'],
            'level' => $course['level'],
            'description' => $course['description'] ?? '',
            'icon' => $course['icon'] ?? '📚',
            'units' => count($courseData['units'] ?? []),
            'available' => true
        ];
    }
}

echo json_encode([
    'success' => true,
    'courses' => $courses,
    'count' => count($courses)
]);
