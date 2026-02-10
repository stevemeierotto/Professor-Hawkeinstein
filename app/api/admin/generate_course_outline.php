<?php
/**
 * Course Outline Generator Mode API Endpoint
 * 
 * Generates a complete course outline with units, lesson titles, and standards mapping.
 * Does NOT generate full lesson content - just the structure for admin approval.
 * 
 * POST /api/admin/generate_course_outline.php
 * 
 * Required fields:
 * - subject: Subject area (e.g., "Algebra", "Biology", "World History")
 * - level: Grade/difficulty level (e.g., "High School", "Middle School", "College")
 * 
 * Optional fields:
 * - unitCount: Number of units (default: 6, min: 3, max: 12)
 * - lessonsPerUnit: Lessons per unit (default: 5, min: 3, max: 8)
 * - standardsSet: Standards framework (e.g., "Common Core", "NGSS", "State Standards")
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "courseName": "...",
 *   "subject": "...",
 *   "level": "...",
 *   "units": [
 *     {
 *       "unitNumber": 1,
 *       "unitTitle": "...",
 *       "description": "...",
 *       "standards": [...],
 *       "lessons": [
 *         {"lessonNumber": 1, "lessonTitle": "...", "description": "..."},
 *         ...
 *       ]
 *     },
 *     ...
 *   ],
 *   "totalUnits": 6,
 *   "totalLessons": 30,
 *   "generationTime": 15.2
 * }
 */

require_once __DIR__ . '/../admin/auth_check.php';
$adminUser = requireAdmin();

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Set timeout for outline generation (2 minutes)
set_time_limit(120);

$startTime = microtime(true);

// Get JSON input
$input = getJSONInput();

// Validate required fields
$required = ['subject', 'level'];
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

$subject = $input['subject'];
$level = $input['level'];
$unitCount = isset($input['unitCount']) ? (int)$input['unitCount'] : 6;
$lessonsPerUnit = isset($input['lessonsPerUnit']) ? (int)$input['lessonsPerUnit'] : 5;
$standardsSet = $input['standardsSet'] ?? null;

// Validate inputs
if ($unitCount < 3 || $unitCount > 12) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'unitCount must be between 3 and 12'
    ]);
    exit;
}

if ($lessonsPerUnit < 3 || $lessonsPerUnit > 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'lessonsPerUnit must be between 3 and 8'
    ]);
    exit;
}

