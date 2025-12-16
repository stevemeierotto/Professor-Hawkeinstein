<?php
/**
 * Generate Lesson Content API
 * 
 * Uses the Content Creator Agent to generate age-appropriate educational content
 * for a specific lesson in a course draft.
 * 
 * POST /api/admin/generate_lesson_content.php
 * {
 *   "draftId": 2,
 *   "unitIndex": 0,
 *   "lessonIndex": 3,
 *   "lessonTitle": "The Founding Fathers and the Constitution",
 *   "lessonDescription": "Students will learn about the key figures who shaped the United States..."
 * }
 */

require_once '../../config/database.php';
require_once 'auth_check.php';
require_once '../helpers/system_agent_helper.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['draftId', 'unitIndex', 'lessonIndex', 'lessonTitle'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$draftId = (int)$input['draftId'];
$unitIndex = (int)$input['unitIndex'];
$lessonIndex = (int)$input['lessonIndex'];
$lessonTitle = $input['lessonTitle'];
$lessonDescription = $input['lessonDescription'] ?? $lessonTitle;

$db = getDb();

// Verify draft exists
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    echo json_encode(['success' => false, 'message' => 'Draft not found']);
    exit;
}

try {
    // Generate content using Content Creator agent
    $result = generateLessonContent($lessonTitle, $lessonDescription, $draft['grade'], $draft['subject']);
    
    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }
    
    // Check if content already exists for this lesson
    $stmt = $db->prepare("
        SELECT content_id FROM draft_lesson_content 
        WHERE draft_id = ? AND unit_index = ? AND lesson_index = ?
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
    $existingContentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Store generated content in scraped_content table
    $stmt = $db->prepare("
        INSERT INTO scraped_content 
        (url, page_title, content_type, raw_content, extracted_text, credibility_score, domain, scraped_by, review_status, grade_level, subject_area)
        VALUES (?, ?, 'ai_generated', ?, ?, ?, 'llm://generated', 1, 'approved', ?, ?)
    ");
    
    $stmt->execute([
        $result['url'],
        $result['title'],
        $result['html'],
        $result['content'],
        $result['credibility'],
        $draft['grade'],
        $draft['subject']
    ]);
    
    $contentId = $db->lastInsertId();
    
    // Delete old content links if they exist
    if (!empty($existingContentIds)) {
        $placeholders = implode(',', array_fill(0, count($existingContentIds), '?'));
        $stmt = $db->prepare("
            DELETE FROM draft_lesson_content 
            WHERE draft_id = ? AND unit_index = ? AND lesson_index = ? AND content_id IN ($placeholders)
        ");
        $params = array_merge([$draftId, $unitIndex, $lessonIndex], $existingContentIds);
        $stmt->execute($params);
    }
    
    // Link generated content to lesson
    $stmt = $db->prepare("
        INSERT INTO draft_lesson_content (draft_id, unit_index, lesson_index, content_id, relevance_score)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE content_id = VALUES(content_id), relevance_score = VALUES(relevance_score)
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex, $contentId, $result['relevance']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Content generated and linked to lesson',
        'contentId' => $contentId,
        'title' => $result['title'],
        'url' => $result['url'],
        'contentLength' => strlen($result['content']),
        'wordCount' => str_word_count($result['content'])
    ]);
    
} catch (Exception $e) {
    error_log("Generate lesson content error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Generation failed: ' . $e->getMessage()]);
}

/**
 * Generate lesson content using Content Creator Agent
 */
function generateLessonContent($lessonTitle, $lessonDescription, $grade, $subject) {
    // Extract grade number for age-appropriate language
    $gradeNum = preg_replace('/[^0-9]/', '', $grade) ?: '2';
    $age = 5 + intval($gradeNum); // Approximate age
    
    // Build the user prompt (system prompt comes from the Content Creator agent)
    $prompt = <<<PROMPT
Create educational lesson content for Grade $gradeNum students (age $age) about:
"$lessonDescription"

Requirements:
- Use simple vocabulary appropriate for Grade $gradeNum
- Write 8-10 paragraphs (about 800-1000 words total)
- Start with an engaging introduction that explains why this topic matters
- Include detailed explanations with multiple real-world examples kids can relate to
- Break down complex concepts into simple steps
- Provide key vocabulary words with simple definitions
- Cover the topic thoroughly so students fully understand the concept

Write ONLY the lesson content. Do NOT include review questions, activities, experiments, or fun facts - those will be added separately.
PROMPT;

    // Call the Content Creator system agent
    $agentResponse = callSystemAgent('content', $prompt);
    
    // Log which agent was used
    if (isset($agentResponse['_system_agent'])) {
        error_log("Content generated by: " . $agentResponse['_system_agent']['agent_name'] . 
                  " (temp=" . $agentResponse['_system_agent']['temperature'] . 
                  ", max_tokens=" . $agentResponse['_system_agent']['max_tokens'] . ")");
    }

    if (empty($agentResponse['response'])) {
        return ['success' => false, 'message' => 'Content Creator Agent did not generate content. Is the agent service running?'];
    }

    $content = trim($agentResponse['response']);
    
    // Clean up any markdown artifacts
    $content = preg_replace('/^#+\s*/m', '', $content); // Remove # headers
    
    // Remove any agent name prefixes like "Professor Hawkeinstein:" or "Content Creator:"
    $content = preg_replace('/^(Professor Hawkeinstein|Content Creator|Assistant):\s*/mi', '', $content);
    
    // Remove duplicate concluding paragraphs (sometimes LLM repeats the ending)
    $lines = explode("\n", $content);
    $uniqueLines = [];
    $lastLine = '';
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine !== $lastLine || empty($trimmedLine)) {
            $uniqueLines[] = $line;
            $lastLine = $trimmedLine;
        }
    }
    $content = implode("\n", $uniqueLines);
    
    $content = trim($content);
    
    if (strlen($content) < 50) {
        return ['success' => false, 'message' => 'Generated content too short: ' . $content];
    }

    // Create HTML version
    $html = '<div class="generated-lesson">' . nl2br(htmlspecialchars($content)) . '</div>';
    
    // Generate a clean title
    $cleanTitle = substr($lessonTitle, 0, 80);
    if (strlen($lessonTitle) > 80) $cleanTitle .= '...';
    
    return [
        'success' => true,
        'url' => 'llm://generated/' . time() . '/' . md5($lessonDescription),
        'title' => 'Lesson: ' . $cleanTitle . ' (AI Generated)',
        'content' => $content,
        'html' => $html,
        'credibility' => 0.85,
        'relevance' => 0.95
    ];
}
