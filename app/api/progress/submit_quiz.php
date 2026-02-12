<?php
/**
 * Quiz Grading API Endpoint
 * Grades quiz submissions with auto-grading for MC/FIB and AI agent for short answer
 */

// HARD-LOCKED PATHS - No relative path math
define('APP_ROOT', '/var/www/html/basic_educational');
define('API_ROOT', APP_ROOT . '/api');
define('LOG_PATH', '/tmp/quiz_debug.log');

require_once APP_ROOT . '/config/database.php';

setCORSHeaders();

require_once APP_ROOT . '/api/helpers/rate_limiter.php';
require_rate_limit_auto('progress_submit_quiz');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require authentication
$userData = requireAuth();

$input = getJSONInput();

$courseId = $input['courseId'] ?? null;
$unitIndex = $input['unitIndex'] ?? null;
$lessonIndex = $input['lessonIndex'] ?? null;
$isUnitTest = $input['isUnitTest'] ?? false;
$questions = $input['questions'] ?? [];
$userAnswers = $input['userAnswers'] ?? [];

// Debug logging
error_log("Quiz Submit DEBUG: courseId=$courseId, unitIndex=$unitIndex, lessonIndex=$lessonIndex, isUnitTest=$isUnitTest, questions=" . count($questions) . ", answers=" . count($userAnswers));

// For unit tests, lessonIndex can be null
if (empty($courseId) || $unitIndex === null || (!$isUnitTest && $lessonIndex === null)) {
    error_log("Quiz Submit FAIL: Validation failed - courseId=" . ($courseId ?: 'NULL') . ", unitIndex=" . ($unitIndex ?? 'NULL') . ", lessonIndex=" . ($lessonIndex ?? 'NULL') . ", isUnitTest=" . ($isUnitTest ? 'true' : 'false'));
    sendJSON(['success' => false, 'message' => 'Course and lesson information required', 'debug' => ['courseId' => $courseId, 'unitIndex' => $unitIndex, 'lessonIndex' => $lessonIndex, 'isUnitTest' => $isUnitTest]], 400);
}

if (empty($questions) || empty($userAnswers)) {
    error_log("Quiz Submit FAIL: Empty questions or answers - questions=" . count($questions) . ", answers=" . count($userAnswers));
    sendJSON(['success' => false, 'message' => 'Questions and answers required', 'debug' => ['questionCount' => count($questions), 'answerCount' => count($userAnswers)]], 400);
}

