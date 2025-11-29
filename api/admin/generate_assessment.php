<?php
/**
 * Assessment Generator API Endpoint
 * 
 * Generates assessments (unit tests, midterms, final exams) based on existing course lessons.
 * Pulls objectives, key concepts, and practice problems from lessons to create comprehensive assessments.
 * 
 * POST /api/admin/generate_assessment.php
 * 
 * Required fields:
 * - courseId: ID or filename of the course metadata file
 * - assessmentType: "unit_test", "midterm", or "final_exam"
 * 
 * For unit_test:
 * - unitNumber: Which unit to test (required)
 * 
 * For midterm:
 * - Optional: upToUnit (default: 3) - test units 1 through this number
 * 
 * For final_exam:
 * - Optional: upToUnit (default: all units) - test units 1 through this number
 * 
 * Optional fields:
 * - numQuestions: Total questions to generate (default: varies by type)
 * - questionTypes: Array of types ["multiple_choice", "short_answer", "problem_solving", "true_false"]
 * - difficulty: "easy", "medium", "hard", or "mixed" (default: "mixed")
 * - includeAnswerKey: Include answer key in response (default: true)
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "assessmentType": "unit_test",
 *   "unitNumber": 2,
 *   "totalQuestions": 25,
 *   "assessment": {
 *     "title": "Unit 2 Test: Linear Equations",
 *     "instructions": "...",
 *     "sections": [
 *       {
 *         "sectionTitle": "Multiple Choice",
 *         "questions": [...]
 *       }
 *     ],
 *     "totalPoints": 100,
 *     "estimatedTime": "45 minutes"
 *   },
 *   "answerKey": {...},
 *   "lessonsCovered": [...]
 * }
 */

require_once __DIR__ . '/../admin/auth_check.php';
requireAdmin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../course/CourseMetadata.php';

// Set longer timeout for assessment generation
set_time_limit(300); // 5 minutes

header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$courseId = $input['courseId'] ?? null;
$assessmentType = $input['assessmentType'] ?? null;

if (!$courseId || !$assessmentType) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: courseId and assessmentType'
    ]);
    exit;
}

// Validate assessment type
$validTypes = ['unit_test', 'midterm', 'final_exam'];
if (!in_array($assessmentType, $validTypes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid assessmentType. Must be: ' . implode(', ', $validTypes)
    ]);
    exit;
}

// Type-specific validation
$unitNumber = $input['unitNumber'] ?? null;
$upToUnit = $input['upToUnit'] ?? null;

if ($assessmentType === 'unit_test' && !$unitNumber) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'unitNumber required for unit_test'
    ]);
    exit;
}

// Optional parameters
$numQuestions = $input['numQuestions'] ?? null;
$questionTypes = $input['questionTypes'] ?? ['multiple_choice', 'short_answer', 'problem_solving', 'true_false'];
$difficulty = $input['difficulty'] ?? 'mixed';
$includeAnswerKey = $input['includeAnswerKey'] ?? true;

