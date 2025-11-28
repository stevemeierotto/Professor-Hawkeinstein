<?php
/**
 * List Scraped Content API
 * Returns list of scraped content with filters
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

$db = getDB();

// Get query parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$limit = max(1, min(100, $limit));

$status = $_GET['status'] ?? null;
$gradeLevel = $_GET['grade_level'] ?? null;
$subjectArea = $_GET['subject'] ?? null;

// Build query
$sql = "
    SELECT 
        content_id, url, title,
        credibility_score, scraped_at, review_status,
        grade_level, subject,
        SUBSTRING(content_text, 1, 500) as content_preview
    FROM scraped_content
    WHERE 1=1
";

$params = [];

if ($status) {
    $sql .= " AND review_status = ?";
    $params[] = $status;
}

if ($gradeLevel) {
    $sql .= " AND grade_level = ?";
    $params[] = $gradeLevel;
}

if ($subjectArea) {
    $sql .= " AND subject = ?";
    $params[] = $subjectArea;
}

$sql .= " ORDER BY scraped_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$content = [];
while ($row = $stmt->fetch()) {
    $content[] = $row;
}

echo json_encode($content);
