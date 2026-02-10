<?php
/**
 * Full Course Generator Mode API Endpoint
 * 
 * Generates complete content for all lessons in an approved course outline.
 * Loops through units and lessons, generating and saving each sequentially.
 * 
 * POST /api/admin/generate_full_course.php
 * 
 * Required fields:
 * - courseId: ID or filename of the course metadata file
 * 
 * Optional fields:
 * - startUnit: Unit to start from (default: 1, for resume capability)
 * - startLesson: Lesson to start from within startUnit (default: 1)
 * - pauseOnFailure: Stop if a lesson fails (default: true)
 * - createBackup: Create backup before starting (default: true)
 * - difficulty: Override difficulty level (default: from outline or "intermediate")
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "courseId": "...",
 *   "totalUnits": 6,
 *   "totalLessons": 30,
 *   "processedLessons": 25,
 *   "successfulLessons": 24,
 *   "failedLessons": 1,
 *   "progress": [
 *     {
 *       "unit": 1, "lesson": 1, "status": "success",
 *       "title": "...", "time": 45.2
 *     },
 *     ...
 *   ],
 *   "failures": [...],
 *   "generationTime": 1234.5,
 *   "backupPath": "...",
 *   "completed": true/false,
 *   "message": "..."
 * }
 * 
 * Note: This is a long-running operation. Consider implementing as background job
 * with status polling for production use.
 */

require_once __DIR__ . '/../admin/auth_check.php';
$adminUser = requireAdmin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../course/CourseMetadata.php';

header('Content-Type: application/json');

// Set long timeout (30 minutes for full course)
set_time_limit(1800);

$startTime = microtime(true);

// Get JSON input
$input = getJSONInput();

// Validate required fields
if (!isset($input['courseId'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required field: courseId'
    ]);
    exit;
}

$courseId = $input['courseId'];
$startUnit = isset($input['startUnit']) ? (int)$input['startUnit'] : 1;
$startLesson = isset($input['startLesson']) ? (int)$input['startLesson'] : 1;
$pauseOnFailure = $input['pauseOnFailure'] ?? true;
$createBackup = $input['createBackup'] ?? true;
$difficulty = $input['difficulty'] ?? null;

// Validate inputs
if ($startUnit < 1) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'startUnit must be >= 1'
    ]);
    exit;
}

if ($startLesson < 1) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'startLesson must be >= 1'
    ]);
    exit;
}

