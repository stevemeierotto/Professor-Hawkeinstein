<?php
/**
 * Admin Activity Log API
 * Returns recent admin actions
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

$db = getDB();

// Get limit from query parameter
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = max(1, min(100, $limit)); // Clamp between 1 and 100

// Check if activity log table exists
$tableCheck = $db->query("SHOW TABLES LIKE 'admin_activity_log'");

if (!$tableCheck->fetch()) {
    // Table doesn't exist yet, return empty array
    echo json_encode([]);
    exit;
}

// Get recent activity
$stmt = $db->prepare("
    SELECT 
        a.activity_id,
        a.action,
        a.details,
        a.metadata,
        a.created_at as timestamp,
        u.name as user_name,
        u.username
    FROM admin_activity_log a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT ?
");

$stmt->execute([$limit]);

$activities = [];
while ($row = $stmt->fetch()) {
    $activities[] = [
        'activity_id' => $row['activity_id'],
        'action' => $row['action'],
        'details' => $row['details'],
        'metadata' => json_decode($row['metadata'], true),
        'timestamp' => $row['timestamp'],
        'user_name' => $row['user_name'],
        'username' => $row['username']
    ];
}

echo json_encode($activities);
