<?php
// Get course outline for a draft
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_get_draft_outline');

header('Content-Type: application/json');

$draftId = isset($_GET['draftId']) ? intval($_GET['draftId']) : 0;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId parameter']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT outline_json FROM course_outlines WHERE draft_id = ? ORDER BY generated_at DESC LIMIT 1");
$stmt->execute([$draftId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result && $result['outline_json']) {
    echo json_encode(['success' => true, 'outline' => $result['outline_json']]);
} else {
    echo json_encode(['success' => false, 'message' => 'No outline found']);
}
