<?php
/**
 * Get Lesson Content for Workbook (Student Access)
 * 
 * Returns lesson content and questions for a specific lesson
 * 
 * GET /api/course/get_lesson_content.php?courseId=2nd_grade_science&unitIndex=0&lessonIndex=0
 */

require_once '../../config/database.php';

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

// Map courseId to draft_id (for now, hardcoded - should be in a mapping table)
$courseIdToDraftId = [
    '2nd_grade_science' => 9
];

$draftId = isset($courseIdToDraftId[$courseId]) ? $courseIdToDraftId[$courseId] : null;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'Course not found']);
    exit;
}

$db = getDb();

try {
    // Get lesson content from scraped_content
    $stmt = $db->prepare("
        SELECT 
            sc.content_id,
            sc.title,
            sc.content_text,
            sc.content_html,
            sc.video_url,
            dlc.relevance_score
        FROM draft_lesson_content dlc
        JOIN scraped_content sc ON dlc.content_id = sc.content_id
        WHERE dlc.draft_id = ? AND dlc.unit_index = ? AND dlc.lesson_index = ?
        ORDER BY dlc.relevance_score DESC
        LIMIT 1
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        echo json_encode([
            'success' => false, 
            'message' => 'Lesson content not found',
            'hasContent' => false
        ]);
        exit;
    }
    
    // Get questions for this lesson
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
