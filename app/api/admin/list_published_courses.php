<?php
// List published courses from database
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_list_published_courses');

header('Content-Type: application/json');

$db = getDB();

// Get all active/published courses from database
$stmt = $db->prepare("SELECT course_id, course_name, course_description, difficulty_level, subject_area, created_at FROM courses WHERE is_active = 1 ORDER BY created_at DESC");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'courses' => $courses]);
