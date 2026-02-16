
<?php
/**
 * Get Lesson Content for Workbook (Student Access)
 * 
 * Returns lesson content and questions for a specific lesson
 * 
 * GET /api/course/get_lesson_content.php?courseId=2nd_grade_science&unitIndex=0&lessonIndex=0
 */

require_once '../../config/database.php';

// Require authentication (never trust client userId)
require_once __DIR__ . '/../helpers/auth_helpers.php';
$userData = requireAuth();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('course_get_lesson_content');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$unitIndex = isset($_GET['unitIndex']) ? (int)$_GET['unitIndex'] : null;
$lessonIndex = isset($_GET['lessonIndex']) ? (int)$_GET['lessonIndex'] : null;

if (!$courseId || $unitIndex === null || $lessonIndex === null) {
    echo json_encode(['success' => false, 'message' => 'courseId, unitIndex, and lessonIndex required']);
    exit;
}

$db = getDB();

try {
    // First, try to get from published course (courses → units → lessons)
    // Try by numeric course_id first
    $stmt = $db->prepare("
        SELECT 
            l.lesson_id,
            l.lesson_title as title,
            l.lesson_content as content_text,
            l.lesson_content as content_html,
            l.video_url
        FROM courses c
        JOIN units u ON c.course_id = u.course_id
        JOIN lessons l ON u.unit_id = l.unit_id
        WHERE c.course_id = ? AND u.unit_number = ? AND l.lesson_number = ?
        LIMIT 1
    ");
    $stmt->execute([$courseId, $unitIndex + 1, $lessonIndex + 1]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found by numeric ID, try by course_name pattern
    if (!$content) {
        // Convert courseId: '2nd_grade_science' → '2nd Grade Science'
        $courseName = ucwords(str_replace('_', ' ', $courseId));
        
        $stmt = $db->prepare("
            SELECT 
                l.lesson_id,
                l.lesson_title as title,
                l.lesson_content as content_text,
                l.lesson_content as content_html,
                l.video_url
            FROM courses c
            JOIN units u ON c.course_id = u.course_id
            JOIN lessons l ON u.unit_id = l.unit_id
            WHERE c.course_name LIKE ? AND u.unit_number = ? AND l.lesson_number = ?
            LIMIT 1
        ");
        $stmt->execute(['%' . $courseName . '%', $unitIndex + 1, $lessonIndex + 1]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // If not found in published courses, try draft
    if (!$content) {
        // Map courseId to draft_id (for now, hardcoded - should be in a mapping table)
        $courseIdToDraftId = [
            '2nd_grade_science' => 9
        ];
        
        $draftId = isset($courseIdToDraftId[$courseId]) ? $courseIdToDraftId[$courseId] : null;
        
        if ($draftId) {
            $stmt = $db->prepare("
                SELECT 
                    sc.content_id,
                    sc.title,
                    sc.content_text,
                    sc.content_html,
                    sc.video_url,
                    dlc.relevance_score
                FROM draft_lesson_content dlc
                JOIN educational_content sc ON dlc.content_id = sc.content_id
                WHERE dlc.draft_id = ? AND dlc.unit_index = ? AND dlc.lesson_index = ?
                ORDER BY dlc.relevance_score DESC
                LIMIT 1
            ");
            $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$content) {
        echo json_encode([
            'success' => false, 
            'message' => 'Lesson content not found',
            'hasContent' => false
        ]);
        exit;
    }
    
    // Get questions for this lesson (questions still stored in draft structure)
    // Map courseId to draft_id for question lookup
    $courseIdToDraftId = [
        '2nd_grade_science' => 9
    ];
    $draftId = isset($courseIdToDraftId[$courseId]) ? $courseIdToDraftId[$courseId] : null;
    
    $questionBanks = [];
    if ($draftId) {
        $stmt = $db->prepare("
            SELECT 
                bank_id,
                question_type,
                questions,
                question_count
            FROM lesson_question_banks
            WHERE draft_id = ? AND unit_index = ? AND lesson_index = ?
            ORDER BY question_type
        ");
        $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
        $questionBanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Group questions by type and parse JSON
    $questionsByType = [
        'fill_in_blank' => [],
        'multiple_choice' => [],
        'short_essay' => []
    ];
    
    foreach ($questionBanks as $bank) {
        $type = $bank['question_type'];
        if (isset($questionsByType[$type]) && $bank['questions']) {
            $parsedQuestions = json_decode($bank['questions'], true);
            if ($parsedQuestions && is_array($parsedQuestions)) {
                $questionsByType[$type] = $parsedQuestions;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'hasContent' => true,
        'courseId' => $courseId,
        'unitIndex' => $unitIndex,
        'lessonIndex' => $lessonIndex,
        'content' => [
            'contentId' => $content['lesson_id'] ?? $content['content_id'] ?? null,
            'title' => $content['title'],
            'text' => $content['content_text'],
            'html' => $content['content_html'],
            'videoUrl' => $content['video_url']
        ],
        'questions' => $questionsByType,
        'questionCounts' => [
            'fill_in_blank' => count($questionsByType['fill_in_blank']),
            'multiple_choice' => count($questionsByType['multiple_choice']),
            'short_essay' => count($questionsByType['short_essay']),
            'total' => count($questionsByType['fill_in_blank']) + count($questionsByType['multiple_choice']) + count($questionsByType['short_essay'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching lesson content: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
