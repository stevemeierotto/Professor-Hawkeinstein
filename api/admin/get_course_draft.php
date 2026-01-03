<?php
// Get a specific course draft by ID
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();
header('Content-Type: application/json');

$draftId = isset($_GET['draftId']) ? intval($_GET['draftId']) : 0;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId parameter']);
    exit;
}

$db = getDb();
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if ($draft) {
    echo json_encode(['success' => true, 'draft' => $draft]);
} else {
    echo json_encode(['success' => false, 'message' => 'Draft not found']);
}
