<?php
/**
 * Get Student's Advisor Instance API
 * Retrieves the student's unique advisor instance with isolated data
 * Each student has exactly one advisor with their own conversation history
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

setCORSHeaders();

$student = requireAuth();
$studentId = $student['userId'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    
    // Get student's unique advisor instance with agent template details
    $stmt = $db->prepare("
        SELECT 
            sa.advisor_instance_id,
            sa.student_id,
            sa.advisor_type_id,
            sa.created_at,
            sa.last_interaction,
            sa.is_active,
            sa.conversation_history,
            sa.progress_notes,
            sa.testing_results,
            sa.strengths_areas,
            sa.growth_areas,
            sa.custom_system_prompt,
            a.agent_id,
            a.agent_name,
            a.agent_type,
            a.system_prompt,
            a.temperature,
            a.model_name,
            a.specialization
        FROM student_advisors sa
        JOIN agents a ON sa.advisor_type_id = a.agent_id
        WHERE sa.student_id = ? AND sa.is_active = 1
        LIMIT 1
    ");
    
    $stmt->execute([$studentId]);
    $advisor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$advisor) {
        // Student has no advisor assigned yet
        http_response_code(404);
        echo json_encode([
            'error' => 'No advisor assigned',
            'message' => 'You do not have a student advisor assigned yet. Please contact administration.'
        ]);
        exit;
    }
    
    // Parse JSON fields if present
    if ($advisor['conversation_history']) {
        $advisor['conversation_history'] = json_decode($advisor['conversation_history'], true);
    } else {
        $advisor['conversation_history'] = [];
    }
    
    if ($advisor['testing_results']) {
        $advisor['testing_results'] = json_decode($advisor['testing_results'], true);
    } else {
        $advisor['testing_results'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'advisor_instance' => $advisor
    ]);
    
} catch (Exception $e) {
    error_log("Get advisor error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve advisor: ' . $e->getMessage()]);
}

?>
