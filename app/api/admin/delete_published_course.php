<?php
/**
 * Delete a published course from the courses table
 * Use with caution - this will permanently delete the course and related data
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_delete_published_course');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['courseId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing courseId.']);
    exit;
}

$courseId = intval($input['courseId']);
$db = getDB();

// First get course info for confirmation
$stmt = $db->prepare("SELECT course_id, course_name, subject_area FROM courses WHERE course_id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Course not found.']);
    exit;
}

// Delete the course (cascading will handle related records if FK constraints are set up)
$stmt = $db->prepare("DELETE FROM courses WHERE course_id = ?");
$stmt->execute([$courseId]);

if ($stmt->rowCount() > 0) {
    echo json_encode([
        'success' => true, 
        'message' => "Course '{$course['course_name']}' deleted successfully.",
        'deletedCourse' => $course
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete course.']);
}
