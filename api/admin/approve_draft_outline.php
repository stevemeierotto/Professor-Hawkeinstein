<?php
/**
 * Approve Draft Outline API
 * 
 * Changes draft status to 'approved' allowing content generation.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['draftId'])) {
    echo json_encode(['success' => false, 'message' => 'Draft ID required']);
    exit;
}

$draftId = (int)$input['draftId'];

try {
    $db = getDb();
    
    // First check if draft exists and get current status
    $stmt = $db->prepare("
        SELECT status, course_name 
        FROM course_drafts 
        WHERE draft_id = ?
    ");
    $stmt->execute([$draftId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$draft) {
        echo json_encode([
            'success' => false,
            'message' => 'Draft not found'
        ]);
        exit;
    }
    
    if ($draft['status'] === 'approved') {
        echo json_encode([
            'success' => true,
            'message' => 'Outline already approved',
            'already_approved' => true
        ]);
        exit;
    }
    
    // Update draft status to approved
    $stmt = $db->prepare("
        UPDATE course_drafts 
        SET status = 'approved'
        WHERE draft_id = ?
    ");
    
    $stmt->execute([$draftId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Outline approved successfully',
        'course_name' => $draft['course_name']
    ]);
    
} catch (Exception $e) {
    error_log("[Approve Draft Outline] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error approving outline'
    ]);
}
