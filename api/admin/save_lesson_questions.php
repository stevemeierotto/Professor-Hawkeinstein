<?php
/**
 * Save/Update Lesson Questions API
 * 
 * Saves a complete question bank for a lesson (replaces existing)
 * Used when editing or deleting individual questions
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$draftId = (int)($input['draftId'] ?? 0);
$unitIndex = (int)($input['unitIndex'] ?? 0);
$lessonIndex = (int)($input['lessonIndex'] ?? 0);
$questionType = $input['questionType'] ?? '';
$questions = $input['questions'] ?? [];

if (!$draftId || !$questionType) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$validTypes = ['fill_in_blank', 'multiple_choice', 'short_essay'];
if (!in_array($questionType, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid question type']);
    exit;
}

try {
    $db = getDb();
    
    // Delete existing bank for this lesson/type
    $stmt = $db->prepare("
        DELETE FROM lesson_question_banks 
        WHERE draft_id = ? AND unit_index = ? AND lesson_index = ? AND question_type = ?
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex, $questionType]);
    
    // Insert updated bank (if there are questions)
    if (!empty($questions)) {
        // Renumber question IDs
        foreach ($questions as $i => &$q) {
            $prefix = substr($questionType, 0, 3);
            $q['id'] = "{$prefix}_" . ($i + 1);
        }
        
        $stmt = $db->prepare("
            INSERT INTO lesson_question_banks 
            (draft_id, unit_index, lesson_index, question_type, questions, question_count, difficulty_distribution)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $diffDist = json_encode(['easy' => 8, 'medium' => 8, 'hard' => 4]);
        $stmt->execute([
            $draftId,
            $unitIndex,
            $lessonIndex,
            $questionType,
            json_encode($questions),
            count($questions),
            $diffDist
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Questions saved',
        'questionCount' => count($questions)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
