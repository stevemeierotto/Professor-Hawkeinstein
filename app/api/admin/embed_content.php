<?php
/**
 * Course Factory Embed Content API (proxy mode)
 * RAG handled by agent_service. PHP only validates input and forwards the job.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';

setCORSHeaders();

$admin = requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_embed_content');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['content_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: content_id']);
    exit;
}

$contentId = (int)$input['content_id'];
$forceRegenerate = !empty($input['force']);
$agentId = isset($input['agent_id']) ? (int)$input['agent_id'] : null;

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT content_id, title, has_embeddings FROM educational_content WHERE content_id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();

    if (!$content) {
        http_response_code(404);
        echo json_encode(['error' => 'Content not found']);
        exit;
    }

    if (!$forceRegenerate && !empty($content['has_embeddings'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Content already indexed. RAG handled by agent_service.',
            'content_id' => $contentId,
            'regenerated' => false
        ]);
        exit;
    }

    $payload = [
        'contentId' => $contentId,
        'force' => $forceRegenerate,
        'requestedBy' => $admin['user_id'] ?? null,
        'source' => 'course_factory/api/admin/embed_content.php'
    ];

    if ($agentId) {
        $payload['agentId'] = $agentId;
    }

    if (isset($input['metadata']) && is_array($input['metadata'])) {
        $payload['metadata'] = $input['metadata'];
    }

    error_log("[RAG] Proxying Course Factory embed request for content {$contentId} to agent_service");
    $agentResponse = callAgentService('/rag/index', $payload);

    if (empty($agentResponse['success'])) {
        error_log("[RAG] Agent service failed to index content {$contentId} (Course Factory)");
        http_response_code(502);
        echo json_encode([
            'error' => 'Agent service indexing failed',
            'details' => $agentResponse['message'] ?? 'Unknown error'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'RAG handled by agent_service',
        'content_id' => $contentId,
        'agent_service' => $agentResponse
    ]);
} catch (Exception $e) {
    error_log("Course Factory embedding proxy error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to proxy embedding request',
        'details' => $e->getMessage()
    ]);
}
