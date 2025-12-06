<?php
/**
 * Generate Lesson Questions API
 * 
 * Generates 3 question banks for a lesson (60 questions total):
 * - Fill-in-the-Blank: 20 questions
 * - Multiple Choice: 20 questions
 * - Short Essay: 20 questions
 * 
 * POST /api/admin/generate_lesson_questions.php
 * {
 *   "draftId": 3,
 *   "unitIndex": 0,
 *   "lessonIndex": 0,
 *   "questionType": "fill_in_blank" | "multiple_choice" | "short_essay" | "all"
 * }
 * 
 * GET /api/admin/generate_lesson_questions.php?draftId=3&unitIndex=0&lessonIndex=0
 * Returns existing question banks for the lesson
 */

require_once '../../config/database.php';
require_once 'auth_check.php';
require_once '../helpers/system_agent_helper.php';
requireAdmin();

header('Content-Type: application/json');

$db = getDb();

// Handle GET - retrieve existing questions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $draftId = isset($_GET['draftId']) ? (int)$_GET['draftId'] : 0;
    $unitIndex = isset($_GET['unitIndex']) ? (int)$_GET['unitIndex'] : null;
    $lessonIndex = isset($_GET['lessonIndex']) ? (int)$_GET['lessonIndex'] : null;
    
    if (!$draftId) {
        echo json_encode(['success' => false, 'message' => 'draftId required']);
        exit;
    }
    
    try {
        if ($unitIndex !== null && $lessonIndex !== null) {
            // Get questions for specific lesson
            $stmt = $db->prepare("
                SELECT bank_id, question_type, questions, question_count, 
                       difficulty_distribution, generated_at, approved_at
                FROM lesson_question_banks
                WHERE draft_id = ? AND unit_index = ? AND lesson_index = ?
                ORDER BY FIELD(question_type, 'fill_in_blank', 'multiple_choice', 'short_essay')
            ");
            $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
            $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($banks as &$bank) {
                $bank['questions'] = json_decode($bank['questions'], true);
                $bank['difficulty_distribution'] = json_decode($bank['difficulty_distribution'], true);
            }
            
            echo json_encode([
                'success' => true,
                'draftId' => $draftId,
                'unitIndex' => $unitIndex,
                'lessonIndex' => $lessonIndex,
                'questionBanks' => $banks,
                'totalQuestions' => array_sum(array_column($banks, 'question_count'))
            ]);
        } else {
            // Get summary for all lessons in draft
            $stmt = $db->prepare("
                SELECT unit_index, lesson_index, 
                       COUNT(*) as bank_count,
                       SUM(question_count) as total_questions,
                       MIN(approved_at IS NOT NULL) as all_approved
                FROM lesson_question_banks
                WHERE draft_id = ?
                GROUP BY unit_index, lesson_index
                ORDER BY unit_index, lesson_index
            ");
            $stmt->execute([$draftId]);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'draftId' => $draftId,
                'lessonSummary' => $summary
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle POST - generate questions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$draftId = (int)($input['draftId'] ?? 0);
$unitIndex = (int)($input['unitIndex'] ?? 0);
$lessonIndex = (int)($input['lessonIndex'] ?? 0);
$questionType = $input['questionType'] ?? 'all';
$appendMode = (bool)($input['appendMode'] ?? false);  // If true, add to existing instead of replacing
$questionCount = (int)($input['questionCount'] ?? 1);  // How many questions to generate (default 1)
$questionCount = max(1, min(10, $questionCount));  // Clamp between 1 and 10

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'draftId required']);
    exit;
}

// Get draft info
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    echo json_encode(['success' => false, 'message' => 'Draft not found']);
    exit;
}

