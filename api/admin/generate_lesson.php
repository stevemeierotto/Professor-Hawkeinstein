<?php
/**
 * Lesson Generator Mode API
 * Course Design Agent mode for generating complete lesson objects
 * 
 * Input: subject, level, unitNumber, lessonNumber, standards
 * Output: Full lesson object following lesson schema
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

// Validate required fields
$required = ['subject', 'level', 'unitNumber', 'lessonNumber', 'standards'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate data types
if (!is_numeric($input['unitNumber']) || $input['unitNumber'] < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'unitNumber must be a positive integer']);
    exit;
}

if (!is_numeric($input['lessonNumber']) || $input['lessonNumber'] < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'lessonNumber must be a positive integer']);
    exit;
}

if (!is_array($input['standards'])) {
    http_response_code(400);
    echo json_encode(['error' => 'standards must be an array']);
    exit;
}

// Optional inputs
$lessonTitle = $input['lessonTitle'] ?? null;
$unitTitle = $input['unitTitle'] ?? null;
$prerequisites = $input['prerequisites'] ?? [];
$duration = $input['estimatedDuration'] ?? 45;
$difficulty = $input['difficulty'] ?? 'intermediate';

try {
    // Build the prompt for Course Design Agent
    $prompt = buildLessonGeneratorPrompt(
        $input['subject'],
        $input['level'],
        $input['unitNumber'],
        $input['lessonNumber'],
        $input['standards'],
        $lessonTitle,
        $unitTitle,
        $prerequisites,
        $duration,
        $difficulty
    );
    
    // Call the agent service to generate the lesson
    $agentResponse = callCourseDesignAgent($prompt);
    
    if (!$agentResponse['success']) {
        throw new Exception($agentResponse['error'] ?? 'Failed to generate lesson');
    }
    
    // Parse the lesson JSON from agent response
    $lessonContent = extractLessonJSON($agentResponse['response']);
    
    if (!$lessonContent) {
        throw new Exception('Agent did not return valid lesson JSON');
    }
    
    // Validate the lesson against schema
    $validation = validateLessonSchema($lessonContent);
    
    if (!$validation['valid']) {
        error_log('[Lesson Generator] Validation errors: ' . json_encode($validation['errors']));
        // Return anyway but include warnings
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'lesson' => $lessonContent,
            'validation' => [
                'valid' => false,
                'warnings' => $validation['errors']
            ],
            'message' => 'Lesson generated with validation warnings'
        ]);
        exit;
    }
    
    // Log generation
    logActivity(
        $admin['userId'],
        'GENERATE_LESSON',
        "Generated lesson {$input['lessonNumber']} for {$input['subject']} - {$input['level']}"
    );
    
    // Return the generated lesson
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lesson' => $lessonContent,
        'validation' => [
            'valid' => true,
            'errors' => []
        ],
        'message' => 'Lesson generated successfully',
        'metadata' => [
            'subject' => $input['subject'],
            'level' => $input['level'],
            'unitNumber' => $input['unitNumber'],
            'lessonNumber' => $input['lessonNumber'],
            'generatedAt' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    error_log('[Lesson Generator] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate lesson: ' . $e->getMessage()
    ]);
}

/**
 * Build the prompt for the Course Design Agent
 */
function buildLessonGeneratorPrompt($subject, $level, $unitNumber, $lessonNumber, $standards, $lessonTitle, $unitTitle, $prerequisites, $duration, $difficulty) {
    $standardsText = '';
    foreach ($standards as $standard) {
        if (is_array($standard)) {
            $standardsText .= "- {$standard['code']}: {$standard['description']}\n";
        } else {
            $standardsText .= "- $standard\n";
        }
    }
    
    $prereqText = empty($prerequisites) ? 'None specified' : implode(', ', $prerequisites);
    
    $titleHint = $lessonTitle ? "\nSuggested lesson title: $lessonTitle" : '';
    $unitHint = $unitTitle ? "\nThis lesson is part of: Unit $unitNumber - $unitTitle" : "\nThis lesson is part of Unit $unitNumber";
    
    return <<<PROMPT
You are a Course Design Agent in LESSON_GENERATOR_MODE.

Generate a complete, structured lesson following the lesson schema.

**Lesson Requirements:**
Subject: $subject
Level: $level
Unit Number: $unitNumber
Lesson Number: $lessonNumber$titleHint$unitHint
Estimated Duration: $duration minutes
Difficulty: $difficulty
Prerequisites: $prereqText

**Educational Standards:**
$standardsText

**Instructions:**
Create a complete lesson with ALL of the following components:

1. **lessonTitle**: A clear, descriptive title
2. **objectives**: 3-5 specific learning objectives (what students will be able to do)
3. **explanation**: Comprehensive lesson content in markdown format explaining the concepts
4. **guidedExamples**: 2-3 step-by-step examples with:
   - problem, solution, steps (each with stepNumber and description)
   - Include work shown for each step
5. **practiceProblems**: 4-6 problems for independent practice with:
   - question, answer, hint, solution, difficulty, points
6. **quizQuestions**: 4-6 assessment questions with:
   - Mix of multiple-choice, true-false, and short-answer
   - Include options, correctAnswer, explanation
7. **videoPlaceholder**: Suggest a video topic/title
8. **summary**: Brief summary of key takeaways
9. **vocabulary**: Key terms with definitions
10. **estimatedDuration**: $duration
11. **difficulty**: $difficulty

**Output Format:**
Return ONLY valid JSON following this structure:
```json
{
  "lessonNumber": $lessonNumber,
  "lessonTitle": "...",
  "objectives": ["...", "..."],
  "explanation": "...",
  "guidedExamples": [...],
  "practiceProblems": [...],
  "quizQuestions": [...],
  "videoPlaceholder": "...",
  "summary": "...",
  "vocabulary": [...],
  "estimatedDuration": $duration,
  "difficulty": "$difficulty"
}
```

Generate the lesson now. Return ONLY the JSON, no additional text.
PROMPT;
}

