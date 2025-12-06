<?php
// Get course outline for a draft
require_once '../../config/database.php';
require_once 'auth_check.php';
requireAdmin();
header('Content-Type: application/json');

$draftId = isset($_GET['draftId']) ? intval($_GET['draftId']) : 0;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId parameter']);
    exit;
}

$db = getDb();
$stmt = $db->prepare("SELECT outline_json FROM course_outlines WHERE draft_id = ? ORDER BY generated_at DESC LIMIT 1");
$stmt->execute([$draftId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result && $result['outline_json']) {
    echo json_encode(['success' => true, 'outline' => $result['outline_json']]);
} else {
    echo json_encode(['success' => false, 'message' => 'No outline found']);
}
