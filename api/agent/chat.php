<?php
/**
 * AI Agent Chat API Endpoint
 * Proxies messages to C++ Agent Microservice
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require authentication for all requests
$userData = requireAuth();

$input = getJSONInput();

$agentId = $input['agentId'] ?? null;
$message = $input['message'] ?? '';
$context = $input['context'] ?? [];

if (empty($agentId) || empty($message)) {
    sendJSON(['success' => false, 'message' => 'Agent ID and message required'], 400);
}

try {
    $db = getDB();
    
    // Verify agent exists and is active
    $agentStmt = $db->prepare("SELECT * FROM agents WHERE agent_id = :agentId AND is_active = 1");
    $agentStmt->execute(['agentId' => $agentId]);
    $agent = $agentStmt->fetch();
    
    if (!$agent) {
        sendJSON(['success' => false, 'message' => 'Agent not found'], 404);
    }
    
    // Get recent conversation history for context
    $historyStmt = $db->prepare("
        SELECT user_message, agent_response
        FROM agent_memories
        WHERE agent_id = :agentId AND user_id = :userId
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $historyStmt->execute([
        'agentId' => $agentId,
        'userId' => $userData['userId']
    ]);
    $conversationHistory = $historyStmt->fetchAll();
    
    // **RAG INTEGRATION**: Retrieve relevant context from embeddings
    $ragContext = [];
    $ragContextText = '';
    try {
        // Call retrieve_context to get relevant chunks
        $retrieveContextUrl = 'http://localhost' . dirname($_SERVER['PHP_SELF']) . '/retrieve_context.php';
        $contextRequest = [
            'query' => $message,
            'agent_id' => $agentId,
            'top_k' => 3,
            'min_similarity' => 0.3
        ];
        
        $ch = curl_init($retrieveContextUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contextRequest));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $contextResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode === 200 && $contextResponse) {
            $contextData = json_decode($contextResponse, true);
            if ($contextData && $contextData['success'] && !empty($contextData['context_chunks'])) {
                $ragContext = $contextData['context_chunks'];
                
                // Build context text for injection into prompt
                $contextParts = [];
                foreach ($ragContext as $chunk) {
                    $contextParts[] = "Source: {$chunk['title']} - {$chunk['text_chunk']}";
                }
                $ragContextText = implode("\n\n", $contextParts);
                
                error_log("[RAG] Retrieved " . count($ragContext) . " context chunks for query");
            } else if ($contextData && $contextData['fallback_mode']) {
                error_log("[RAG] No embeddings available - operating without RAG context");
            }
        }
    } catch (Exception $e) {
        error_log("[RAG] Context retrieval failed: " . $e->getMessage());
        // Continue without RAG context - graceful degradation
    }
    
    // Prepare request for C++ agent microservice
    $agentRequest = [
        'agentId' => $agentId,
        'userId' => $userData['userId'],
        'message' => $message,
        'agentConfig' => [
            'model' => $agent['model_name'],
            'systemPrompt' => $agent['system_prompt'],
            'temperature' => (float)$agent['temperature'],
            'maxTokens' => (int)$agent['max_tokens']
        ],
        'conversationHistory' => array_reverse($conversationHistory),
        'context' => $context,
        'ragContext' => $ragContextText  // Inject RAG context
    ];
    
    // Call C++ agent microservice
    $agentResponse = callAgentService('/agent/chat', $agentRequest);
    
    if (!$agentResponse['success']) {
        sendJSON(['success' => false, 'message' => 'Agent communication failed'], 500);
    }
    
    // Store interaction in agent_memories
    $memoryStmt = $db->prepare("
        INSERT INTO agent_memories 
        (agent_id, user_id, interaction_type, user_message, agent_response, context_used, metadata, importance_score)
        VALUES (:agentId, :userId, :type, :userMsg, :agentResp, :context, :metadata, :importance)
    ");
    
    $memoryStmt->execute([
        'agentId' => $agentId,
        'userId' => $userData['userId'],
        'type' => 'chat',
        'userMsg' => $message,
        'agentResp' => $agentResponse['response'],
        'context' => json_encode($agentResponse['retrievedContext'] ?? []),
        'metadata' => json_encode([
            'model' => $agent['model_name'],
            'tokens_used' => $agentResponse['tokensUsed'] ?? 0
        ]),
        'importance' => $agentResponse['importanceScore'] ?? 0.5
    ]);
    
    // Update agent last_active timestamp (if column exists)
    try {
        $updateStmt = $db->prepare("UPDATE agents SET last_active = NOW() WHERE agent_id = :agentId");
        $updateStmt->execute(['agentId' => $agentId]);
        error_log("[Agent Chat] Updated last_active for agent $agentId");
    } catch (Exception $e) {
        // Column may not exist yet - non-critical
        error_log("[Agent Chat] Note: last_active update skipped (" . $e->getMessage() . ")");
    }
    
    logActivity($userData['userId'], 'AGENT_CHAT', "Chat with agent $agentId");
    
    sendJSON([
        'success' => true,
        'response' => $agentResponse['response'],
        'agentName' => $agent['agent_name'],
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    error_log("Agent chat error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Chat failed'], 500);
}
