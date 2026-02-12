<?php
/**
 * Update Student Advisor Instance Data API
 * Stores conversation turns, progress notes, and test results
 * Each update is appended to the student's advisor-specific data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

setCORSHeaders();

$student = requireAuth();
$studentId = $student['userId'];

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('student_update_advisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

try {
    $db = getDB();
    
    // Verify student has an advisor instance
    $advisorStmt = $db->prepare("SELECT advisor_instance_id FROM student_advisors WHERE student_id = ? AND is_active = 1");
    $advisorStmt->execute([$studentId]);
    $advisorInstance = $advisorStmt->fetch();
    
    if (!$advisorInstance) {
        http_response_code(404);
        echo json_encode(['error' => 'No advisor instance found for student']);
        exit;
    }
    
    $instanceId = $advisorInstance['advisor_instance_id'];
    
    // Prepare update fields
    $updates = [];
    $params = [];
    
    // Add conversation turn if provided
    if (isset($input['conversation_turn']) && is_array($input['conversation_turn'])) {
        $conversation = $input['conversation_turn'];
        
        // Get existing conversation history
        $historyStmt = $db->prepare("SELECT conversation_history FROM student_advisors WHERE advisor_instance_id = ?");
        $historyStmt->execute([$instanceId]);
        $result = $historyStmt->fetch();
        
        $history = [];
        if ($result['conversation_history']) {
            $history = json_decode($result['conversation_history'], true) ?: [];
        }
        
        // Add new conversation turn
        $history[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'role' => $conversation['role'] ?? 'student',
            'message' => $conversation['message'] ?? '',
            'metadata' => $conversation['metadata'] ?? null
        ];
        
        $updates[] = "conversation_history = ?";
        $params[] = json_encode($history);
    }
    
    // Update progress notes if provided
    if (isset($input['progress_notes'])) {
        $updates[] = "progress_notes = ?";
        $params[] = $input['progress_notes'];
    }
    
    // Add test result if provided
    if (isset($input['test_result']) && is_array($input['test_result'])) {
        $testResult = $input['test_result'];
        
        // Get existing test results
        $resultsStmt = $db->prepare("SELECT testing_results FROM student_advisors WHERE advisor_instance_id = ?");
        $resultsStmt->execute([$instanceId]);
        $result = $resultsStmt->fetch();
        
        $results = [];
        if ($result['testing_results']) {
            $results = json_decode($result['testing_results'], true) ?: [];
        }
        
        // Add new test result
        $results[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'test_name' => $testResult['test_name'] ?? '',
            'course_id' => $testResult['course_id'] ?? null,
            'score' => $testResult['score'] ?? null,
            'max_score' => $testResult['max_score'] ?? null,
            'percentage' => isset($testResult['score'], $testResult['max_score']) 
                ? round(($testResult['score'] / $testResult['max_score']) * 100, 2)
                : null,
            'feedback' => $testResult['feedback'] ?? null
        ];
        
        $updates[] = "testing_results = ?";
        $params[] = json_encode($results);
    }
    
    // Update strengths if provided
    if (isset($input['strengths_areas'])) {
        $updates[] = "strengths_areas = ?";
        $params[] = $input['strengths_areas'];
    }
    
    // Update growth areas if provided
    if (isset($input['growth_areas'])) {
        $updates[] = "growth_areas = ?";
        $params[] = $input['growth_areas'];
    }
    
    // Always update last_interaction timestamp
    $updates[] = "last_interaction = NOW()";
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No data provided to update']);
        exit;
    }
    
    // Add instance ID to params
    $params[] = $instanceId;
    
    // Execute update
    $updateQuery = "UPDATE student_advisors SET " . implode(", ", $updates) . " WHERE advisor_instance_id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute($params);
    
    // Fetch updated advisor data
    $fetchStmt = $db->prepare("
        SELECT 
            sa.advisor_instance_id,
            sa.last_interaction,
            sa.conversation_history,
            sa.progress_notes,
            sa.testing_results,
            sa.strengths_areas,
            sa.growth_areas
        FROM student_advisors sa
        WHERE sa.advisor_instance_id = ?
    ");
    $fetchStmt->execute([$instanceId]);
    $updatedData = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    if ($updatedData['conversation_history']) {
        $updatedData['conversation_history'] = json_decode($updatedData['conversation_history'], true);
    }
    if ($updatedData['testing_results']) {
        $updatedData['testing_results'] = json_decode($updatedData['testing_results'], true);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Advisor instance data updated',
        'advisor_data' => $updatedData
    ]);
    
} catch (Exception $e) {
    error_log("Update advisor data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update advisor data: ' . $e->getMessage()]);
}

?>
