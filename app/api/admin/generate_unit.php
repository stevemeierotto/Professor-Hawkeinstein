<?php
/**
 * Unit Generator Mode API Endpoint
 * 
 * Generates a complete unit with 5 lessons based on educational standards.
 * Each lesson follows the lesson schema and is automatically saved to the course metadata.
 * 
 * POST /api/admin/generate_unit.php
 * 
 * Required fields:
 * - courseId: ID or filename of the course metadata file
 * - subject: Subject area (e.g., "Algebra", "Biology")
 * - level: Grade/difficulty level (e.g., "High School", "College")
 * - unitNumber: Target unit number
 * - unitTitle: Title for the unit
 * - standards: Array of educational standards for the unit
 * 
 * Optional fields:
 * - lessonCount: Number of lessons to generate (default: 5, min: 3, max: 10)
 * - difficulty: Difficulty level (default: "intermediate")
 * - createBackup: Whether to create backup before saving (default: true)
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "unitNumber": 1,
 *   "lessonsGenerated": 5,
 *   "lessons": [...],
 *   "savedLessons": [...],
 *   "failures": [...],
 *   "message": "...",
 *   "generationTime": 120.5,
 *   "backupPath": "..."
 * }
 */

require_once __DIR__ . '/../admin/auth_check.php';
$adminUser = requireAdmin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../course/CourseMetadata.php';

header('Content-Type: application/json');

// Set longer timeout for unit generation (5 minutes)
set_time_limit(300);

$startTime = microtime(true);

// Get JSON input
$input = getJSONInput();

// Validate required fields
$required = ['courseId', 'subject', 'level', 'unitNumber', 'unitTitle', 'standards'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit;
    }
}

$courseId = $input['courseId'];
$subject = $input['subject'];
$level = $input['level'];
$unitNumber = (int)$input['unitNumber'];
$unitTitle = $input['unitTitle'];
$standards = $input['standards'];
$lessonCount = isset($input['lessonCount']) ? (int)$input['lessonCount'] : 5;
$difficulty = $input['difficulty'] ?? 'intermediate';
$createBackup = $input['createBackup'] ?? true;

// Validate inputs
if ($unitNumber <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'unitNumber must be a positive integer'
    ]);
    exit;
}

if ($lessonCount < 3 || $lessonCount > 10) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'lessonCount must be between 3 and 10'
    ]);
    exit;
}

if (!is_array($standards) || empty($standards)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'standards must be a non-empty array'
    ]);
    exit;
}

// Determine course file path
$courseDir = __DIR__ . '/../course/courses/';
if (!is_dir($courseDir)) {
    mkdir($courseDir, 0755, true);
}

if (strpos($courseId, '.json') !== false) {
    $courseFile = $courseDir . basename($courseId);
} else {
    $courseFile = $courseDir . 'course_' . preg_replace('/[^a-z0-9_-]/i', '_', $courseId) . '.json';
}

// Check if course file exists
if (!file_exists($courseFile)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => "Course file not found: $courseFile",
        'courseId' => $courseId
    ]);
    exit;
}

