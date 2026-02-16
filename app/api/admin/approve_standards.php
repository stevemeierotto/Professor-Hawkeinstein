<?php
// Approve standards for a course draft (Step 2 of wizard)
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_approve_standards');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['draftId']) || !isset($input['standards'])) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId or standards array.']);
    exit;
}

$draftId = intval($input['draftId']);
$standards = $input['standards']; // Array of {standard_id, standard_code, description}

$db = getDB();

// Remove any existing approved standards for this draft
$db->prepare("DELETE FROM approved_standards WHERE draft_id = ?")->execute([$draftId]);

// Insert approved standards
$stmt = $db->prepare("INSERT INTO approved_standards (draft_id, standard_id, standard_code, description) VALUES (?, ?, ?, ?)");
foreach ($standards as $std) {
    $stmt->execute([
        $draftId,
        $std['standard_id'] ?? null,
        $std['standard_code'] ?? null,
        $std['description'] ?? null
    ]);
}

// Update draft status
$db->prepare("UPDATE course_drafts SET status = 'outline_review' WHERE draft_id = ?")->execute([$draftId]);

echo json_encode(['success' => true, 'message' => 'Standards approved.']);