/**
 * Call the Course Design Agent (or general agent) to generate lesson
 */
function callCourseDesignAgent($prompt) {
    $db = getDB();
    
    // Find Course Design Agent or fallback to any active agent
    $stmt = $db->prepare("
        SELECT agent_id, model_name, temperature, max_tokens
        FROM agents 
        WHERE (agent_name LIKE '%Course Design%' OR agent_type LIKE '%course%' OR agent_name LIKE '%Hawkeinstein%')
        AND is_active = 1
        ORDER BY agent_id ASC
        LIMIT 1
    ");
    $stmt->execute();
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        return ['success' => false, 'error' => 'No suitable agent found for lesson generation'];
    }
    
    // Call agent_service via HTTP
    $agentServiceUrl = AGENT_SERVICE_URL;
    
    $payload = json_encode([
        'userId' => 1, // Admin user
        'agentId' => $agent['agent_id'],
        'message' => $prompt
    ]);
    
    $ch = curl_init("$agentServiceUrl/agent/chat");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout for generation
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => "Agent service error: $curlError"];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "Agent service returned HTTP $httpCode"];
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        return ['success' => false, 'error' => 'Invalid response from agent service'];
    }
    
    return [
        'success' => true,
        'response' => $data['response'] ?? $data['message'] ?? ''
    ];
}

/**
 * Extract JSON from agent response (may have markdown code blocks)
 */
function extractLessonJSON($response) {
    // Try to extract JSON from markdown code block
    if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
        $json = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
        $json = $matches[1];
    } else {
        // No code block, assume entire response is JSON
        $json = $response;
    }
    
    // Try to find JSON object boundaries
    $json = trim($json);
    if (strpos($json, '{') !== 0) {
        // Find first {
        $start = strpos($json, '{');
        if ($start !== false) {
            $json = substr($json, $start);
        }
    }
    
    // Decode JSON
    $lesson = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Lesson Generator] JSON decode error: ' . json_last_error_msg());
        error_log('[Lesson Generator] JSON content: ' . substr($json, 0, 500));
        return null;
    }
    
    return $lesson;
}

/**
 * Validate lesson against schema
 */
function validateLessonSchema($lesson) {
    $errors = [];
    
    // Required fields
    $required = [
        'lessonNumber', 'lessonTitle', 'objectives', 'explanation',
        'guidedExamples', 'practiceProblems', 'quizQuestions',
        'videoPlaceholder', 'summary'
    ];
    
    foreach ($required as $field) {
        if (!isset($lesson[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }
    
    // Type validations
    if (isset($lesson['lessonNumber']) && !is_numeric($lesson['lessonNumber'])) {
        $errors[] = "lessonNumber must be numeric";
    }
    
    if (isset($lesson['objectives']) && !is_array($lesson['objectives'])) {
        $errors[] = "objectives must be an array";
    } elseif (isset($lesson['objectives']) && count($lesson['objectives']) === 0) {
        $errors[] = "objectives cannot be empty";
    }
    
    if (isset($lesson['guidedExamples']) && !is_array($lesson['guidedExamples'])) {
        $errors[] = "guidedExamples must be an array";
    }
    
    if (isset($lesson['practiceProblems']) && !is_array($lesson['practiceProblems'])) {
        $errors[] = "practiceProblems must be an array";
    }
    
    if (isset($lesson['quizQuestions']) && !is_array($lesson['quizQuestions'])) {
        $errors[] = "quizQuestions must be an array";
    }
    
    return [
        'valid' => count($errors) === 0,
        'errors' => $errors
    ];
}
?>
