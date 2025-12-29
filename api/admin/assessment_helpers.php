<?php
/**
 * Assessment Generation Helper Functions
 * 
 * Convenient wrapper functions for generating assessments.
 * Can be included in other scripts that need assessment generation.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../course/CourseMetadata.php';

/**
 * Generate a unit test for a specific unit
 * 
 * @param string $courseId Course identifier
 * @param int $unitNumber Unit number to test
 * @param int $numQuestions Number of questions (default: 20)
 * @param string $difficulty "easy", "medium", "hard", or "mixed" (default: "mixed")
 * @param array $questionTypes Types to include (default: all types)
 * @param bool $includeAnswerKey Include answer key (default: true)
 * @return array Assessment data or error
 */
function generateUnitTest(
    $courseId, 
    $unitNumber, 
    $numQuestions = 20, 
    $difficulty = 'mixed',
    $questionTypes = ['multiple_choice', 'short_answer', 'problem_solving', 'true_false'],
    $includeAnswerKey = true
) {
    return callAssessmentAPI([
        'courseId' => $courseId,
        'assessmentType' => 'unit_test',
        'unitNumber' => $unitNumber,
        'numQuestions' => $numQuestions,
        'difficulty' => $difficulty,
        'questionTypes' => $questionTypes,
        'includeAnswerKey' => $includeAnswerKey
    ]);
}

/**
 * Generate a midterm exam (typically after Unit 3)
 * 
 * @param string $courseId Course identifier
 * @param int $upToUnit Include units 1 through this number (default: 3)
 * @param int $numQuestions Number of questions (default: 40)
 * @param string $difficulty "easy", "medium", "hard", or "mixed" (default: "mixed")
 * @param array $questionTypes Types to include (default: all types)
 * @param bool $includeAnswerKey Include answer key (default: true)
 * @return array Assessment data or error
 */
function generateMidterm(
    $courseId,
    $upToUnit = 3,
    $numQuestions = 40,
    $difficulty = 'mixed',
    $questionTypes = ['multiple_choice', 'short_answer', 'problem_solving', 'true_false'],
    $includeAnswerKey = true
) {
    return callAssessmentAPI([
        'courseId' => $courseId,
        'assessmentType' => 'midterm',
        'upToUnit' => $upToUnit,
        'numQuestions' => $numQuestions,
        'difficulty' => $difficulty,
        'questionTypes' => $questionTypes,
        'includeAnswerKey' => $includeAnswerKey
    ]);
}

/**
 * Generate a final exam (typically after Unit 6 or all units)
 * 
 * @param string $courseId Course identifier
 * @param int|null $upToUnit Include units 1 through this number (null = all units)
 * @param int $numQuestions Number of questions (default: 60)
 * @param string $difficulty "easy", "medium", "hard", or "mixed" (default: "mixed")
 * @param array $questionTypes Types to include (default: all types)
 * @param bool $includeAnswerKey Include answer key (default: true)
 * @return array Assessment data or error
 */
function generateFinalExam(
    $courseId,
    $upToUnit = null,
    $numQuestions = 60,
    $difficulty = 'mixed',
    $questionTypes = ['multiple_choice', 'short_answer', 'problem_solving', 'true_false'],
    $includeAnswerKey = true
) {
    $params = [
        'courseId' => $courseId,
        'assessmentType' => 'final_exam',
        'numQuestions' => $numQuestions,
        'difficulty' => $difficulty,
        'questionTypes' => $questionTypes,
        'includeAnswerKey' => $includeAnswerKey
    ];
    
    if ($upToUnit !== null) {
        $params['upToUnit'] = $upToUnit;
    }
    
    return callAssessmentAPI($params);
}

/**
 * Save assessment to course metadata
 * 
 * @param string $courseId Course identifier
 * @param array $assessment Assessment data
 * @param string $assessmentType "unit_test", "midterm", or "final_exam"
 * @param int|null $unitNumber Unit number (for unit tests)
 * @return array Result with success status
 */