// Determine course file path
$courseDir = __DIR__ . '/../course/courses/';
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
        $backupPath = $backupDir . basename($courseFile, '.json') . '_full_backup_' . $timestamp . '.json';
        
        if (!copy($courseFile, $backupPath)) {
            error_log("[Full Course Generator] Failed to create backup: $backupPath");
            $backupPath = null;
        }
    }
    
    // Load course metadata
    $course = new CourseMetadata($courseFile);
    $units = $course->getUnits();
    
    if (empty($units)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Course has no units. Create outline first.'
        ]);
        exit;
    }
    
    $courseName = $course->get('courseName');
    $subject = $course->get('subject');
    $level = $course->get('level');
    
    error_log("[Full Course Generator] Starting generation for: $courseName");
    error_log("[Full Course Generator] Starting from Unit $startUnit, Lesson $startLesson");
    
    // Calculate totals
    $totalLessons = 0;
    foreach ($units as $unit) {
        $totalLessons += count($unit['lessons'] ?? []);
    }
    
    // Progress tracking
    $progress = [];
    $failures = [];
    $processedLessons = 0;
    $successfulLessons = 0;
    $failedLessons = 0;
    $completed = false;
    $stoppedEarly = false;
    
    // Loop through units
    foreach ($units as $unit) {
        $unitNumber = $unit['unitNumber'];
        $unitTitle = $unit['unitTitle'];
        $unitStandards = $unit['standards'] ?? [];
        
        // Skip units before startUnit
        if ($unitNumber < $startUnit) {
            continue;
        }
        
        error_log("[Full Course Generator] Processing Unit $unitNumber: $unitTitle");
        
        $lessons = $unit['lessons'] ?? [];
        
        // Loop through lessons
        foreach ($lessons as $lessonIndex => $lesson) {
            $lessonNumber = $lesson['lessonNumber'];
            $lessonTitle = $lesson['lessonTitle'] ?? "Lesson $lessonNumber";
            
            // Skip lessons before startLesson in startUnit
            if ($unitNumber == $startUnit && $lessonNumber < $startLesson) {
                continue;
            }
            
            // Check if lesson already has content (status != 'outline')
            $currentStatus = $lesson['status'] ?? 'outline';
            if ($currentStatus !== 'outline' && !empty($lesson['explanation'])) {
                error_log("[Full Course Generator] Skipping Unit $unitNumber Lesson $lessonNumber (already generated)");
                $progress[] = [
                    'unit' => $unitNumber,
                    'lesson' => $lessonNumber,
                    'title' => $lessonTitle,
                    'status' => 'skipped',
                    'reason' => 'Already generated',
                    'time' => 0
                ];
                continue;
            }
            
            $lessonStartTime = microtime(true);
            
            error_log("[Full Course Generator] Generating Unit $unitNumber, Lesson $lessonNumber: $lessonTitle");
            
            try {
                // Build prompt for lesson generation
                $prerequisites = [];
                if ($lessonNumber > 1) {
                    $prerequisites[] = $lessons[$lessonIndex - 1]['lessonTitle'] ?? "Lesson " . ($lessonNumber - 1);
                }
                
                $lessonPrompt = buildFullCoursePrompt(
                    $subject,
                    $level,
                    $unitNumber,
                    $unitTitle,
                    $lessonNumber,
                    $lessonTitle,
                    $lesson['description'] ?? '',
                    $unitStandards,
                    $prerequisites,
                    $difficulty ?? $lesson['difficulty'] ?? 'intermediate'
                );
                
                // Call agent to generate lesson
                $agentResponse = callCourseDesignAgent($lessonPrompt);
                
                if (!$agentResponse['success']) {
                    throw new Exception($agentResponse['error'] ?? 'Agent call failed');
                }
                
                // Extract and parse lesson JSON
                $generatedLesson = extractLessonJSON($agentResponse['response']);
                
                if (!$generatedLesson) {
                    throw new Exception('Failed to parse lesson JSON from agent response');
                }
                
                // Ensure correct lesson number
                $generatedLesson['lessonNumber'] = $lessonNumber;
                $generatedLesson['lessonTitle'] = $generatedLesson['lessonTitle'] ?? $lessonTitle;
                
                // Validate lesson schema
                $validation = validateLessonSchema($generatedLesson);
                
                if (!$validation['valid']) {
                    // Check if it has critical fields
                    if (!isset($generatedLesson['objectives']) || 
                        !isset($generatedLesson['explanation']) ||
                        empty($generatedLesson['objectives'])) {
                        throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
                    }
                    // Continue with warnings if has critical fields
                    error_log("[Full Course Generator] Validation warnings for Unit $unitNumber Lesson $lessonNumber: " . implode(', ', $validation['errors']));
                }
                
                // Mark as generated
                $generatedLesson['status'] = 'generated';
                
                // Save lesson to course metadata
                $saveResult = $course->insertLesson($unitNumber, $generatedLesson);
                
                if (!$saveResult['success']) {
                    throw new Exception('Failed to save lesson: ' . ($saveResult['error'] ?? 'Unknown error'));
                }
                
                $lessonEndTime = microtime(true);
                $lessonTime = round($lessonEndTime - $lessonStartTime, 2);
                
                $processedLessons++;
                $successfulLessons++;
                
                $progress[] = [
                    'unit' => $unitNumber,
                    'lesson' => $lessonNumber,
                    'title' => $lessonTitle,
                    'status' => 'success',
                    'action' => $saveResult['action'],
                    'time' => $lessonTime,
                    'validationWarnings' => $validation['valid'] ? null : $validation['errors']
                ];
                
                error_log("[Full Course Generator] SUCCESS: Unit $unitNumber Lesson $lessonNumber ($lessonTime seconds)");
                
                // Periodic save to disk (every 5 lessons)
                if ($processedLessons % 5 === 0) {
                    if ($course->saveToFile($courseFile, true)) {
                        error_log("[Full Course Generator] Progress saved ($processedLessons lessons)");
                    }
                }
                
            } catch (Exception $e) {
                $lessonEndTime = microtime(true);
                $lessonTime = round($lessonEndTime - $lessonStartTime, 2);
                
                $processedLessons++;
                $failedLessons++;
                
                $failureInfo = [
                    'unit' => $unitNumber,
                    'lesson' => $lessonNumber,
                    'title' => $lessonTitle,
                    'error' => $e->getMessage(),
                    'time' => $lessonTime
                ];
                
                $failures[] = $failureInfo;
                $progress[] = array_merge($failureInfo, ['status' => 'failed']);
                
                error_log("[Full Course Generator] FAILED: Unit $unitNumber Lesson $lessonNumber - " . $e->getMessage());
                
                if ($pauseOnFailure) {
                    error_log("[Full Course Generator] Stopping due to failure (pauseOnFailure=true)");
                    $stoppedEarly = true;
                    break 2; // Break out of both loops
                }
            }
        }
    }
    
    // Final save to disk
    if (!$course->saveToFile($courseFile, true)) {
        error_log("[Full Course Generator] WARNING: Failed to save final course file");
    } else {
        error_log("[Full Course Generator] Final course file saved");
    }
    
    $endTime = microtime(true);
    $totalGenerationTime = round($endTime - $startTime, 2);
    
    // Determine if fully completed
    $completed = !$stoppedEarly && ($processedLessons >= $totalLessons || $failedLessons === 0);
    
    // Log activity
    logActivity(
        $adminUser['userId'],
        'GENERATE_FULL_COURSE',
        "Generated $successfulLessons of $totalLessons lessons for $courseName (failures: $failedLessons)"
    );
    
    // Build response
    $response = [
        'success' => $failedLessons === 0 || (!$pauseOnFailure && $successfulLessons > 0),
        'courseId' => $courseId,
        'courseName' => $courseName,
        'totalUnits' => count($units),
        'totalLessons' => $totalLessons,
        'processedLessons' => $processedLessons,
        'successfulLessons' => $successfulLessons,
        'failedLessons' => $failedLessons,
        'completed' => $completed,
        'stoppedEarly' => $stoppedEarly,
        'progress' => $progress,
        'failures' => $failures,
        'generationTime' => $totalGenerationTime,
        'averageTimePerLesson' => $processedLessons > 0 ? round($totalGenerationTime / $processedLessons, 2) : 0
    ];
    
    if ($backupPath) {
        $response['backupPath'] = $backupPath;
    }
    
    // Build message
    if ($completed && $failedLessons === 0) {
        $response['message'] = "Successfully generated all $totalLessons lessons for $courseName";
    } elseif ($stoppedEarly) {
        $response['message'] = "Generation stopped at Unit $unitNumber Lesson $lessonNumber due to failure. Resume with startUnit=$unitNumber, startLesson=$lessonNumber.";
    } elseif ($failedLessons > 0) {
        $response['message'] = "Generated $successfulLessons lessons with $failedLessons failures. Review and regenerate failed lessons.";
    } else {
        $response['message'] = "Generation completed with partial success.";
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("[Full Course Generator] Fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Build prompt for lesson generation in full course context
 */
function buildFullCoursePrompt($subject, $level, $unitNumber, $unitTitle, $lessonNumber, $lessonTitle, $lessonDescription, $standards, $prerequisites, $difficulty) {
    $standardsText = '';
    if (is_array($standards) && !empty($standards)) {
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
    
    $prerequisitesText = '';
    if (!empty($prerequisites)) {
        $prerequisitesText = "\nPREREQUISITES:\n- " . implode("\n- ", $prerequisites) . "\n";
    }
    
    $prompt = "You are an expert curriculum designer. Generate complete lesson content.\n\n";
    $prompt .= "CONTEXT:\n";
    $prompt .= "- Subject: $subject\n";
    $prompt .= "- Level: $level\n";
    $prompt .= "- Unit $unitNumber: $unitTitle\n";
    $prompt .= "- Lesson $lessonNumber: $lessonTitle\n";
    
    if ($lessonDescription) {
        $prompt .= "- Description: $lessonDescription\n";
    }
    
    $prompt .= "- Difficulty: $difficulty\n";
    
    if ($standardsText) {
        $prompt .= "\nEDUCATIONAL STANDARDS:\n$standardsText";
    }
    
    if ($prerequisitesText) {
        $prompt .= $prerequisitesText;
    }
    
    $prompt .= "\nREQUIREMENTS:\n";
    $prompt .= "Generate a comprehensive lesson with:\n";
    $prompt .= "1. 3-5 clear learning objectives\n";
    $prompt .= "2. Detailed explanation with examples\n";
    $prompt .= "3. 2-3 guided examples with step-by-step solutions\n";
    $prompt .= "4. 4-6 practice problems for independent work\n";
    $prompt .= "5. 4-6 quiz questions (multiple-choice, true-false, or short-answer)\n";
    $prompt .= "6. Key vocabulary terms with definitions\n";
    $prompt .= "7. Summary of key concepts\n\n";
    
    $prompt .= "OUTPUT FORMAT (JSON):\n";
    $prompt .= "{\n";
    $prompt .= '  "lessonNumber": ' . $lessonNumber . ",\n";
    $prompt .= '  "lessonTitle": "' . addslashes($lessonTitle) . "\",\n";
    $prompt .= '  "objectives": ["...", "...", "..."],\n';
    $prompt .= '  "explanation": "Comprehensive HTML explanation",\n';
    $prompt .= '  "guidedExamples": [{"title":"...", "problem":"...", "steps":["..."], "solution":"..."}],\n';
    $prompt .= '  "practiceProblems": [{"problem":"..."}],\n';
    $prompt .= '  "quizQuestions": [{"question":"...", "type":"multiple-choice", "options":[], "correctAnswer":""}],\n';
    $prompt .= '  "videoPlaceholder": "Video title",\n';
    $prompt .= '  "summary": "Key takeaways",\n';
    $prompt .= '  "vocabulary": [{"term":"...", "definition":"..."}],\n';
    $prompt .= '  "estimatedDuration": 45,\n';
    $prompt .= '  "difficulty": "' . $difficulty . '"\n';
    $prompt .= "}\n\n";
    $prompt .= "Generate the complete lesson now:";
    
    return $prompt;
}

/**
 * Call Course Design Agent (same as other generators)
 */
function callCourseDesignAgent($prompt) {
    global $db;
    
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
            'error' => 'No suitable agent found'
        ];
    }
    
    $agentServiceUrl = AGENT_SERVICE_URL . '/agent/chat';
    
    $payload = [
        'userId' => 1,
        'agentId' => $agent['agent_id'],
        'message' => $prompt
    ];
    
    $ch = curl_init($agentServiceUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        return ['success' => false, 'error' => "Agent service error: $error"];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "Agent service returned HTTP $httpCode"];
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['response'])) {
        return ['success' => false, 'error' => 'Invalid response from agent service'];
    }
    
    return ['success' => true, 'response' => $data['response']];
}

/**
 * Extract lesson JSON (same as other generators)
 */
function extractLessonJSON($response) {
    if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
        $json = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
        $json = $matches[1];
    } else {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $json = $matches[0];
        } else {
            return null;
        }
    }
    
    $lesson = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[Full Course Generator] JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $lesson;
}

/**
 * Validate lesson schema (same as other generators)
 */
function validateLessonSchema($lesson) {
    $errors = [];
    
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
    
    if (isset($lesson['lessonNumber']) && !is_numeric($lesson['lessonNumber'])) {
        $errors[] = "lessonNumber must be numeric";
    }
    
    if (isset($lesson['objectives']) && (!is_array($lesson['objectives']) || empty($lesson['objectives']))) {
        $errors[] = "objectives must be non-empty array";
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
