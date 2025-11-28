<?php
/**
 * Create Student Advisor Instance API
 * Creates a unique advisor instance for each student
 * Each student gets their own advisor with isolated data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

setCORSHeaders();

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

if (!isset($input['student_id']) || !isset($input['advisor_type_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: student_id, advisor_type_id']);
    exit;
}

$studentId = intval($input['student_id']);
$advisorTypeId = intval($input['advisor_type_id']);

try {
    $db = getDB();
    
    // BEGIN TRANSACTION for atomic advisor assignment
    // Ensures data consistency and prevents race conditions
    $db->beginTransaction();
    
    try {
        // Verify advisor type exists and is a student advisor
        $agentStmt = $db->prepare("SELECT agent_id, agent_name, system_prompt FROM agents WHERE agent_id = ? AND is_student_advisor = 1");
        $agentStmt->execute([$advisorTypeId]);
        $agent = $agentStmt->fetch();
        
        if (!$agent) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Agent not found or is not a student advisor type']);
            exit;
        }
        
        // Verify student exists
        $studentStmt = $db->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND role = 'student'");
        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch();
        
        if (!$student) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Student not found']);
            exit;
        }
        
        // Check if student already has an advisor instance (with row lock)
        // FOR UPDATE prevents concurrent assignments
        $checkStmt = $db->prepare("SELECT advisor_instance_id FROM student_advisors WHERE student_id = ? FOR UPDATE");
        $checkStmt->execute([$studentId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            $db->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Student already has an advisor instance. Only one advisor per student allowed.']);
            exit;
        }
        
        // Create unique advisor instance for this student
        // INSERT with transaction ensures atomicity
        // UNIQUE constraint on student_id provides additional safety
        $insertStmt = $db->prepare("
            INSERT INTO student_advisors (student_id, advisor_type_id, created_at, is_active)
            VALUES (?, ?, NOW(), 1)
        ");
        
        $insertStmt->execute([$studentId, $advisorTypeId]);
        $instanceId = $db->lastInsertId();
        
        // Commit transaction - all operations succeeded
        $db->commit();
        
        // Log action after successful commit
        logAdminAction(
            $admin['userId'],
            'STUDENT_ADVISOR_CREATED',
            "Created advisor instance for student {$student['username']} (ID: $studentId) using advisor type {$agent['agent_name']} (ID: $advisorTypeId)",
            ['student_id' => $studentId, 'advisor_type_id' => $advisorTypeId, 'instance_id' => $instanceId]
        );
    } catch (Exception $txError) {
        // Rollback transaction on any error
        $db->rollBack();
        throw $txError;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Advisor instance created for {$student['username']}",
        'advisor_instance' => [
            'advisor_instance_id' => $instanceId,
            'student_id' => $studentId,
            'advisor_type_id' => $advisorTypeId,
            'advisor_name' => $agent['agent_name'],
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Create advisor instance error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create advisor instance: ' . $e->getMessage()]);
}

?>