// Load course file
$courseDir = __DIR__ . '/../course/courses/';
if (strpos($courseId, '.json') !== false) {
    $courseFile = $courseDir . basename($courseId);
} else {
    $courseFile = $courseDir . 'course_' . preg_replace('/[^a-z0-9_-]/i', '_', $courseId) . '.json';
}

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
    $startTime = microtime(true);
    
    // Load course metadata
    $course = new CourseMetadata($courseFile);
    $units = $course->getUnits();
    
    if (empty($units)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Course has no units'
        ]);
        exit;
    }
    
    $courseName = $course->get('courseName');
    $subject = $course->get('subject');
    $level = $course->get('level');
    
    // Determine which units to assess
    $unitsToAssess = [];
    $lessonsCovered = [];
    
    switch ($assessmentType) {
        case 'unit_test':
            // Single unit test
            foreach ($units as $unit) {
                if ($unit['unitNumber'] == $unitNumber) {
                    $unitsToAssess[] = $unit;
                    break;
                }
            }
            
            if (empty($unitsToAssess)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => "Unit $unitNumber not found in course"
                ]);
                exit;
            }
            break;
            
        case 'midterm':
            // Units 1-3 (or specified upToUnit)
            $upToUnit = $upToUnit ?? 3;
            foreach ($units as $unit) {
                if ($unit['unitNumber'] <= $upToUnit) {
                    $unitsToAssess[] = $unit;
                }
            }
            break;
            
        case 'final_exam':
            // All units (or up to specified)
            if ($upToUnit) {
                foreach ($units as $unit) {
                    if ($unit['unitNumber'] <= $upToUnit) {
                        $unitsToAssess[] = $unit;
                    }
                }
            } else {
                $unitsToAssess = $units;
            }
            break;
    }
    
    // Extract objectives and key concepts from lessons
    $allObjectives = [];
    $allVocabulary = [];
    $allExamples = [];
    $unitTitles = [];
    
    foreach ($unitsToAssess as $unit) {
        $unitTitles[] = $unit['unitTitle'];
        $lessons = $unit['lessons'] ?? [];
        
        foreach ($lessons as $lesson) {
            // Skip outline-only lessons (not generated yet)
            $status = $lesson['status'] ?? 'outline';
            if ($status === 'outline' || empty($lesson['explanation'])) {
                continue;
            }
            
            $lessonsCovered[] = [
                'unit' => $unit['unitNumber'],
                'lesson' => $lesson['lessonNumber'],
                'title' => $lesson['lessonTitle']
            ];
            
            // Extract objectives
            if (!empty($lesson['objectives'])) {
                foreach ($lesson['objectives'] as $objective) {
                    $allObjectives[] = [
                        'objective' => $objective,
                        'unit' => $unit['unitNumber'],
                        'lesson' => $lesson['lessonNumber'],
                        'lessonTitle' => $lesson['lessonTitle']
                    ];
                }
            }
            
            // Extract vocabulary
            if (!empty($lesson['vocabularyTerms'])) {
                foreach ($lesson['vocabularyTerms'] as $term) {
                    $allVocabulary[] = [
                        'term' => $term['term'] ?? '',
                        'definition' => $term['definition'] ?? '',
                        'unit' => $unit['unitNumber'],
                        'lesson' => $lesson['lessonNumber']
                    ];
                }
            }
            
            // Extract example problems (for problem-solving questions)
            if (!empty($lesson['practiceProblems'])) {
                foreach ($lesson['practiceProblems'] as $problem) {
                    $allExamples[] = [
                        'problem' => $problem['problem'] ?? '',
                        'solution' => $problem['solution'] ?? '',
                        'unit' => $unit['unitNumber'],
                        'lesson' => $lesson['lessonNumber']
                    ];
                }
            }
        }
    }
    
    if (empty($lessonsCovered)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No generated lessons found to create assessment from. Generate lesson content first.'
        ]);
        exit;
    }
    
    // Set default question counts based on assessment type
    if (!$numQuestions) {
        switch ($assessmentType) {
            case 'unit_test':
                $numQuestions = 20;
                break;
            case 'midterm':
                $numQuestions = 40;
                break;
            case 'final_exam':
                $numQuestions = 60;
                break;
        }
    }
    
    // Build assessment prompt
    $assessmentTitle = buildAssessmentTitle($assessmentType, $unitNumber, $unitTitles, $courseName);
    $prompt = buildAssessmentPrompt(
        $assessmentType,
        $assessmentTitle,
        $subject,
        $level,
        $allObjectives,
        $allVocabulary,
        $allExamples,
        $numQuestions,
        $questionTypes,
        $difficulty,
        $lessonsCovered
    );
    
    // Call agent service to generate assessment
    error_log("[Assessment Generator] Generating $assessmentType for $courseName");
    error_log("[Assessment Generator] Covering " . count($lessonsCovered) . " lessons");
    
    $agentResponse = callCourseDesignAgent($prompt, 180); // 3 minute timeout
    
    if (!$agentResponse['success']) {
        throw new Exception('Agent call failed: ' . ($agentResponse['error'] ?? 'Unknown error'));
    }
    
    // Extract assessment JSON from response
    $assessmentData = extractAssessmentJSON($agentResponse['response']);
    
    // Validate assessment structure
    $validation = validateAssessmentStructure($assessmentData, $assessmentType);
    if (!$validation['valid']) {
        error_log("[Assessment Generator] Validation warnings: " . json_encode($validation['errors']));
    }
    
    // Separate answer key if requested
    $answerKey = null;
    if ($includeAnswerKey && isset($assessmentData['answerKey'])) {
        $answerKey = $assessmentData['answerKey'];
        unset($assessmentData['answerKey']);
    }
    
    $endTime = microtime(true);
    $generationTime = round($endTime - $startTime, 2);
    
    // Return response
    $response = [
        'success' => true,
        'assessmentType' => $assessmentType,
        'courseId' => $courseId,
        'courseName' => $courseName,
        'assessment' => $assessmentData,
        'totalQuestions' => count($assessmentData['questions'] ?? []),
        'lessonsCovered' => $lessonsCovered,
        'generationTime' => $generationTime
    ];
    
    if ($assessmentType === 'unit_test') {
        $response['unitNumber'] = $unitNumber;
    }
    
    if ($includeAnswerKey) {
        $response['answerKey'] = $answerKey;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("[Assessment Generator] Error: " . $e->getMessage());
    error_log("[Assessment Generator] Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Build assessment title based on type
 */
function buildAssessmentTitle($type, $unitNumber, $unitTitles, $courseName) {
    switch ($type) {
        case 'unit_test':
            $unitTitle = $unitTitles[0] ?? "Unit $unitNumber";
            return "Unit $unitNumber Test: $unitTitle";
            
        case 'midterm':
            return "Midterm Exam: $courseName";
            
        case 'final_exam':
            return "Final Exam: $courseName";
            
        default:
            return "Assessment";
    }
}

/**
 * Build comprehensive prompt for assessment generation
 */
function buildAssessmentPrompt($type, $title, $subject, $level, $objectives, $vocabulary, $examples, $numQuestions, $questionTypes, $difficulty, $lessonsCovered) {
    
    $prompt = "Generate a comprehensive $type assessment for a $level $subject course.\n\n";
    $prompt .= "ASSESSMENT TITLE: $title\n\n";
    
    // Coverage information
    $prompt .= "LESSONS COVERED:\n";
    foreach ($lessonsCovered as $lesson) {
        $prompt .= "- Unit {$lesson['unit']}, Lesson {$lesson['lesson']}: {$lesson['title']}\n";
    }
    $prompt .= "\n";
    
    // Learning objectives
    $prompt .= "LEARNING OBJECTIVES TO ASSESS:\n";
    foreach (array_slice($objectives, 0, 15) as $obj) { // Limit to avoid token overflow
        $prompt .= "- {$obj['objective']} (Unit {$obj['unit']}, Lesson {$obj['lesson']})\n";
    }
    $prompt .= "\n";
    
    // Key vocabulary
    if (!empty($vocabulary)) {
        $prompt .= "KEY VOCABULARY TERMS:\n";
        foreach (array_slice($vocabulary, 0, 20) as $term) {
            $prompt .= "- {$term['term']}: {$term['definition']}\n";
        }
        $prompt .= "\n";
    }
    
    // Assessment requirements
    $prompt .= "ASSESSMENT REQUIREMENTS:\n";
    $prompt .= "- Total Questions: $numQuestions\n";
    $prompt .= "- Question Types: " . implode(', ', $questionTypes) . "\n";
    $prompt .= "- Difficulty Level: $difficulty\n";
    $prompt .= "- All questions MUST be based on the learning objectives and lessons listed above\n";
    $prompt .= "- Include a mix of question types to assess different cognitive levels\n";
    $prompt .= "- Questions should progress from easier to more challenging\n\n";
    
    // Question distribution
    $prompt .= "SUGGESTED QUESTION DISTRIBUTION:\n";
    if (in_array('multiple_choice', $questionTypes)) {
        $mcCount = ceil($numQuestions * 0.4);
        $prompt .= "- Multiple Choice: ~$mcCount questions (40%)\n";
    }
    if (in_array('true_false', $questionTypes)) {
        $tfCount = ceil($numQuestions * 0.2);
        $prompt .= "- True/False: ~$tfCount questions (20%)\n";
    }
    if (in_array('short_answer', $questionTypes)) {
        $saCount = ceil($numQuestions * 0.2);
        $prompt .= "- Short Answer: ~$saCount questions (20%)\n";
    }
    if (in_array('problem_solving', $questionTypes)) {
        $psCount = ceil($numQuestions * 0.2);
        $prompt .= "- Problem Solving: ~$psCount questions (20%)\n";
    }
    $prompt .= "\n";
    
    // Output format
    $prompt .= "OUTPUT FORMAT (JSON):\n";
    $prompt .= "Return a valid JSON object with this structure:\n";
    $prompt .= "{\n";
    $prompt .= "  \"title\": \"$title\",\n";
    $prompt .= "  \"instructions\": \"Clear instructions for students\",\n";
    $prompt .= "  \"totalPoints\": 100,\n";
    $prompt .= "  \"estimatedTime\": \"45 minutes\",\n";
    $prompt .= "  \"questions\": [\n";
    $prompt .= "    {\n";
    $prompt .= "      \"questionNumber\": 1,\n";
    $prompt .= "      \"type\": \"multiple_choice\",\n";
    $prompt .= "      \"question\": \"Question text\",\n";
    $prompt .= "      \"options\": [\"A) Option 1\", \"B) Option 2\", \"C) Option 3\", \"D) Option 4\"],\n";
    $prompt .= "      \"points\": 2,\n";
    $prompt .= "      \"difficulty\": \"easy\",\n";
    $prompt .= "      \"objective\": \"Related learning objective\",\n";
    $prompt .= "      \"unit\": 1,\n";
    $prompt .= "      \"lesson\": 2\n";
    $prompt .= "    }\n";
    $prompt .= "  ],\n";
    $prompt .= "  \"answerKey\": {\n";
    $prompt .= "    \"1\": {\n";
    $prompt .= "      \"answer\": \"B\",\n";
    $prompt .= "      \"explanation\": \"Brief explanation why this is correct\"\n";
    $prompt .= "    }\n";
    $prompt .= "  }\n";
    $prompt .= "}\n\n";
    
    // Question type specifications
    $prompt .= "QUESTION TYPE SPECIFICATIONS:\n\n";
    
    if (in_array('multiple_choice', $questionTypes)) {
        $prompt .= "Multiple Choice:\n";
        $prompt .= "- 4 options (A, B, C, D)\n";
        $prompt .= "- Only one correct answer\n";
        $prompt .= "- Distractors should be plausible but clearly incorrect\n";
        $prompt .= "- Avoid 'all of the above' or 'none of the above'\n\n";
    }
    
    if (in_array('true_false', $questionTypes)) {
        $prompt .= "True/False:\n";
        $prompt .= "- Clear, unambiguous statements\n";
        $prompt .= "- Avoid words like 'always' or 'never' unless accurate\n";
        $prompt .= "- Balance between true and false answers\n\n";
    }
    
    if (in_array('short_answer', $questionTypes)) {
        $prompt .= "Short Answer:\n";
        $prompt .= "- Require 1-3 sentence responses\n";
        $prompt .= "- Test understanding of concepts, not just memorization\n";
        $prompt .= "- Provide clear answer criteria in answer key\n\n";
    }
    
    if (in_array('problem_solving', $questionTypes)) {
        $prompt .= "Problem Solving:\n";
        $prompt .= "- Multi-step problems requiring calculation or analysis\n";
        $prompt .= "- Similar to practice problems from lessons\n";
        $prompt .= "- Include complete solution steps in answer key\n";
        $prompt .= "- Show all work requirements\n\n";
    }
    
    $prompt .= "IMPORTANT:\n";
    $prompt .= "- Every question MUST relate to specific lessons and objectives listed above\n";
    $prompt .= "- Include unit and lesson numbers for each question to show coverage\n";
    $prompt .= "- Ensure questions test understanding, not just recall\n";
    $prompt .= "- Return ONLY the JSON object, no additional text\n";
    $prompt .= "- Make sure the JSON is valid and properly formatted\n";
    
    return $prompt;
}

/**
 * Call Course Design Agent service
 */
function callCourseDesignAgent($message, $timeout = 120) {
    $agentServiceUrl = 'http://localhost:8080/agent/chat';
    
    $payload = [
        'userId' => 1,
        'agentId' => 2, // Course Design Agent
        'message' => $message
    ];
    
    $ch = curl_init($agentServiceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
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
    
    $responseData = json_decode($response, true);
    
    if (!$responseData || !isset($responseData['response'])) {
        return [
            'success' => false,
            'error' => 'Invalid response from agent service'
        ];
    }
    
    return [
        'success' => true,
        'response' => $responseData['response']
    ];
}

/**
 * Extract assessment JSON from agent response
 */
function extractAssessmentJSON($response) {
    // Try to find JSON in markdown code blocks
    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $json = trim($matches[1]);
    } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $json = trim($matches[1]);
    } else {
        // Try to extract JSON object directly
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = $matches[0];
        } else {
            $json = $response;
        }
    }
    
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse assessment JSON: ' . json_last_error_msg());
    }
    
    return $data;
}

/**
 * Validate assessment structure
 */
function validateAssessmentStructure($assessment, $type) {
    $errors = [];
    
    // Required fields
    $required = ['title', 'instructions', 'questions'];
    foreach ($required as $field) {
        if (!isset($assessment[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }
    
    // Validate questions array
    if (isset($assessment['questions'])) {
        if (!is_array($assessment['questions'])) {
            $errors[] = "questions must be an array";
        } elseif (empty($assessment['questions'])) {
            $errors[] = "questions array is empty";
        } else {
            // Validate each question
            foreach ($assessment['questions'] as $index => $question) {
                if (!isset($question['question'])) {
                    $errors[] = "Question $index missing 'question' field";
                }
                if (!isset($question['type'])) {
                    $errors[] = "Question $index missing 'type' field";
                }
            }
        }
    }
    
    // Check for answer key
    if (!isset($assessment['answerKey'])) {
        $errors[] = "Missing answerKey (should be included for grading)";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
