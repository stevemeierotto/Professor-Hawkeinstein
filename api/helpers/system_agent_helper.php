<?php
/**
 * System Agent Helper
 * 
 * Fetches system agent configuration for use in the course creation pipeline.
 * System agents have agent_type='system' and are identified by their purpose field.
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Get system agent by purpose (role)
 * 
 * @param string $purpose One of: standards, outline, content, questions, quiz, unit_test, validator
 * @return array|null Agent configuration or null if not found
 */
function getSystemAgent($purpose) {
    $db = getDb();
    
    // Map purpose to agent names (system agents are identified by name pattern)
    $namePatterns = [
        'standards' => 'Standards%',
        'outline' => 'Outline%',
        'content' => 'Content Creator%',
        'questions' => 'Question Generator%',
        'quiz' => 'Quiz%',
        'unit_test' => 'Unit Test%',
        'validator' => '%Validator%'
    ];
    
    $pattern = $namePatterns[$purpose] ?? $purpose;
    
    $stmt = $db->prepare("
        SELECT agent_id, agent_name, system_prompt, temperature, max_tokens
        FROM agents 
        WHERE agent_type = 'system' AND agent_name LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$pattern]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($agent) {
        // Ensure numeric fields are proper types
        $agent['agent_id'] = (int)$agent['agent_id'];
        $agent['temperature'] = (float)$agent['temperature'];
        $agent['max_tokens'] = (int)$agent['max_tokens'];
    }
    
    return $agent ?: null;
}

/**
 * Get system agent or fall back to defaults
 * 
 * @param string $purpose One of: standards, outline, content, questions, quiz, unit_test, validator
 * @return array Agent configuration (always returns something)
 */
function getSystemAgentOrDefault($purpose) {
    $agent = getSystemAgent($purpose);
    
    if ($agent) {
        return $agent;
    }
    
    // Fallback defaults if no system agent configured
    $defaults = [
        'standards' => [
            'agent_id' => 1,
            'agent_name' => 'Standards Analyzer (Default)',
            'system_prompt' => 'You are an educational standards expert. Analyze and select appropriate learning standards.',
            'temperature' => 0.3,
            'max_tokens' => 512,
            'purpose' => 'standards'
        ],
        'outline' => [
            'agent_id' => 1,
            'agent_name' => 'Outline Generator (Default)',
            'system_prompt' => 'You are an expert curriculum designer. Create clear, structured course outlines from educational standards.',
            'temperature' => 0.4,
            'max_tokens' => 1024,
            'purpose' => 'outline'
        ],
        'content' => [
            'agent_id' => 1,
            'agent_name' => 'Content Creator (Default)',
            'system_prompt' => 'You are an expert educational content creator. Write engaging, age-appropriate lesson content that thoroughly explains concepts with real-world examples.',
            'temperature' => 0.6,
            'max_tokens' => 2048,
            'purpose' => 'content'
        ],
        'questions' => [
            'agent_id' => 1,
            'agent_name' => 'Question Generator (Default)',
            'system_prompt' => '',
            'temperature' => 0.5,
            'max_tokens' => 1024,
            'purpose' => 'questions'
        ],
        'quiz' => [
            'agent_id' => 1,
            'agent_name' => 'Quiz Creator (Default)',
            'system_prompt' => 'You are an expert at designing educational quizzes. Create balanced assessments from question banks.',
            'temperature' => 0.4,
            'max_tokens' => 1024,
            'purpose' => 'quiz'
        ],
        'unit_test' => [
            'agent_id' => 1,
            'agent_name' => 'Unit Test Creator (Default)',
            'system_prompt' => 'You are an expert at creating comprehensive unit assessments that fairly evaluate student mastery.',
            'temperature' => 0.4,
            'max_tokens' => 2048,
            'purpose' => 'unit_test'
        ],
        'validator' => [
            'agent_id' => 1,
            'agent_name' => 'Content Validator (Default)',
            'system_prompt' => 'You are a QA specialist for educational content. Verify accuracy, age-appropriateness, and alignment with standards.',
            'temperature' => 0.3,
            'max_tokens' => 1024,
            'purpose' => 'validator'
        ]
    ];
    
    return $defaults[$purpose] ?? $defaults['content'];
}

/**
 * Call agent service with system agent settings
 * 
 * @param string $purpose System agent purpose
 * @param string $userPrompt The prompt/question to send
 * @param array $options Optional overrides (temperature, max_tokens)
 * @return array Response from agent service
 */
function callSystemAgent($purpose, $userPrompt, $options = []) {
    $agent = getSystemAgentOrDefault($purpose);
    
    // Build the full prompt with system prompt prepended
    $fullPrompt = $agent['system_prompt'] . "\n\n" . $userPrompt;
    
    // Merge agent defaults with any overrides
    $temperature = $options['temperature'] ?? $agent['temperature'];
    $maxTokens = $options['max_tokens'] ?? $agent['max_tokens'];
    
    // Call the agent service
    $response = callAgentService('/agent/chat', [
        'userId' => 0,
        'agentId' => $agent['agent_id'],
        'message' => $fullPrompt,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens
    ]);
    
    // Add agent info to response for debugging
    $response['_system_agent'] = [
        'purpose' => $purpose,
        'agent_name' => $agent['agent_name'],
        'temperature' => $temperature,
        'max_tokens' => $maxTokens
    ];
    
    return $response;
}
