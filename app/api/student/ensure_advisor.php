<?php
/**
 * Ensure Student Has Advisor API
 * Auto-creates advisor instance for student if they don't have one
 * Always returns the student's advisor (creates if needed)
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
    error_log("ensure_advisor.php called for student: $studentId");
    $db = getDB();
    
    // BEGIN TRANSACTION for atomic advisor creation
    // This prevents race conditions when multiple requests come in simultaneously
    $db->beginTransaction();
    
    try {
        // Check if student already has an advisor (with row lock)
        // FOR UPDATE locks the row to prevent concurrent modifications
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
                a.is_active as agent_active
            FROM student_advisors sa
            JOIN agents a ON sa.advisor_type_id = a.agent_id
            WHERE sa.student_id = ? AND sa.is_active = 1
            LIMIT 1
            FOR UPDATE
        ");
        
        error_log("Executing query for student $studentId with row lock");
        $stmt->execute([$studentId]);
        $advisor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Query result for student $studentId: " . ($advisor ? "Found advisor" : "No advisor found"));
        
        // If no advisor found, create one with Professor Hawkeinstein (agent_id = 1)
        if (!$advisor) {
            error_log("Creating new advisor for student $studentId");
            
            // Use INSERT IGNORE to handle race condition at DB level
            // UNIQUE constraint on student_id ensures only one advisor per student
            $createStmt = $db->prepare("
                INSERT IGNORE INTO student_advisors (
                    student_id,
                    advisor_type_id,
                    created_at,
                    last_interaction,
                    is_active,
                    conversation_history,
                    progress_notes,
                    testing_results
                ) VALUES (
                    ?,
                    1,
                    NOW(),
                    NOW(),
                    1,
                    '[]',
                    '',
                    '[]'
                )
            ");
            
            $createStmt->execute([$studentId]);
            
            // Check if insert was successful (rowCount > 0) or duplicate was ignored
            if ($createStmt->rowCount() > 0) {
                error_log("Advisor created, new advisor_instance_id: " . $db->lastInsertId());
            } else {
                error_log("Advisor already exists (concurrent creation detected), fetching existing");
            }
            
            // Commit transaction before reading
            $db->commit();
            
            // Now fetch the advisor (either newly created or existing from concurrent request)
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
                    a.is_active as agent_active
                FROM student_advisors sa
                JOIN agents a ON sa.advisor_type_id = a.agent_id
                WHERE sa.student_id = ? AND sa.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $advisor = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Fetched advisor after creation: " . ($advisor ? "Success" : "Failed"));
        } else {
            // Advisor already exists, commit transaction
            $db->commit();
        }
    } catch (Exception $txError) {
        // Rollback transaction on any error
        $db->rollBack();
        throw $txError;
    }
    
    if (!$advisor) {
        throw new Exception("Failed to create or retrieve advisor");
    }
    
    // Check if agent is active
    $isAdvisorActive = $advisor['agent_active'] == 1;
    
    // Parse JSON fields if present
    if ($advisor['conversation_history']) {
        $advisor['conversation_history'] = json_decode($advisor['conversation_history'], true) ?? [];
    } else {
        $advisor['conversation_history'] = [];
    }
    
    if ($advisor['testing_results']) {
        $advisor['testing_results'] = json_decode($advisor['testing_results'], true) ?? [];
    } else {
        $advisor['testing_results'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'advisor_instance' => $advisor,
        'is_active' => $isAdvisorActive,
        'message' => $isAdvisorActive ? 'Advisor is active' : 'Advisor is currently inactive'
    ]);
    
} catch (Exception $e) {
    error_log("Ensure advisor error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to ensure advisor: ' . $e->getMessage()]);
}
?>
