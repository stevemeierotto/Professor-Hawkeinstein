<?php
/**
 * Create Agent API
 * Creates a new AI teaching agent with knowledge base
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/model_validation.php';

// Require admin authorization
$admin = requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_create_agent');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

// Validate required fields
$required = ['name', 'type', 'specialization', 'model', 'systemPrompt'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Validate model exists, fallback if necessary
    $validatedModel = validateModelOrFallback($input['model']);
    if ($validatedModel !== $input['model']) {
        error_log("[Create Agent] Model '{$input['model']}' not found, using fallback: $validatedModel");
    }
    
    // Prepare personality config
    $personalityConfig = json_encode([
        'grade_level' => $input['gradeLevel'] ?? null,
        'subject_area' => $input['subject'] ?? null,
        'teaching_style' => $input['teachingStyle'] ?? 'adaptive',
        'patience_level' => $input['patienceLevel'] ?? 'high',
        'encouragement_frequency' => $input['encouragementFrequency'] ?? 'moderate'
    ]);
    
    // Insert agent
    $stmt = $db->prepare("
        INSERT INTO agents (
            agent_name, agent_type, specialization, personality_config,
            model_name, system_prompt, temperature, max_tokens, is_active, is_student_advisor, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, NOW())
    $stmt->execute([
        $input['name'],
        $input['type'],
        $input['specialization'],
        $personalityConfig,
        $validatedModel,  // Use validated model instead of raw input
        $input['systemPrompt'],
        $temperature,
        $maxTokens,
        $isStudentAdvisor
    ]); $personalityConfig,
        $input['model'],
        $input['systemPrompt'],
        $temperature,
        $maxTokens,
        $isStudentAdvisor
    ]);
    
    $agentId = $db->lastInsertId();
    
    // Link knowledge sources to agent
    if (isset($input['knowledge_sources']) && is_array($input['knowledge_sources'])) {
        foreach ($input['knowledge_sources'] as $contentId) {
            // Get scraped content - use actual column names
            $contentStmt = $db->prepare("
                SELECT content_id, title, url, cleaned_text, content_text, 
                       grade_level, subject, credibility_score, metadata
                FROM educational_content 
                WHERE content_id = ? AND review_status = 'approved'
            ");
            $contentStmt->execute([$contentId]);
            $content = $contentStmt->fetch();
            
            if ($content) {
                // Use cleaned_text if available, otherwise content_text
                $textContent = !empty($content['cleaned_text']) ? $content['cleaned_text'] : $content['content_text'];
                
                // Insert into RAG documents for this agent
                $ragStmt = $db->prepare("
                    INSERT INTO rag_documents (
                        agent_id, document_title, document_type, content,
                        content_chunk, source_url, metadata
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Parse domain from URL
                $parsedUrl = parse_url($content['url']);
                $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
                
                $metadata = json_encode([
                    'educational_content_id' => $contentId,
                    'domain' => $domain,
                    'credibility_score' => $content['credibility_score'],
                    'grade_level' => $content['grade_level'],
                    'subject_area' => $content['subject'],
                    'added_by_agent_factory' => true
                ]);
                
                // Simple chunking - split by paragraphs or double newlines
                $chunks = array_filter(explode("\n\n", $textContent));
                $chunkText = !empty($chunks) ? $chunks[0] : substr($textContent, 0, 1000);
                
                $ragStmt->execute([
                    $agentId,
                    $content['title'] ?: 'Untitled',
                    'educational_content',
                    $textContent,
                    $chunkText,
                    $content['url'],
                    $metadata
                ]);
                
                // Content successfully added to RAG for this agent
                // No need to update educational_content - content can be used by multiple agents
            }
        }
    }
    
    $db->commit();
    
    // Log action
    logAdminAction(
        $admin['userId'],
        'AGENT_CREATED',
        "Created agent: {$input['name']} (ID: $agentId)",
        [
            'agent_id' => $agentId,
            'agent_type' => $input['type'],
            'knowledge_sources' => count($input['knowledge_sources'] ?? [])
        ]
    );
    
    echo json_encode([
        'success' => true,
        'agent_id' => $agentId,
        'agent_name' => $input['name']
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Agent creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create agent: ' . $e->getMessage()]);
}
