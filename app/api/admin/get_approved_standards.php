<?php
// Get approved standards for a course draft
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_get_approved_standards');

header('Content-Type: application/json');

$draftId = isset($_GET['draftId']) ? intval($_GET['draftId']) : 0;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId parameter']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, standard_id, standard_code, description FROM approved_standards WHERE draft_id = ? ORDER BY id");
$stmt->execute([$draftId]);
$standards = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'standards' => $standards]);
