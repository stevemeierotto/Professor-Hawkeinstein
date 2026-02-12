<?php
/**
 * AI Agent Chat API Endpoint (proxy mode)
 * Authenticates the user, validates input, and forwards directly to agent_service.
 * RAG, retrieval, and prompt assembly are all handled inside the C++ service.
 */

require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../config/database.php';

error_log('[Agent Chat] Endpoint invoked');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log('[Agent Chat] Rejected non-POST request');
        sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $userData = requireAuth();
    error_log('[Agent Chat] Authenticated user ' . $userData['userId']);
    
    require_once __DIR__ . '/../helpers/rate_limiter.php';
    require_rate_limit_auto('agent_chat');

    $input = getJSONInput();

    $agentId = isset($input['agentId']) ? (int)$input['agentId'] : null;
    $message = isset($input['message']) ? trim($input['message']) : '';

    if (!$agentId || $message === '') {
        error_log('[Agent Chat] Missing agentId or message');
        sendJSON(['success' => false, 'message' => 'Agent ID and message required'], 400);
    }

    $agentRequest = [
        'agentId' => $agentId,
        'userId' => (int)$userData['userId'],
        'message' => $message
    ];

    error_log('[Agent Chat] Proxying request to agent_service');
    $agentResponse = callAgentService('/agent/chat', $agentRequest);

    if (empty($agentResponse['success'])) {
        error_log('[Agent Chat] Agent service failure: ' . ($agentResponse['message'] ?? 'unknown error'));
        sendJSON([
            'success' => false,
            'message' => $agentResponse['message'] ?? 'Agent service communication failed'
        ], 502);
    }

    logActivity($userData['userId'], 'AGENT_CHAT', "Chat with agent {$agentId}");
    error_log('[Agent Chat] Successfully logged conversation');

    sendJSON([
        'success' => true,
        'response' => $agentResponse['response'] ?? '',
        'agentId' => $agentId,
        'timestamp' => date('c')
    ]);
} catch (Throwable $e) {
    error_log('[Agent Chat] Fatal error: ' . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Chat failed'], 500);
}
