<?php
// List course drafts (not published yet)
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_list_course_drafts');

header('Content-Type: application/json');

$db = getDB();

// Get all drafts that are not published
$stmt = $db->prepare("SELECT draft_id, course_name, subject, grade, status, created_at FROM course_drafts WHERE status != 'published' ORDER BY created_at DESC");
$stmt->execute();
$drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'drafts' => $drafts]);
