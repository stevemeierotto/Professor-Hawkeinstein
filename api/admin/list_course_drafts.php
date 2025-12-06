<?php
// List course drafts (not published yet)
require_once '../../config/database.php';
require_once 'auth_check.php';
requireAdmin();
header('Content-Type: application/json');

$db = getDb();

// Get all drafts that are not published
$stmt = $db->prepare("SELECT draft_id, course_name, subject, grade, status, created_at FROM course_drafts WHERE status != 'published' ORDER BY created_at DESC");
$stmt->execute();
$drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'drafts' => $drafts]);