// Get lesson content
$stmt = $db->prepare("
    SELECT sc.title, sc.content_text
    FROM draft_lesson_content dlc
    JOIN scraped_content sc ON dlc.content_id = sc.content_id
    WHERE dlc.draft_id = ? AND dlc.unit_index = ? AND dlc.lesson_index = ?
    ORDER BY dlc.relevance_score DESC
    LIMIT 1
");
$stmt->execute([$draftId, $unitIndex, $lessonIndex]);
$lessonContent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lessonContent) {
    echo json_encode(['success' => false, 'message' => 'No lesson content found. Generate lesson content first.']);
    exit;
}

$lessonTitle = $lessonContent['title'];
$lessonText = $lessonContent['content_text'];
$gradeNum = preg_replace('/[^0-9]/', '', $draft['grade']) ?: '2';

try {
    $results = [];
    $typesToGenerate = ($questionType === 'all') 
        ? ['fill_in_blank', 'multiple_choice', 'short_essay']
        : [$questionType];
    
    foreach ($typesToGenerate as $type) {
        // Get existing questions if in append mode
        $existingQuestions = [];
        if ($appendMode) {
            $stmt = $db->prepare("
                SELECT questions FROM lesson_question_banks 
                WHERE draft_id = ? AND unit_index = ? AND lesson_index = ? AND question_type = ?
            ");
            $stmt->execute([$draftId, $unitIndex, $lessonIndex, $type]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['questions']) {
                $existingQuestions = json_decode($row['questions'], true) ?: [];
            }
        }
        
        // Generate new questions
        $questionResult = generateQuestions($type, $lessonTitle, $lessonText, $gradeNum, $questionCount);
        
        if (!$questionResult['success']) {
            // Pass through debug info if available
            $results[$type] = [
                'success' => false, 
                'message' => $questionResult['message'],
                'debug' => $questionResult['debug'] ?? null,
                'raw' => $questionResult['raw'] ?? null
            ];
            continue;
        }
        
        // Combine existing and new questions if in append mode
        $allQuestions = $appendMode ? array_merge($existingQuestions, $questionResult['questions']) : $questionResult['questions'];
        
        // Dedupe again after merge
        $allQuestions = deduplicateQuestions($allQuestions);
        
        // Renumber IDs
        foreach ($allQuestions as $i => &$q) {
            $prefix = substr($type, 0, 3);
            $q['id'] = "{$prefix}_" . ($i + 1);
        }
        
        // Store in database using INSERT ON DUPLICATE KEY UPDATE
        $diffDist = json_encode(['easy' => 8, 'medium' => 8, 'hard' => 4]);
        $questionsJson = json_encode($allQuestions);
        $questionCount = count($allQuestions);
        
        $stmt = $db->prepare("
            INSERT INTO lesson_question_banks 
            (draft_id, unit_index, lesson_index, question_type, questions, question_count, difficulty_distribution)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                questions = VALUES(questions),
                question_count = VALUES(question_count),
                difficulty_distribution = VALUES(difficulty_distribution),
                generated_at = NOW()
        ");
        
        $stmt->execute([
            $draftId,
            $unitIndex,
            $lessonIndex,
            $type,
            $questionsJson,
            $questionCount,
            $diffDist
        ]);
        
        $results[$type] = [
            'success' => true,
            'bankId' => $db->lastInsertId(),
            'questionCount' => count($allQuestions),
            'newQuestions' => count($questionResult['questions'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Questions generated',
        'draftId' => $draftId,
        'unitIndex' => $unitIndex,
        'lessonIndex' => $lessonIndex,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Generate questions of a specific type using LLM
 */
function generateQuestions($type, $lessonTitle, $lessonText, $gradeNum, $count = 1) {
    $age = 5 + intval($gradeNum);
    
    // Use more lesson content for better questions
    $maxLessonLength = 4000;
    if (strlen($lessonText) > $maxLessonLength) {
        $lessonText = substr($lessonText, 0, $maxLessonLength) . '...';
    }
    
    switch ($type) {
        case 'fill_in_blank':
            $prompt = createFillInBlankPrompt($lessonTitle, $lessonText, $gradeNum, $age, $count);
            break;
        case 'multiple_choice':
            $prompt = createMultipleChoicePrompt($lessonTitle, $lessonText, $gradeNum, $age, $count);
            break;
        case 'short_essay':
            $prompt = createShortEssayPrompt($lessonTitle, $lessonText, $gradeNum, $age, $count);
            break;
        default:
            return ['success' => false, 'message' => 'Invalid question type'];
    }
    
    error_log("[Question Generation] About to call LLM for $type, prompt length: " . strlen($prompt));
    
    // Call Question Generator system agent
    $response = callSystemAgent('questions', $prompt);
    
    // Log which agent was used
    if (isset($response['_system_agent'])) {
        error_log("[Question Generation] Using agent: " . $response['_system_agent']['agent_name'] . 
                  " (temp=" . $response['_system_agent']['temperature'] . ")");
    }
    
    error_log("[Question Generation] LLM returned, response: " . (isset($response['response']) ? 'yes' : 'no'));
    
    if (empty($response['response'])) {
        return ['success' => false, 'message' => 'LLM did not generate response'];
    }
    
    // Parse the response into structured questions
    $rawResponse = $response['response'];
    error_log("[Question Generation] Raw LLM response for $type: " . substr($rawResponse, 0, 500));
    
    $questions = parseQuestionsFromResponse($type, $rawResponse);
    
    if (empty($questions)) {
        error_log("[Question Generation] Failed to parse any questions from response");
        // Include debug info about what we tried to parse
        $hasQuestion = preg_match('/QUESTION:/i', $rawResponse) ? 'yes' : 'no';
        $hasAnswer = preg_match('/ANSWER:/i', $rawResponse) ? 'yes' : 'no';
        return [
            'success' => false, 
            'message' => 'Failed to parse questions from response',
            'debug' => [
                'hasQUESTION' => $hasQuestion,
                'hasANSWER' => $hasAnswer,
                'responseLength' => strlen($rawResponse),
                'first200chars' => substr($rawResponse, 0, 200)
            ],
            'raw' => $rawResponse
        ];
    }
    
    // Deduplicate questions - remove ones with similar text
    $questions = deduplicateQuestions($questions);
    
    // Limit to requested count (or max 10)
    $maxQuestions = min($count, 10);
    $questions = array_slice($questions, 0, $maxQuestions);
    
    error_log("[Question Generation] Final count after dedup/limit: " . count($questions) . " questions (requested: $count)");
    return ['success' => true, 'questions' => $questions];
}

/**
 * Remove duplicate or very similar questions
 */
function deduplicateQuestions($questions) {
    $seen = [];
    $unique = [];
    
    foreach ($questions as $q) {
        // Normalize question text for comparison
        $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $q['question']));
        
        // Skip if question is too short (likely malformed)
        if (strlen($normalized) < 15) {
            continue;
        }
        
        // Skip if we've seen something very similar (first 30 chars match)
        $prefix = substr($normalized, 0, 30);
        if (isset($seen[$prefix])) {
            continue;
        }
        
        $seen[$prefix] = true;
        $unique[] = $q;
    }
    
    return $unique;
}

/**
 * Create prompt for fill-in-the-blank questions
 */
function createFillInBlankPrompt($lessonTitle, $lessonText, $gradeNum, $age, $count = 1) {
    $plural = $count > 1 ? 's' : '';
    return <<<PROMPT
You are writing questions for a $age year old (Grade $gradeNum). Use simple words they know.

READ THIS LESSON CONTENT:
---
$lessonText
---

Create $count fill-in-the-blank question$plural using ONLY facts from the lesson above.
Put _____ where a word is missing. Give the answer on the next line.

Example format:
The _____ is the largest planet in our solar system.
A1: Jupiter

Now write $count different question$plural based on the lesson:
PROMPT;
}

/**
 * Create prompt for multiple choice questions
 */
function createMultipleChoicePrompt($lessonTitle, $lessonText, $gradeNum, $age, $count = 1) {
    $plural = $count > 1 ? 's' : '';
    return <<<PROMPT
You are writing questions for a $age year old (Grade $gradeNum). Use simple words.

READ THIS LESSON CONTENT:
---
$lessonText
---

Create $count multiple choice question$plural using ONLY facts from the lesson above.

Example format:
Q1: What color is the sky on a clear day?
A) Red
B) Blue
C) Green
D) Yellow
ANSWER: B

Now write $count different question$plural based on the lesson:
PROMPT;
}

/**
 * Create prompt for short essay questions
 */
function createShortEssayPrompt($lessonTitle, $lessonText, $gradeNum, $age, $count = 1) {
    return <<<PROMPT
You are a teacher. Write a question for a $age year old (Grade $gradeNum).

LESSON:
$lessonText

OUTPUT FORMAT (you must use this exact format):
QUESTION: [your question here]
ANSWER: [1-2 sentence answer]

Write ONE question about the lesson. Start your response with "QUESTION:" now:
PROMPT;
}

/**
 * Parse LLM response into structured question array
 */
function parseQuestionsFromResponse($type, $response) {
    $questions = [];
    $lines = explode("\n", $response);
    
    switch ($type) {
        case 'fill_in_blank':
            $currentQ = null;
            $qNum = 0;
            foreach ($lines as $idx => $line) {
                $line = trim($line);
                // Remove markdown formatting
                $line = preg_replace('/\*\*([^*]+)\*\*/', '$1', $line);
                
                // Match "Q1:", "1.", "1:" or "1)" for question - must contain underscore(s) for fill-in-blank
                if (preg_match('/(?:Q)?(\d+)[:.)\s]\s*(.+)/i', $line, $m) && strlen($m[2]) > 10) {
                    $questionText = trim($m[2]);
                    // Check if this is a fill-in-blank (has underscores)
                    // Skip template-like text containing brackets
                    if (strpos($questionText, '_') !== false && strpos($questionText, '[') === false && strpos($questionText, ']') === false) {
                        // Save previous question if complete
                        if ($currentQ && !empty($currentQ['correct_answer'])) $questions[] = $currentQ;
                        $qNum = intval($m[1]);
                        
                        // Check if answer is on the same line (format: "1. Question _____. A1: answer")
                        $answer = '';
                        if (preg_match('/\s+A\d*[:.]\s*(.+)$/i', $questionText, $am)) {
                            $answer = trim($am[1]);
                            // Remove the answer part from the question
                            $questionText = preg_replace('/\s+A\d*[:.]\s*.+$/i', '', $questionText);
                        }
                        
                        $currentQ = [
                            'id' => 'fib_' . $qNum,
                            'question' => trim($questionText),
                            'correct_answer' => $answer,
                            'hint' => '',
                            'difficulty' => $qNum <= 2 ? 'easy' : ($qNum <= 4 ? 'medium' : 'hard')
                        ];
                    }
                } 
                // Match answer formats: "A1: answer", "Answer: answer", "A: answer", or just a single word after a question
                elseif ($currentQ && empty($currentQ['correct_answer'])) {
                    if (preg_match('/^(?:A\d*|Answer)[:.]\s*(.+)$/i', $line, $m)) {
                        $answer = trim($m[1]);
                        $answer = preg_replace('/\*\*([^*]+)\*\*/', '$1', $answer);
                        $currentQ['correct_answer'] = $answer;
                    }
                    // Also try: if line is short (1-2 words), it might be the answer
                    elseif (strlen($line) > 0 && strlen($line) < 30 && !preg_match('/^\d/', $line)) {
                        $currentQ['correct_answer'] = $line;
                    }
                }
                elseif (preg_match('/^H\d*[:.]\s*(.+)$/i', $line, $m) && $currentQ) {
                    $currentQ['hint'] = trim($m[1]);
                }
            }
            if ($currentQ && !empty($currentQ['correct_answer'])) $questions[] = $currentQ;
            break;
            
        case 'multiple_choice':
            $currentQ = null;
            $options = [];
            $qNum = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                // Match "Q1:", "1.", "1:" for question
                // Skip template-like text containing brackets
                if (preg_match('/^(?:Q)?(\d+)[:.)\s]\s*(.+)/i', $line, $m) && strlen($m[2]) > 10 && strpos($m[2], '?') !== false && strpos($m[2], '[') === false) {
                    if ($currentQ && !empty($currentQ['options'])) $questions[] = $currentQ;
                    $qNum = intval($m[1]);
                    $currentQ = [
                        'id' => 'mc_' . $qNum,
                        'question' => trim($m[2]),
                        'options' => [],
                        'correct_answer' => '',
                        'explanation' => '',
                        'difficulty' => $qNum <= 2 ? 'easy' : ($qNum <= 4 ? 'medium' : 'hard')
                    ];
                    $options = [];
                } elseif (preg_match('/^([A-D])\)\s*(.+)$/i', $line, $m) && $currentQ) {
                    // Skip template-like options
                    $optText = trim($m[2]);
                    if (strpos($optText, '[') === false) {
                        $options[strtoupper($m[1])] = $optText;
                        $currentQ['options'] = array_values($options);
                    }
                } elseif (preg_match('/^(?:ANSWER|CORRECT)[:.]\s*([A-D])/i', $line, $m) && $currentQ) {
                    $letter = strtoupper($m[1]);
                    $currentQ['correct_answer'] = $options[$letter] ?? $letter;
                } elseif (preg_match('/^(?:EXPLAIN|WHY)[:.]\s*(.+)$/i', $line, $m) && $currentQ) {
                    $currentQ['explanation'] = trim($m[1]);
                }
            }
            if ($currentQ && !empty($currentQ['options'])) $questions[] = $currentQ;
            break;
            
        case 'short_essay':
            // Simple parser for single question format:
            // QUESTION: [question text]
            // ANSWER: [answer text]
            // (may be on same line or separate lines)
            $questionText = '';
            $answerText = '';
            
            // Look for QUESTION: - stop at ANSWER: or end
            if (preg_match('/QUESTION:\s*(.+?)(?=\s*ANSWER:|$)/is', $response, $qm)) {
                $questionText = trim($qm[1]);
                // Remove brackets and markdown formatting
                $questionText = preg_replace('/^\[|\]$/', '', $questionText);
                $questionText = preg_replace('/\*\*/', '', $questionText);  // Remove **bold**
                $questionText = trim($questionText);
            }
            
            // Look for ANSWER: - stop at end or double newline
            if (preg_match('/ANSWER:\s*(.+?)(?=\n\n|$)/is', $response, $am)) {
                $answerText = trim($am[1]);
                // Remove brackets and markdown formatting
                $answerText = preg_replace('/^\[|\]$/', '', $answerText);
                $answerText = preg_replace('/\*\*/', '', $answerText);  // Remove **bold**
                $answerText = trim($answerText);
                // Limit answer length to first 2 sentences max
                if (strlen($answerText) > 300) {
                    preg_match('/^(.+?[.!?])(?:\s+.+?[.!?])?/s', $answerText, $sm);
                    $answerText = isset($sm[0]) ? trim($sm[0]) : substr($answerText, 0, 300);
                }
            }
            
            // If we found both, create the question
            if (!empty($questionText) && !empty($answerText)) {
                $questions[] = [
                    'id' => 'essay_1',
                    'question' => $questionText,
                    'suggested_answer' => $answerText,
                    'rubric' => ['keywords' => []],
                    'difficulty' => 'medium'
                ];
            }
            break;
    }
    
    return $questions;
}
