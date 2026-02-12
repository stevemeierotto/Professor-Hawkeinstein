<?php
/**
 * List Training Exports API
 * Returns list of training data exports
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_list_exports');

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        te.export_id,
        te.agent_id,
        a.agent_name,
        te.export_name,
        te.export_format,
        te.min_importance_score,
        te.total_conversations,
        te.total_messages,
        te.file_path,
        te.file_size_bytes,
        te.exported_at,
        te.is_used_for_finetuning
    FROM training_exports te
    LEFT JOIN agents a ON te.agent_id = a.agent_id
    ORDER BY te.exported_at DESC
    LIMIT 50
");

$stmt->execute();

$exports = [];
while ($row = $stmt->fetch()) {
    $exports[] = $row;
}

echo json_encode($exports);
