<?php
/**
 * Export Training Data API
 * Exports conversation history as JSONL for model fine-tuning
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

$agentId = $input['agent_id'] ?? null;
$minImportance = floatval($input['min_importance_score'] ?? 0.70);
$dateFrom = $input['date_from'] ?? null;
$dateTo = $input['date_to'] ?? null;
$exportName = $input['export_name'] ?? 'training_data_' . date('Y-m-d_His');
$format = $input['format'] ?? 'jsonl';

try {
    $db = getDB();
    
    // Build query
    $sql = "
        SELECT 
            am.memory_id,
            am.agent_id,
            a.agent_name,
            am.user_message,
            am.agent_response,
            am.context_used,
            am.importance_score,
            am.created_at,
            u.username
        FROM agent_memories am
        JOIN agents a ON am.agent_id = a.agent_id
        JOIN users u ON am.user_id = u.user_id
        WHERE am.importance_score >= ?
    ";
    
    $params = [$minImportance];
    
    if ($agentId) {
        $sql .= " AND am.agent_id = ?";
        $params[] = $agentId;
    }
    
    if ($dateFrom) {
        $sql .= " AND am.created_at >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $sql .= " AND am.created_at <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " ORDER BY am.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $conversations = [];
    while ($row = $stmt->fetch()) {
        $conversations[] = $row;
    }
    
    if (empty($conversations)) {
        http_response_code(400);
        echo json_encode(['error' => 'No conversations match the criteria']);
        exit;
    }
    
    // Format data based on export format
    $exportData = formatTrainingData($conversations, $format);
    
    // Save to file
    $exportPath = __DIR__ . '/../../media/training_exports/';
    if (!is_dir($exportPath)) {
        mkdir($exportPath, 0755, true);
    }
    
    $fileName = $exportName . '.' . $format;
    $filePath = $exportPath . $fileName;
    
    file_put_contents($filePath, $exportData);
    $fileSize = filesize($filePath);
    
    // Record export in database
    $recordStmt = $db->prepare("
        INSERT INTO training_exports (
            agent_id, export_name, export_format, min_importance_score,
            date_from, date_to, total_conversations, total_messages,
            file_path, file_size_bytes, metadata, exported_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $metadata = json_encode([
        'conversation_count' => count($conversations),
        'export_criteria' => [
            'min_importance' => $minImportance,
            'date_range' => [$dateFrom, $dateTo]
        ],
        'export_timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $totalMessages = count($conversations);
    
    $recordStmt->execute([
        $agentId,
        $exportName,
        $format,
        $minImportance,
        $dateFrom,
        $dateTo,
        count($conversations),
        $totalMessages,
        $filePath,
        $fileSize,
        $metadata,
        $admin['user_id']
    ]);
    
    $exportId = $db->lastInsertId();
    
    // Log action
    logAdminAction(
        $admin['user_id'],
        'TRAINING_DATA_EXPORTED',
        "Exported {$totalMessages} conversations for " . ($agentId ? "agent #$agentId" : "all agents"),
        ['export_id' => $exportId, 'format' => $format]
    );
    
    echo json_encode([
        'success' => true,
        'export_id' => $exportId,
        'file_name' => $fileName,
        'file_size' => $fileSize,
        'conversation_count' => count($conversations),
        'download_url' => 'media/training_exports/' . $fileName
    ]);
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to export training data: ' . $e->getMessage()]);
}

/**
 * Format conversations for model fine-tuning
 * 
 * @param array $conversations Conversation data
 * @param string $format Export format (jsonl, json, csv)
 * @return string Formatted data
 */
function formatTrainingData($conversations, $format) {
    if ($format === 'jsonl') {
        // JSONL format - one JSON object per line (llama.cpp fine-tuning format)
        $lines = [];
        foreach ($conversations as $conv) {
            $lines[] = json_encode([
                'instruction' => $conv['user_message'],
                'response' => $conv['agent_response'],
                'context' => $conv['context_used'],
                'metadata' => [
                    'agent' => $conv['agent_name'],
                    'importance' => $conv['importance_score'],
                    'timestamp' => $conv['created_at']
                ]
            ]);
        }
        return implode("\n", $lines);
    }
    
    if ($format === 'json') {
        // Standard JSON array
        $data = [];
        foreach ($conversations as $conv) {
            $data[] = [
                'instruction' => $conv['user_message'],
                'response' => $conv['agent_response'],
                'context' => $conv['context_used'],
                'metadata' => [
                    'agent' => $conv['agent_name'],
                    'importance' => $conv['importance_score'],
                    'timestamp' => $conv['created_at']
                ]
            ];
        }
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    if ($format === 'csv') {
        // CSV format
        $csv = "instruction,response,context,agent,importance,timestamp\n";
        foreach ($conversations as $conv) {
            $csv .= sprintf('"%s","%s","%s","%s",%s,"%s"' . "\n",
                str_replace('"', '""', $conv['user_message']),
                str_replace('"', '""', $conv['agent_response']),
                str_replace('"', '""', $conv['context_used']),
                $conv['agent_name'],
                $conv['importance_score'],
                $conv['created_at']
            );
        }
        return $csv;
    }
    
    return '';
}
