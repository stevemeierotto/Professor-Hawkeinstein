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
    
    // unit_index and lesson_index are already 0-based in the database
    
    // First, find the content_id for this lesson
    // We need to match by course name (converted from courseId) and unit/lesson indices
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
            'message' => "Lesson content not found for course: $courseId, unit_index: $unitIndex, lesson_index: $lessonIndex"
        ]);
        exit;
    }
    
    $contentId = $result['content_id'];
    
    // Check if video_url column exists
    $checkStmt = $db->prepare("SHOW COLUMNS FROM educational_content LIKE 'video_url'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        // Add the column if it doesn't exist
        $db->exec("ALTER TABLE educational_content ADD COLUMN video_url VARCHAR(255) NULL AFTER content_html");
    }
    
    // Update the video_url in educational_content
    $updateStmt = $db->prepare("
        UPDATE educational_content 
        SET video_url = ?
        WHERE content_id = ?
    ");
    
    $updateStmt->execute([$videoUrl ?: null, $contentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Video URL updated successfully',
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
