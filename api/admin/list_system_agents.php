<?php
/**
 * List System Agents API
 * 
 * Returns all agents with agent_type = 'system' for course creation pipeline
 */

require_once '../../config/database.php';
require_once 'auth_check.php';
requireAdmin();

header('Content-Type: application/json');

$db = getDb();

try {
    // Get all system agents (agent_type = 'system')
    $stmt = $db->prepare("
        SELECT 
            agent_id,
            agent_name,
            agent_type,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(personality_config, '$.role')), 'other') as agent_role,
            specialization,
            model_name,
            system_prompt,
            temperature,
            max_tokens,
            is_active,
            created_at
        FROM agents 
        WHERE agent_type = 'system'
        ORDER BY 
            FIELD(JSON_UNQUOTE(JSON_EXTRACT(personality_config, '$.role')), 
                  'standards', 'outline', 'content', 'questions', 'quiz', 'unit_test', 'validator', 'other'),
            agent_name
    ");
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert numeric fields
    foreach ($agents as &$agent) {
        $agent['agent_id'] = (int)$agent['agent_id'];
        $agent['temperature'] = (float)$agent['temperature'];
        $agent['max_tokens'] = (int)$agent['max_tokens'];
        $agent['is_active'] = (bool)$agent['is_active'];
    }

    echo json_encode([
        'success' => true,
        'agents' => $agents,
        'count' => count($agents)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
