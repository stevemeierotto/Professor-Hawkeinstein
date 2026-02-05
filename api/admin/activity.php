<?php
/**
 * Admin Activity Log API
 * Returns recent admin actions
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

$db = getDB();

// Get limit from query parameter
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = max(1, min(100, $limit)); // Clamp between 1 and 100

// Check if activity log table exists
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'admin_activity_log'");
    
    if (!$tableCheck->fetch()) {
        // Table doesn't exist yet, return empty array
        echo json_encode([]);
        exit;
    }
    
    // Get recent activity
    $stmt = $db->prepare("
        SELECT 
            a.id as activity_id,
            a.action,
            a.details,
            a.created_at as timestamp,
            COALESCE(u.name, 'System') as user_name,
            COALESCE(u.username, 'system') as username
        FROM admin_activity_log a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    
    $activities = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = [
            'activity_id' => $row['activity_id'],
            'action' => $row['action'],
            'details' => $row['details'],
            'timestamp' => $row['timestamp'],
            'user_name' => $row['user_name']
        ];
    }
    
    echo json_encode($activities);
} catch (PDOException $e) {
    // Table doesn't exist or has different schema - return empty
    echo json_encode([]);
}
