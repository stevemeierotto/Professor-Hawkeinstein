<?php
/**
 * Get Content Detail API
 * Returns full content details for review
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Content ID required']);
    exit;
}

$contentId = intval($_GET['id']);

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        content_id,
        url,
        title,
        content_text,
        content_html,
        content_summary,
        cleaned_text,
        subject,
        grade_level,
        credibility_score,
        review_status,
        metadata,
        scraped_at
    FROM educational_content 
    WHERE content_id = ?
");

$stmt->execute([$contentId]);
$content = $stmt->fetch();

if (!$content) {
    http_response_code(404);
    echo json_encode(['error' => 'Content not found']);
    exit;
}

echo json_encode($content);
