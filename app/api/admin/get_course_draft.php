<?php
// Get a specific course draft by ID
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_get_course_draft');

header('Content-Type: application/json');

$draftId = isset($_GET['draftId']) ? intval($_GET['draftId']) : 0;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId parameter']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if ($draft) {
    echo json_encode(['success' => true, 'draft' => $draft]);
} else {
    echo json_encode(['success' => false, 'message' => 'Draft not found']);
}
