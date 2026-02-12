<?php
// PHASE 5: DISABLE DEBUG ENDPOINT IN PRODUCTION
if (!getenv('DEBUG_API_ENABLED')) {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    exit;
}
/**
 * DEBUG ONLY - Assessment Grading Results Inspector
 * 
 * Retrieves the most recent quiz or unit test attempt for inspection
 * Shows: Question, Correct Answer, Student Answer, Grade
 * 
 * This is a temporary debug endpoint for verifying grading inputs/outputs
 * Works for both quizzes and unit tests
 * 
 * GET /api/progress/debug_assessment_results.php
 * GET /api/progress/debug_assessment_results.php?type=quiz|unit_test
 * Returns JSON with the most recent assessment attempt details
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();



// Simple auth - require ?debug=true in production
$debugKey = $_GET['debug'] ?? '';
if ($debugKey !== 'true') {
    http_response_code(403);
    echo json_encode(['error' => 'Debug access denied', 'message' => 'Provide ?debug=true parameter']);
    exit;
}

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('progress_debug_quiz');

try {
    $db = getDb();
    
    // Get the most recent assessment submission (quiz or unit test)
    $stmt = $db->query("
        SELECT 
            pt.progress_id,
            pt.user_id,
            pt.milestone,
            pt.metric_value as score,
            pt.notes,
            pt.metric_type,
            pt.recorded_at
        FROM progress_tracking pt
        WHERE pt.metric_type IN ('quiz_score', 'unit_test_score')
        ORDER BY pt.recorded_at DESC
        LIMIT 1
    ");
    
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt) {
        echo json_encode([
            'success' => false,
            'message' => 'No assessment attempts found',
            'note' => 'DEBUG PAGE - Take a quiz or unit test first to see results'
        ]);
        exit;
    }
    
    // Determine assessment type for display
    $assessmentType = $attempt['metric_type'] === 'unit_test_score' ? 'Unit Test' : 'Quiz';
    
    // Parse the notes JSON which contains all grading details
    $notes = json_decode($attempt['notes'], true);
    
    if (!$notes || !isset($notes['results'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid quiz data format'
        ]);
        exit;
    }
    
    // Extract individual question results
    $questions = [];
    foreach ($notes['results'] as $result) {
        $questions[] = [
            'index' => $result['question_index'],
            'question' => $result['question_text'],
            'type' => $result['question_type'],
            'correct_answer' => $result['correct_answer'] ?? '',
            'student_answer' => $result['user_answer'] ?? '',
            'grade' => $result['grade'] ?? 'Not Graded',
            'points' => $result['points'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'debug_info' => '⚠️ DEBUG PAGE - Temporary assessment grading verification tool',
        'assessment_type' => $assessmentType,
        'metric_type' => $attempt['metric_type'],
        'attempt_id' => $attempt['progress_id'],
        'user_id' => $attempt['user_id'],
        'milestone' => $attempt['milestone'],
        'score' => $attempt['metric_value'],
        'timestamp' => $attempt['recorded_at'],
        'summary' => [
            'total_questions' => $notes['total_questions'],
            'correct' => $notes['correct'],
            'partial' => $notes['partial'],
            'incorrect' => $notes['incorrect']
        ],
        'questions' => $questions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