function saveAssessmentToCourse($courseId, $assessment, $assessmentType, $unitNumber = null) {
    $courseDir = __DIR__ . '/../course/courses/';
    
    if (strpos($courseId, '.json') !== false) {
        $courseFile = $courseDir . basename($courseId);
    } else {
        $courseFile = $courseDir . 'course_' . preg_replace('/[^a-z0-9_-]/i', '_', $courseId) . '.json';
    }
    
    if (!file_exists($courseFile)) {
        return [
            'success' => false,
            'error' => "Course file not found: $courseFile"
        ];
    }
    
    try {
        $course = new CourseMetadata($courseFile);
        $metadata = $course->getData();
        
        // Initialize assessments array if not exists
        if (!isset($metadata['assessments'])) {
            $metadata['assessments'] = [];
        }
        
        // Add assessment
        $assessmentId = $assessmentType;
        if ($assessmentType === 'unit_test' && $unitNumber) {
            $assessmentId = "unit_{$unitNumber}_test";
        }
        
        $metadata['assessments'][$assessmentId] = [
            'type' => $assessmentType,
            'unitNumber' => $unitNumber,
            'assessment' => $assessment,
            'createdAt' => date('c'),
            'version' => '1.0'
        ];
        
        // Save back to file
        $course->setData($metadata);
        $result = $course->saveToFile($courseFile);
        
        if ($result) {
            return [
                'success' => true,
                'assessmentId' => $assessmentId,
                'message' => "Assessment saved to course metadata"
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to save course file'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate all assessments for a course
 * 
 * @param string $courseId Course identifier
 * @param bool $saveToMetadata Save assessments to course file (default: true)
 * @return array Results for each assessment
 */
function generateAllAssessments($courseId, $saveToMetadata = true) {
    $results = [];
    
    // Load course to determine number of units
    $courseDir = __DIR__ . '/../course/courses/';
    if (strpos($courseId, '.json') !== false) {
        $courseFile = $courseDir . basename($courseId);
    } else {
        $courseFile = $courseDir . 'course_' . preg_replace('/[^a-z0-9_-]/i', '_', $courseId) . '.json';
    }
    
    if (!file_exists($courseFile)) {
        return [
            'success' => false,
            'error' => "Course file not found: $courseFile"
        ];
    }
    
    $course = new CourseMetadata($courseFile);
    $units = $course->getUnits();
    $numUnits = count($units);
    
    // Generate unit tests for each unit
    foreach ($units as $unit) {
        $unitNumber = $unit['unitNumber'];
        echo "Generating Unit $unitNumber test...\n";
        
        $result = generateUnitTest($courseId, $unitNumber);
        
        if ($result['success']) {
            if ($saveToMetadata) {
                $saveResult = saveAssessmentToCourse(
                    $courseId, 
                    $result['assessment'], 
                    'unit_test', 
                    $unitNumber
                );
                $result['saved'] = $saveResult['success'];
            }
            $results["unit_{$unitNumber}_test"] = $result;
        } else {
            $results["unit_{$unitNumber}_test"] = $result;
        }
    }
    
    // Generate midterm after Unit 3 (if course has 3+ units)
    if ($numUnits >= 3) {
        echo "Generating Midterm exam...\n";
        
        $result = generateMidterm($courseId, 3);
        
        if ($result['success']) {
            if ($saveToMetadata) {
                $saveResult = saveAssessmentToCourse(
                    $courseId,
                    $result['assessment'],
                    'midterm'
                );
                $result['saved'] = $saveResult['success'];
            }
            $results['midterm'] = $result;
        } else {
            $results['midterm'] = $result;
        }
    }
    
    // Generate final exam (all units)
    echo "Generating Final exam...\n";
    
    $result = generateFinalExam($courseId);
    
    if ($result['success']) {
        if ($saveToMetadata) {
            $saveResult = saveAssessmentToCourse(
                $courseId,
                $result['assessment'],
                'final_exam'
            );
            $result['saved'] = $saveResult['success'];
        }
        $results['final_exam'] = $result;
    } else {
        $results['final_exam'] = $result;
    }
    
    return [
        'success' => true,
        'assessments' => $results,
        'totalGenerated' => count($results)
    ];
}

/**
 * Internal function to call assessment API
 */
function callAssessmentAPI($params) {
    $apiUrl = 'http://localhost/basic_educational/api/admin/generate_assessment.php';
    
    // Get admin token from session (if running in web context)
    // For CLI usage, this would need to be passed or configured
    $token = $_SESSION['admin_token'] ?? null;
    
    if (!$token) {
        // Try to get from environment or config
        $token = getenv('ADMIN_JWT_TOKEN');
    }
    
    if (!$token) {
        return [
            'success' => false,
            'error' => 'No admin token available. Set ADMIN_JWT_TOKEN environment variable or authenticate first.'
        ];
    }
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => "API call failed: $error"
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "API returned HTTP $httpCode",
            'response' => $response
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        return [
            'success' => false,
            'error' => 'Invalid JSON response from API'
        ];
    }
    
    return $result;
}

/**
 * Example usage in a script:
 * 
 * require_once 'api/admin/assessment_helpers.php';
 * 
 * // Generate single unit test
 * $result = generateUnitTest('algebra_1', 2);
 * if ($result['success']) {
 *     echo "Generated test with {$result['totalQuestions']} questions\n";
 *     
 *     // Save to course
 *     saveAssessmentToCourse('algebra_1', $result['assessment'], 'unit_test', 2);
 * }
 * 
 * // Generate midterm
 * $midterm = generateMidterm('algebra_1');
 * 
 * // Generate final exam
 * $final = generateFinalExam('algebra_1');
 * 
 * // Generate all assessments at once
 * $allResults = generateAllAssessments('algebra_1', true);
 */