try {
    // Build prompt for course outline generation
    $prompt = buildCourseOutlinePrompt($subject, $level, $unitCount, $lessonsPerUnit, $standardsSet);
    
    // Call agent to generate outline
    $agentResponse = callCourseDesignAgent($prompt);
    
    if (!$agentResponse['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $agentResponse['error'] ?? 'Failed to generate course outline'
        ]);
        exit;
    }
    
    // Extract and parse outline JSON
    $outline = extractOutlineJSON($agentResponse['response']);
    
    if (!$outline) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to parse course outline from agent response',
            'rawResponse' => substr($agentResponse['response'], 0, 500)
        ]);
        exit;
    }
    
    // Validate outline structure
    $validation = validateOutlineStructure($outline, $unitCount, $lessonsPerUnit);
    
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Generated outline failed validation',
            'validationErrors' => $validation['errors'],
            'outline' => $outline
        ]);
        exit;
    }
    
    $endTime = microtime(true);
    $generationTime = round($endTime - $startTime, 2);
    
    // Calculate totals
    $totalLessons = 0;
    foreach ($outline['units'] as $unit) {
        $totalLessons += count($unit['lessons'] ?? []);
    }
    
    // Log the activity
    logActivity(
        $adminUser['userId'],
        'GENERATE_COURSE_OUTLINE',
        "Generated course outline: $subject ($level) with $unitCount units"
    );
    
    // Return the outline
    $response = [
        'success' => true,
        'courseName' => $outline['courseName'] ?? "$subject - $level",
        'subject' => $subject,
        'level' => $level,
        'description' => $outline['description'] ?? '',
        'units' => $outline['units'],
        'totalUnits' => count($outline['units']),
        'totalLessons' => $totalLessons,
        'generationTime' => $generationTime,
        'message' => "Course outline generated successfully"
    ];
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("[Course Outline] Fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Build prompt for course outline generation
 */
function buildCourseOutlinePrompt($subject, $level, $unitCount, $lessonsPerUnit, $standardsSet) {
    $prompt = "You are an expert curriculum designer. Create a comprehensive course outline for:\n\n";
    $prompt .= "COURSE DETAILS:\n";
    $prompt .= "- Subject: $subject\n";
    $prompt .= "- Level: $level\n";
    $prompt .= "- Structure: $unitCount units, $lessonsPerUnit lessons per unit\n";
    
    if ($standardsSet) {
        $prompt .= "- Standards Framework: $standardsSet\n";
    }
    
    $prompt .= "\nOBJECTIVE:\n";
    $prompt .= "Design a logical progression of units and lessons that builds from foundational to advanced concepts.\n";
    $prompt .= "Each unit should focus on a major topic area, and lessons within each unit should build sequentially.\n\n";
    
    $prompt .= "REQUIREMENTS:\n";
    $prompt .= "1. Create $unitCount units covering the full scope of $subject at $level level\n";
    $prompt .= "2. Each unit must have exactly $lessonsPerUnit lessons\n";
    $prompt .= "3. Provide clear, descriptive titles for units and lessons\n";
    $prompt .= "4. Include brief descriptions explaining what each unit/lesson covers\n";
    $prompt .= "5. Map relevant educational standards to each unit\n";
    $prompt .= "6. Ensure logical progression (simple → complex, concrete → abstract)\n\n";
    
    $prompt .= "OUTPUT FORMAT (JSON):\n";
    $prompt .= "Return ONLY a JSON object with this structure:\n";
    $prompt .= "{\n";
    $prompt .= '  "courseName": "Descriptive course name",\n';
    $prompt .= '  "description": "Brief course overview",\n';
    $prompt .= '  "units": [\n';
    $prompt .= "    {\n";
    $prompt .= '      "unitNumber": 1,\n';
    $prompt .= '      "unitTitle": "Clear, descriptive unit title",\n';
    $prompt .= '      "description": "What this unit covers and why",\n';
    $prompt .= '      "standards": [\n';
    $prompt .= '        {"code": "STANDARD.CODE", "description": "What the standard requires"},\n';
    $prompt .= "        ...\n";
    $prompt .= "      ],\n";
    $prompt .= '      "lessons": [\n';
    $prompt .= '        {"lessonNumber": 1, "lessonTitle": "...", "description": "..."},\n';
    $prompt .= '        {"lessonNumber": 2, "lessonTitle": "...", "description": "..."},\n';
    $prompt .= "        ...\n";
    $prompt .= "      ]\n";
    $prompt .= "    },\n";
    $prompt .= "    ...\n";
    $prompt .= "  ]\n";
    $prompt .= "}\n\n";
    
    $prompt .= "IMPORTANT:\n";
    $prompt .= "- Do NOT generate full lesson content (no examples, practice problems, quizzes)\n";
    $prompt .= "- Only provide titles and brief descriptions\n";
    $prompt .= "- This is an outline for admin approval before content generation\n";
    $prompt .= "- Ensure all units have exactly $lessonsPerUnit lessons\n";
    $prompt .= "- Number units 1-$unitCount and lessons 1-$lessonsPerUnit within each unit\n\n";
    
    $prompt .= "Generate the course outline now:";
    
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
            'error' => 'No suitable agent found for outline generation'
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // 90 second timeout for outline
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
 * Extract outline JSON from agent response
 */
function extractOutlineJSON($response) {
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
            error_log("[Course Outline] Could not find JSON in response: " . substr($response, 0, 200));
            return null;
        }
    }
    
    $outline = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[Course Outline] JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $outline;
}

/**
 * Validate outline structure
 */
function validateOutlineStructure($outline, $expectedUnits, $expectedLessonsPerUnit) {
    $errors = [];
    
    // Check top-level structure
    if (!isset($outline['units']) || !is_array($outline['units'])) {
        $errors[] = "Missing or invalid 'units' array";
        return ['valid' => false, 'errors' => $errors];
    }
    
    $actualUnits = count($outline['units']);
    if ($actualUnits !== $expectedUnits) {
        $errors[] = "Expected $expectedUnits units, got $actualUnits";
    }
    
    // Validate each unit
    foreach ($outline['units'] as $index => $unit) {
        $unitNum = $index + 1;
        
        if (!isset($unit['unitNumber'])) {
            $errors[] = "Unit $unitNum missing unitNumber";
        }
        
        if (!isset($unit['unitTitle']) || empty($unit['unitTitle'])) {
            $errors[] = "Unit $unitNum missing or empty unitTitle";
        }
        
        if (!isset($unit['lessons']) || !is_array($unit['lessons'])) {
            $errors[] = "Unit $unitNum missing or invalid lessons array";
            continue;
        }
        
        $actualLessons = count($unit['lessons']);
        if ($actualLessons !== $expectedLessonsPerUnit) {
            $errors[] = "Unit $unitNum: expected $expectedLessonsPerUnit lessons, got $actualLessons";
        }
        
        // Validate each lesson in unit
        foreach ($unit['lessons'] as $lessonIndex => $lesson) {
            $lessonNum = $lessonIndex + 1;
            
            if (!isset($lesson['lessonNumber'])) {
                $errors[] = "Unit $unitNum, Lesson $lessonNum missing lessonNumber";
            }
            
            if (!isset($lesson['lessonTitle']) || empty($lesson['lessonTitle'])) {
                $errors[] = "Unit $unitNum, Lesson $lessonNum missing or empty lessonTitle";
            }
        }
        
        // Check if standards exist (optional but recommended)
        if (!isset($unit['standards']) || !is_array($unit['standards'])) {
            // Warning, not error
            error_log("[Course Outline] Unit $unitNum has no standards array");
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
?>
