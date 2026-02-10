<?php
/**
 * Course Factory Retrieve Context API (proxy mode)
 * RAG handled entirely by agent_service.
 */

require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['query']) || trim($input['query']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: query']);
    exit;
}

$query = $input['query'];
$agentId = isset($input['agent_id']) ? (int)$input['agent_id'] : null;
$topK = isset($input['top_k']) ? max(1, (int)$input['top_k']) : 5;
$minSimilarity = isset($input['min_similarity']) ? (float)$input['min_similarity'] : 0.25;
$minSimilarity = max(0.0, min(1.0, $minSimilarity));

foreach (['embedding', 'embedding_vector', 'vector', 'cosine'] as $forbiddenKey) {
    if (isset($input[$forbiddenKey])) {
        error_log("[RAG] Warning: Course Factory retrieve_context received forbidden field '{$forbiddenKey}'. RAG handled by agent_service.");
    }
}

$payload = [
    'query' => $query,
    'agentId' => $agentId,
    'topK' => $topK,
    'minSimilarity' => $minSimilarity,
    'source' => 'course_factory/api/agent/retrieve_context.php'
];

if (isset($input['filters']) && is_array($input['filters'])) {
    $payload['filters'] = $input['filters'];
}

error_log("[RAG] Proxying Course Factory retrieval request to agent_service.");
$agentResponse = callAgentService('/rag/search', $payload);

if (empty($agentResponse['success'])) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Agent service retrieval failed',
        'details' => $agentResponse['message'] ?? 'Unknown error'
    ]);
    exit;
}

echo json_encode($agentResponse);