try {
    $db = getDB();
    
    // Use new helper function to resolve course IDs
    $courseIds = resolveCourseIds($courseId);
    
    if (!$courseIds) {
        sendJSON(['success' => false, 'message' => 'Course not found', 'courseId' => $courseId], 404);
    }
    
    $courseIdNum = $courseIds['course_id'];
    $draftId = $courseIds['draft_id'];
    
    // Get grading agent by purpose (updated to match new schema)
    $agentStmt = $db->prepare("SELECT agent_id, agent_name FROM agents WHERE purpose = 'grading' AND is_active = 1 LIMIT 1");
    $agentStmt->execute();
    $gradingAgent = $agentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gradingAgent) {
        sendJSON(['success' => false, 'message' => 'Grading agent not available'], 500);
    }
    
    $gradingAgentId = $gradingAgent['agent_id'];
    
    // Grade each question
    $results = [];
    $correctCount = 0;
    $partialCount = 0;
    $totalQuestions = count($questions);
    
    foreach ($questions as $index => $question) {
        $userAnswer = $userAnswers[$index] ?? '';
        $correctAnswer = $question['correct_answer'] ?? '';
        
        // Determine question type
        $answerOptions = $question['options'] ?? $question['choices'] ?? null;
        $questionType = null;
        
        if ($answerOptions) {
            $questionType = 'multiple_choice';
        } elseif (isset($question['hint'])) {
            $questionType = 'fill_in_blank';
        } else {
            $questionType = 'short_essay';
        }
        
        $result = [
            'question_index' => $index,
            'question_text' => $question['question'] ?? '',
            'question_type' => $questionType,
            'user_answer' => $userAnswer,
            'correct_answer' => $correctAnswer,
            'grade' => 'Incorrect',
            'points' => 0
        ];
        
        // Auto-grade multiple choice and fill in the blank
        if ($questionType === 'multiple_choice') {
            // Case-insensitive exact match
            if (strcasecmp(trim($userAnswer), trim($correctAnswer)) === 0) {
                $result['grade'] = 'Correct';
                $result['points'] = 1;
                $correctCount++;
            }
        } elseif ($questionType === 'fill_in_blank') {
            // Case-insensitive exact match (could be enhanced with fuzzy matching)
            if (strcasecmp(trim($userAnswer), trim($correctAnswer)) === 0) {
                $result['grade'] = 'Correct';
                $result['points'] = 1;
                $correctCount++;
            }
        } elseif ($questionType === 'short_essay') {
            // Use grading agent for short answer evaluation
            try {
                error_log("Calling grading agent for short essay question $index");
                $questionText = $question['question'] ?? '';
                $gradeResult = gradeShortAnswer($questionText, $correctAnswer, $userAnswer, $gradingAgentId);
                $result['grade'] = $gradeResult['grade'];
                $result['points'] = $gradeResult['points'];
                
                if ($gradeResult['grade'] === 'Correct') {
                    $correctCount++;
                } elseif ($gradeResult['grade'] === 'Partially Correct') {
                    $partialCount++;
                }
                error_log("Grading agent returned: {$gradeResult['grade']} ({$gradeResult['points']} points)");
            } catch (Exception $e) {
                error_log("Grading agent failed for question $index: " . $e->getMessage());
                // Fallback: mark as incorrect if agent fails
                $result['grade'] = 'Not Graded (Agent Error)';
                $result['points'] = 0;
                $result['error'] = $e->getMessage();
            }
        }
        
        $results[] = $result;
    }
    
    // Calculate final score
    $score = 0;
    if ($totalQuestions > 0) {
        $totalPoints = array_sum(array_column($results, 'points'));
        $score = round(($totalPoints / $totalQuestions) * 100, 2);
    }
    
    // Save to progress_tracking
    $milestone = "Unit {$unitIndex} - Lesson {$lessonIndex} Quiz";
    $notes = json_encode([
        'total_questions' => $totalQuestions,
        'correct' => $correctCount,
        'partial' => $partialCount,
        'incorrect' => $totalQuestions - $correctCount - $partialCount,
        'results' => $results,
        'submitted_at' => date('Y-m-d H:i:s')
    ]);
    
    // Use bindParam with explicit types to ensure proper FK matching
    $progressStmt = $db->prepare("
        INSERT INTO progress_tracking 
        (user_id, course_id, agent_id, metric_type, metric_value, milestone, notes)
        VALUES 
        (:userId, :courseId, :agentId, 'quiz_score', :score, :milestone, :notes)
    ");
    
    $progressStmt->bindParam(':userId', $userData['userId'], PDO::PARAM_INT);
    $progressStmt->bindParam(':courseId', $courseIdNum, PDO::PARAM_INT);
    $progressStmt->bindParam(':agentId', $gradingAgentId, PDO::PARAM_INT);
    $progressStmt->bindParam(':score', $score, PDO::PARAM_STR);
    $progressStmt->bindParam(':milestone', $milestone, PDO::PARAM_STR);
    $progressStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
    
    // Verify FK references exist (for debug_info if INSERT fails)
    $userCheck = $db->query("SELECT COUNT(*) FROM users WHERE user_id = {$userData['userId']}")->fetchColumn();
    $courseCheck = $db->query("SELECT COUNT(*) FROM courses WHERE course_id = $courseIdNum")->fetchColumn();
    $agentCheck = $db->query("SELECT COUNT(*) FROM agents WHERE agent_id = $gradingAgentId")->fetchColumn();
    $currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
    $connectionId = $db->query("SELECT CONNECTION_ID()")->fetchColumn();
    
    // Execute the INSERT statement
    if (!$progressStmt->execute()) {
        $errorInfo = $progressStmt->errorInfo();
        error_log("Failed to save progress: " . print_r($errorInfo, true));
        error_log("Debug info: courseId=$courseIdNum, userId={$userData['userId']}, agentId=$gradingAgentId");
        
        // Return results even if save fails
        sendJSON([
            'success' => true,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'correct' => $correctCount,
            'partial' => $partialCount,
            'incorrect' => $totalQuestions - $correctCount - $partialCount,
            'results' => $results,
            'warning' => 'Results not saved to database: ' . $errorInfo[2]
        ]);
    }
    
    error_log("Quiz results saved successfully to progress_tracking (progress_id: {$db->lastInsertId()})");
    
    sendJSON([
        'success' => true,
        'score' => $score,
        'total_questions' => $totalQuestions,
        'correct' => $correctCount,
        'partial' => $partialCount,
        'incorrect' => $totalQuestions - $correctCount - $partialCount,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log("Quiz grading error: " . $e->getMessage());
    
    // Use pre-computed FK checks (from before INSERT attempt)
    $dbCheck = [
        'current_database' => $currentDb ?? 'N/A',
        'connection_id' => $connectionId ?? 'N/A',
        'user_exists' => $userCheck ?? 'N/A',
        'course_exists' => $courseCheck ?? 'N/A',
        'agent_exists' => $agentCheck ?? 'N/A'
    ];
    
    $debugInfo = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'userId' => $userData['userId'] ?? 'N/A',
        'courseIdNum' => $courseIdNum ?? 'N/A',
        'gradingAgentId' => $gradingAgentId ?? 'N/A',
        'draftId' => $draftId ?? 'N/A',
        'courseId_input' => $courseId ?? 'N/A',
        'database_check' => $dbCheck
    ];
    
    sendJSON([
        'success' => false, 
        'message' => 'Grading failed: ' . $e->getMessage(),
        'debug_info' => $debugInfo
    ], 500);
}

/**
 * Grade short answer using AI agent
 * @param string $questionText The question being asked
 * @param string $correctAnswer The expected correct answer
 * @param string $userAnswer The student's submitted answer
 * @param int $agentId The grading agent ID
 * @return array ['grade' => string, 'points' => float]
 */
function gradeShortAnswer($questionText, $correctAnswer, $userAnswer, $agentId) {
    error_log("gradeShortAnswer called with agentId=$agentId");
    
    // Build structured prompt for grading agent
    $prompt = "QUESTION: {$questionText}\n\nCORRECT ANSWER: {$correctAnswer}\n\nSTUDENT ANSWER: {$userAnswer}\n\nGrade this answer.";
    
    // DIAGNOSTIC LOGGING: Log the exact prompt being sent to the model
    $diagnosticLog = "\n===== GRADING PROMPT START =====\n";
    $diagnosticLog .= "QUESTION: {$questionText}\n\n";
    $diagnosticLog .= "CORRECT ANSWER: {$correctAnswer}\n\n";
    $diagnosticLog .= "STUDENT ANSWER: {$userAnswer}\n\n";
    $diagnosticLog .= "FULL PROMPT:\n{$prompt}\n";
    $diagnosticLog .= "===== GRADING PROMPT END =====\n";
    error_log($diagnosticLog);
    file_put_contents('/tmp/last_grading_prompt.txt', $diagnosticLog);
    
    // Call agent service with correct signature: callAgentService($endpoint, $data)
    $agentResponse = callAgentService('/agent/chat', [
        'userId' => 1, // System user for grading
        'agentId' => $agentId,
        'message' => $prompt,
        'context' => []
    ]);
    
    error_log("gradeShortAnswer agent response: " . json_encode($agentResponse));
    
    // Check if agent call failed
    if (!$agentResponse || !isset($agentResponse['success']) || $agentResponse['success'] === false) {
        $errorMsg = isset($agentResponse['message']) ? $agentResponse['message'] : 'Agent service unavailable';
        error_log("gradeShortAnswer: Agent call failed - $errorMsg");
        throw new Exception("Grading agent failed: $errorMsg");
    }
    
    if (empty($agentResponse['response'])) {
        error_log("gradeShortAnswer: Empty response from agent");
        throw new Exception("Grading agent returned empty response");
    }
    
    $responseText = trim($agentResponse['response']);
    error_log("gradeShortAnswer: Agent evaluated as: $responseText");
    
    // Parse agent response (expecting: Correct, Partially Correct, or Incorrect)
    if (stripos($responseText, 'Correct') !== false) {
        if (stripos($responseText, 'Partially') !== false) {
            return ['grade' => 'Partially Correct', 'points' => 0.5];
        }
        return ['grade' => 'Correct', 'points' => 1];
    }
    
    return ['grade' => 'Incorrect', 'points' => 0];
}
