<?php
/**
 * Approve Lesson Content API
 * 
 * POST /api/admin/approve_lesson_content.php
 * {
 *   "draftId": 3,
 *   "unitIndex": 0,
 *   "lessonIndex": 0,
 *   "approved": true
 * }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_approve_lesson_content');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$draftId = (int)($input['draftId'] ?? 0);
$unitIndex = (int)($input['unitIndex'] ?? 0);
$lessonIndex = (int)($input['lessonIndex'] ?? 0);
$approved = (bool)($input['approved'] ?? true);

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'draftId required']);
    exit;
}

$db = getDb();

try {
    // Update the approval status in draft_lesson_content
    $stmt = $db->prepare("
        UPDATE draft_lesson_content 
        SET approved = ?,
            approved_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END,
            approved_by = CASE WHEN ? = 1 THEN ? ELSE NULL END
        WHERE draft_id = ? AND unit_index = ? AND lesson_index = ?
    ");
    
    $adminId = getAdminId();
    $approvedInt = $approved ? 1 : 0;
    
    $stmt->execute([
        $approvedInt,
        $approvedInt,
        $approvedInt,
        $adminId,
        $draftId,
        $unitIndex,
        $lessonIndex
    ]);
    
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => $approved ? 'Content approved' : 'Approval removed',
        'draftId' => $draftId,
        'unitIndex' => $unitIndex,
        'lessonIndex' => $lessonIndex,
        'approved' => $approved,
        'rowsAffected' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
