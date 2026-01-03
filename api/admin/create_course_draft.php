<?php
// Create a new course draft from metadata (Step 1 of wizard)
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();
header('Content-Type: application/json');

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Validate required fields
$required = ['courseName', 'subject', 'grade'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

// Prepare DB
$db = getDb();
$stmt = $db->prepare("INSERT INTO course_drafts (course_name, subject, grade, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
$adminId = getAdminId(); // From session/JWT
$stmt->execute([
    $input['courseName'],
    $input['subject'],
    $input['grade'],
    $adminId
]);
$draftId = $db->lastInsertId();

// Respond with draftId for next wizard step
echo json_encode(['success' => true, 'draftId' => $draftId]);