try {
    // Create backup if requested
    $backupPath = null;
    if ($createBackup) {
        $backupDir = __DIR__ . '/../course/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $backupDir . basename($courseFile, '.json') . '_backup_' . $timestamp . '.json';
        
        if (!copy($courseFile, $backupPath)) {
            error_log("[Unit Generator] Failed to create backup: $backupPath");
            $backupPath = null;
        }
    }
    
    // Load course metadata
    $course = new CourseMetadata($courseFile);
    
    // Validate course has the target unit
    $unit = $course->getUnit($unitNumber);
    if (!$unit) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Unit $unitNumber not found in course",
            'availableUnits' => array_map(function($u) {
                return $u['unitNumber'];
            }, $course->getUnits())
        ]);
        exit;
    }
    
    $generatedLessons = [];
    $savedLessons = [];
    $failures = [];
    
    // Generate each lesson
    for ($lessonNum = 1; $lessonNum <= $lessonCount; $lessonNum++) {
        error_log("[Unit Generator] Generating lesson $lessonNum of $lessonCount for unit $unitNumber");
        
        try {
            // Build prompt for this specific lesson
            $lessonPrompt = buildUnitLessonPrompt(
                $subject,
                $level,
                $unitNumber,
                $unitTitle,
                $lessonNum,
                $lessonCount,
                $standards,
                $difficulty
            );
            
            // Call agent to generate lesson
            $agentResponse = callCourseDesignAgent($lessonPrompt);
            
            if (!$agentResponse['success']) {
                $failures[] = [
                    'lessonNumber' => $lessonNum,
                    'error' => $agentResponse['error'] ?? 'Agent call failed',
                    'stage' => 'generation'
                ];
                continue;
            }
            
            // Extract and parse lesson JSON
            $lessonObject = extractLessonJSON($agentResponse['response']);
            
            if (!$lessonObject) {
                $failures[] = [
                    'lessonNumber' => $lessonNum,
                    'error' => 'Failed to parse lesson JSON from agent response',
                    'stage' => 'parsing'
                ];
                continue;
            }
            
            // Ensure lessonNumber is set correctly
            $lessonObject['lessonNumber'] = $lessonNum;
            
            // Validate lesson schema
            $validation = validateLessonSchema($lessonObject);
            
            if (!$validation['valid']) {
                $failures[] = [
                    'lessonNumber' => $lessonNum,
                    'error' => 'Lesson validation failed',
                    'validationErrors' => $validation['errors'],
                    'stage' => 'validation'
                ];
                // Continue anyway if lesson has critical fields
                if (!isset($lessonObject['lessonTitle']) || 
                    !isset($lessonObject['objectives']) || 
                    !isset($lessonObject['explanation'])) {
                    continue; // Skip if missing critical fields
                }
            }
            
            $generatedLessons[] = $lessonObject;
            
            // Save lesson to course metadata
            $saveResult = $course->insertLesson($unitNumber, $lessonObject);
            
            if ($saveResult['success']) {
                $savedLessons[] = [
                    'lessonNumber' => $lessonNum,
                    'action' => $saveResult['action'],
                    'title' => $lessonObject['lessonTitle'] ?? "Lesson $lessonNum"
                ];
            } else {
                $failures[] = [
                    'lessonNumber' => $lessonNum,
                    'error' => $saveResult['error'] ?? 'Failed to save lesson',
                    'stage' => 'saving'
                ];
            }
            
        } catch (Exception $e) {
            error_log("[Unit Generator] Error generating lesson $lessonNum: " . $e->getMessage());
            $failures[] = [
                'lessonNumber' => $lessonNum,
                'error' => $e->getMessage(),
                'stage' => 'exception'
            ];
        }
    }
    
    // Save the updated course metadata to file
    if (!empty($savedLessons)) {
        if (!$course->saveToFile($courseFile, true)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save course metadata file after generating lessons',
                'lessonsGenerated' => count($generatedLessons),
                'lessonsSaved' => count($savedLessons)
            ]);
            exit;
        }
    }
    
    $endTime = microtime(true);
    $generationTime = round($endTime - $startTime, 2);
    
    // Log the activity
    logActivity(
        $adminUser['userId'],
        'GENERATE_UNIT',
        "Generated unit $unitNumber with " . count($savedLessons) . " lessons for course $courseId"
    );
    
    // Determine overall success
    $overallSuccess = count($savedLessons) >= ($lessonCount * 0.6); // At least 60% success
    
    $response = [
        'success' => $overallSuccess,
        'unitNumber' => $unitNumber,
        'unitTitle' => $unitTitle,
        'lessonsRequested' => $lessonCount,
        'lessonsGenerated' => count($generatedLessons),
        'lessonsSaved' => count($savedLessons),
        'lessons' => $generatedLessons,
        'savedLessons' => $savedLessons,
        'failures' => $failures,
        'generationTime' => $generationTime,
        'message' => count($savedLessons) === $lessonCount 
            ? "Successfully generated and saved all $lessonCount lessons for unit $unitNumber"
            : "Generated " . count($savedLessons) . " of $lessonCount lessons. Some failures occurred."
    ];
    
    if ($backupPath) {
        $response['backupPath'] = $backupPath;
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("[Unit Generator] Fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Build prompt for generating a specific lesson within a unit
 */
function buildUnitLessonPrompt($subject, $level, $unitNumber, $unitTitle, $lessonNumber, $totalLessons, $standards, $difficulty) {
    $standardsText = '';
    if (is_array($standards)) {
        foreach ($standards as $standard) {
            if (is_array($standard)) {
                $code = $standard['code'] ?? '';
                $desc = $standard['description'] ?? $standard['desc'] ?? '';
                $standardsText .= "- $code: $desc\n";
            } else {
                $standardsText .= "- $standard\n";
            }
        }
    }
    
    $prompt = "You are an expert curriculum designer. Generate lesson $lessonNumber of $totalLessons for a unit on $unitTitle.\n\n";
    $prompt .= "CONTEXT:\n";
    $prompt .= "- Subject: $subject\n";
    $prompt .= "- Level: $level\n";
    $prompt .= "- Unit $unitNumber: $unitTitle\n";
    $prompt .= "- This is lesson $lessonNumber out of $totalLessons in this unit\n";
    $prompt .= "- Difficulty: $difficulty\n\n";
    
    $prompt .= "EDUCATIONAL STANDARDS:\n$standardsText\n";
    
    $prompt .= "REQUIREMENTS:\n";
    $prompt .= "Generate a complete, detailed lesson that:\n";
    $prompt .= "1. Builds on previous lessons (if lesson > 1)\n";
    $prompt .= "2. Prepares students for upcoming lessons\n";
    $prompt .= "3. Aligns with the educational standards\n";
    $prompt .= "4. Includes comprehensive explanations\n";
    $prompt .= "5. Provides 2-3 worked examples\n";
    $prompt .= "6. Includes 4-6 practice problems\n";
    $prompt .= "7. Has 4-6 assessment questions\n\n";
    
    $prompt .= "OUTPUT FORMAT (JSON):\n";
    $prompt .= "Return ONLY a JSON object with these exact fields:\n";
    $prompt .= "{\n";
    $prompt .= '  "lessonNumber": ' . $lessonNumber . ",\n";
    $prompt .= '  "lessonTitle": "Clear, specific title for this lesson",\n';
    $prompt .= '  "objectives": ["objective 1", "objective 2", "objective 3"],\n';
    $prompt .= '  "explanation": "Comprehensive HTML explanation with examples",\n';
    $prompt .= '  "guidedExamples": [{"title":"...", "problem":"...", "steps":["..."], "solution":"..."}],\n';
    $prompt .= '  "practiceProblems": [{"problem":"..."}],\n';
    $prompt .= '  "quizQuestions": [{"question":"...", "type":"multiple-choice", "options":["A","B","C","D"], "correctAnswer":"A"}],\n';
    $prompt .= '  "videoPlaceholder": "Suggested video title",\n';
    $prompt .= '  "summary": "Key takeaways from this lesson",\n';
    $prompt .= '  "vocabulary": [{"term":"...", "definition":"..."}],\n';
    $prompt .= '  "estimatedDuration": 45,\n';
    $prompt .= '  "difficulty": "' . $difficulty . '"\n';
    $prompt .= "}\n\n";
    $prompt .= "Generate the lesson now:";
    
    return $prompt;
}

/**
 * Call Course Design Agent via agent service
 */
function callCourseDesignAgent($prompt) {
    global $db;
    
    // Find Course Design Agent or fallback to Hawkeinstein
    $stmt = $db->prepare("
        SELECT agent_id, model_name 
        FROM agents 
        WHERE (agent_name LIKE '%Course Design%' OR agent_type LIKE '%course%' OR agent_name LIKE '%Hawkeinstein%')
        AND is_active = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        return [
            'success' => false,
            'error' => 'No suitable agent found for lesson generation'
        ];
    }
    
    $agentServiceUrl = AGENT_SERVICE_URL . '/agent/chat';
    
    $payload = [
        'userId' => 1, // System user for generation
        'agentId' => $agent['agent_id'],
        'message' => $prompt
    ];
    
    $ch = curl_init($agentServiceUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout per lesson
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        return [
            'success' => false,
            'error' => "Agent service error: $error"
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "Agent service returned HTTP $httpCode"
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['response'])) {
        return [
            'success' => false,
            'error' => 'Invalid response from agent service'
        ];
    }
    
    return [
        'success' => true,
        'response' => $data['response']
    ];
}

/**
 * Extract lesson JSON from agent response
 */
function extractLessonJSON($response) {
    // Try to find JSON in markdown code block
    if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
        $json = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
        $json = $matches[1];
    } else {
        // Try to find JSON object directly
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $json = $matches[0];
        } else {
            error_log("[Unit Generator] Could not find JSON in response: " . substr($response, 0, 200));
            return null;
        }
    }
    
    $lesson = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[Unit Generator] JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $lesson;
}

/**
 * Validate lesson against schema requirements
 */
function validateLessonSchema($lesson) {
    $errors = [];
    
    // Required fields
    $required = [
        'lessonNumber',
        'lessonTitle',
        'objectives',
        'explanation',
        'guidedExamples',
        'practiceProblems',
        'quizQuestions',
        'videoPlaceholder',
        'summary'
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
    } elseif (isset($lesson['objectives']) && empty($lesson['objectives'])) {
        $errors[] = "objectives array cannot be empty";
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
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
?>
