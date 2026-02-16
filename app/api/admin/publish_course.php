<?php
// Publish a course draft - moves it to published status and creates course entry
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_publish_course');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['draftId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId.']);
    exit;
}

$draftId = intval($input['draftId']);
$db = getDB();

// Get draft info
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    echo json_encode(['success' => false, 'message' => 'Draft not found.']);
    exit;
}

// Get outline
$stmt = $db->prepare("SELECT outline_json FROM course_outlines WHERE draft_id = ? ORDER BY generated_at DESC LIMIT 1");
$stmt->execute([$draftId]);
$outlineResult = $stmt->fetch(PDO::FETCH_ASSOC);
$outlineJson = $outlineResult ? $outlineResult['outline_json'] : null;

// Get approved standards
$stmt = $db->prepare("SELECT standard_code, description FROM approved_standards WHERE draft_id = ?");
$stmt->execute([$draftId]);
$standards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map complexity to difficulty level
$difficultyMap = [
    'basic' => 'beginner',
    'intermediate' => 'intermediate',
    'advanced' => 'advanced'
];
$difficulty = $difficultyMap[$draft['complexity']] ?? 'intermediate';

try {
    $db->beginTransaction();

    // Insert into courses table (matching actual schema)
    $stmt = $db->prepare("INSERT INTO courses (course_name, course_description, difficulty_level, subject_area, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    
    // Build description from outline and standards
    $description = "Course: {$draft['course_name']}\n";
    $description .= "Grade Level: {$draft['grade']}\n";
    $description .= "Source: {$draft['source']}\n\n";
    if ($standards) {
        $description .= "Standards covered: " . count($standards) . "\n";
    }
    if ($outlineJson) {
        $description .= "\nOutline:\n" . substr($outlineJson, 0, 2000);
    }
    
    $stmt->execute([
        $draft['course_name'],
        $description,
        $difficulty,
        $draft['subject']
    ]);
    $courseId = $db->lastInsertId();

    // Update draft status to published
    $stmt = $db->prepare("UPDATE course_drafts SET status = 'published' WHERE draft_id = ?");
    $stmt->execute([$draftId]);

    $db->commit();

    echo json_encode(['success' => true, 'courseId' => $courseId, 'message' => 'Course published successfully.']);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error publishing course: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to publish course: ' . $e->getMessage()]);
}
