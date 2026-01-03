<?php
// Delete a course draft (and cascade to standards/outlines)
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['draftId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId.']);
    exit;
}

$draftId = intval($input['draftId']);
$db = getDb();

// Cascading delete handled by FK constraints
$stmt = $db->prepare("DELETE FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true, 'message' => 'Draft deleted.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Draft not found or already deleted.']);
}
