<?php
/**
 * Update lesson video URL
 * Adds or updates the video_url for a specific lesson
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Require admin authentication
requireAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $courseId = $input['courseId'] ?? null;
    $unitIndex = $input['unitIndex'] ?? null;
    $lessonIndex = $input['lessonIndex'] ?? null;
    $videoUrl = $input['video_url'] ?? '';
    
    if ($courseId === null || $unitIndex === null || $lessonIndex === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: courseId, unitIndex, lessonIndex'
        ]);
        exit;
    }
    
    $db = getDB();
    
    // Try to find published lesson first (lessons table)
    // courseId might be in format "2nd_grade_science" or numeric like "5"
    // First try as numeric course_id
    $stmt = $db->prepare("
        SELECT l.lesson_id
        FROM lessons l
        JOIN units u ON l.unit_id = u.unit_id
        JOIN courses c ON u.course_id = c.course_id
        WHERE c.course_id = ?
        AND u.unit_number = ?
        AND l.lesson_number = ?
        LIMIT 1
    ");
    
    // unit_number and lesson_number in the database are 1-based, but we receive 0-based indices
    $stmt->execute([$courseId, $unitIndex + 1, $lessonIndex + 1]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found by numeric ID, try by course_name pattern matching
    if (!$result) {
        // Convert courseId format: "2nd_grade_science" → look for "2nd Grade Science"
        $courseName = ucwords(str_replace('_', ' ', $courseId));
        
        $stmt = $db->prepare("
            SELECT l.lesson_id
            FROM lessons l
            JOIN units u ON l.unit_id = u.unit_id
            JOIN courses c ON u.course_id = c.course_id
            WHERE c.course_name LIKE ?
            AND u.unit_number = ?
            AND l.lesson_number = ?
            LIMIT 1
        ");
        
        $stmt->execute(['%' . $courseName . '%', $unitIndex + 1, $lessonIndex + 1]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($result && $result['lesson_id']) {
        // This is a published course - update lessons table
        $updateStmt = $db->prepare("
            UPDATE lessons 
            SET video_url = ?
            WHERE lesson_id = ?
        ");
        
        $updateStmt->execute([$videoUrl ?: null, $result['lesson_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Video URL updated successfully',
            'lesson_id' => $result['lesson_id'],
            'video_url' => $videoUrl
        ]);
        exit;
    }
    
    // If not found in published courses, try draft courses
    // unit_index and lesson_index are already 0-based in draft tables
    $stmt = $db->prepare("
        SELECT dlc.content_id
        FROM draft_lesson_content dlc
        JOIN course_drafts cd ON dlc.draft_id = cd.draft_id
        WHERE LOWER(REPLACE(cd.course_name, ' ', '_')) = ?
        AND dlc.unit_index = ?
        AND dlc.lesson_index = ?
        LIMIT 1
    ");
    
    $stmt->execute([$courseId, $unitIndex, $lessonIndex]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || !$result['content_id']) {
        echo json_encode([
            'success' => false,
            'message' => "Lesson not found for course: $courseId, unit: $unitIndex, lesson: $lessonIndex. Make sure the course exists and is published."
        ]);
        exit;
    }
    
    $contentId = $result['content_id'];
    
    // Check if video_url column exists in educational_content
    $checkStmt = $db->prepare("SHOW COLUMNS FROM educational_content LIKE 'video_url'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        // Add the column if it doesn't exist
        $db->exec("ALTER TABLE educational_content ADD COLUMN video_url VARCHAR(255) NULL AFTER content_html");
    }
    
    // Update the video_url in educational_content (for draft courses)
    $updateStmt = $db->prepare("
        UPDATE educational_content 
        SET video_url = ?
        WHERE content_id = ?
    ");
    
    $updateStmt->execute([$videoUrl ?: null, $contentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Video URL updated successfully (draft)',
        'content_id' => $contentId,
        'video_url' => $videoUrl
    ]);
    
} catch (Exception $e) {
    error_log("Error in update_lesson_video.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
