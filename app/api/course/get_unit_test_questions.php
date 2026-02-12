
<?php
/**
 * Get Unit Test Questions (All lessons in a unit)
 * 
 * Returns questions from all 3 bags across all lessons in a unit
 * 
 * GET /api/course/get_unit_test_questions.php?courseId=2nd_grade_science&unitIndex=0
 */

require_once '../../config/database.php';

// Require authentication (never trust client userId)
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
$userData = requireAuth();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('course_get_unit_test_questions');



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$unitIndex = isset($_GET['unitIndex']) ? (int)$_GET['unitIndex'] : null;

if (!$courseId || $unitIndex === null) {
    echo json_encode(['success' => false, 'message' => 'courseId and unitIndex required']);
    exit;
}

$db = getDb();

try {
    // Use helper function to resolve course IDs
    $courseIds = resolveCourseIds($courseId);
    
    if (!$courseIds) {
        echo json_encode([
            'success' => false, 
            'message' => 'Course not found',
            'courseId' => $courseId
        ]);
        exit;
    }
    
    $draftId = $courseIds['draft_id'];
    
    // Get all lessons in this unit and their question banks
    $stmt = $db->prepare("
        SELECT 
            bank_id,
            lesson_index,
            question_type,
            questions,
            question_count
        FROM lesson_question_banks
        WHERE draft_id = ? AND unit_index = ?
        ORDER BY lesson_index, question_type
    ");
    $stmt->execute([$draftId, $unitIndex]);
    $questionBanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questionBanks)) {
        echo json_encode([
            'success' => false,
            'message' => 'No questions found for this unit',
            'unitIndex' => $unitIndex
        ]);
        exit;
    }
    
    // Aggregate questions by type across all lessons
    $allQuestionsByType = [
        'fill_in_blank' => [],
        'multiple_choice' => [],
        'short_essay' => []
    ];
    
    foreach ($questionBanks as $bank) {
        $type = $bank['question_type'];
        $lessonIndex = $bank['lesson_index'];
        
        if (isset($allQuestionsByType[$type]) && $bank['questions']) {
            $parsedQuestions = json_decode($bank['questions'], true);
            if ($parsedQuestions && is_array($parsedQuestions)) {
                // Add lesson context to each question
                foreach ($parsedQuestions as $question) {
                    $question['lessonIndex'] = $lessonIndex;
                    $question['lessonNumber'] = $lessonIndex + 1;
                    $allQuestionsByType[$type][] = $question;
                }
            }
        }
    }
    
    // Count unique lessons
    $uniqueLessons = array_unique(array_column($questionBanks, 'lesson_index'));
    $lessonCount = count($uniqueLessons);
    
    echo json_encode([
        'success' => true,
        'courseId' => $courseId,
        'unitIndex' => $unitIndex,
        'lessonCount' => $lessonCount,
        'questions' => $allQuestionsByType,
        'questionCounts' => [
            'fill_in_blank' => count($allQuestionsByType['fill_in_blank']),
            'multiple_choice' => count($allQuestionsByType['multiple_choice']),
            'short_essay' => count($allQuestionsByType['short_essay']),
            'total' => count($allQuestionsByType['fill_in_blank']) + 
                      count($allQuestionsByType['multiple_choice']) + 
                      count($allQuestionsByType['short_essay'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching unit test questions: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
