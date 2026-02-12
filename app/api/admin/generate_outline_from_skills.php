<?php
/**
 * Generate Course Outline from Skills API
 * 
 * Takes simplified skills and generates a structured course outline with units and lessons.
 * Uses keyword-based clustering to group related skills into units.
 * 
 * @endpoint POST /api/admin/generate_outline_from_skills.php
 * @requires Admin authentication
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authentication
$adminUser = requireAdmin();

require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit('GENERATION', 'generate_outline_from_skills');

header('Content-Type: application/json');

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit;
}

// Validate required fields
$courseId = $data['courseId'] ?? null;
$simplifiedSkillsStoreId = $data['simplifiedSkillsStoreId'] ?? null;
$numUnits = $data['numUnits'] ?? 6;
$lessonsPerUnit = $data['lessonsPerUnit'] ?? 5;
$adminUserId = $data['adminUserId'] ?? ($adminUser['userId'] ?? $adminUser['user_id'] ?? null);

if (!$courseId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'courseId is required'
    ]);
    exit;
}

if (!$simplifiedSkillsStoreId || !is_numeric($simplifiedSkillsStoreId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Valid simplifiedSkillsStoreId is required'
    ]);
    exit;
}

// Validate numeric parameters
$numUnits = max(1, min(12, (int)$numUnits));
$lessonsPerUnit = max(1, min(10, (int)$lessonsPerUnit));

try {
    $db = getDB();
    
    // Load simplified skills from database
    $stmt = $db->prepare("
        SELECT id, jurisdiction_id, grade_level, subject, simplified_skills, skills_count, metadata
        FROM scraped_standards 
        WHERE id = ?
    ");
    $stmt->execute([$simplifiedSkillsStoreId]);
    $record = $stmt->fetch();
    
    if (!$record) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Skills record not found with storeId: ' . $simplifiedSkillsStoreId
        ]);
        exit;
    }
    
    $simplifiedSkills = json_decode($record['simplified_skills'], true);
    
    if (!$simplifiedSkills || !is_array($simplifiedSkills) || count($simplifiedSkills) === 0) {
        error_log("Empty skills for storeId $simplifiedSkillsStoreId. Record data: " . json_encode([
            'id' => $record['id'],
            'skills_count' => $record['skills_count'],
            'simplified_skills_length' => strlen($record['simplified_skills'] ?? ''),
            'json_error' => json_last_error_msg()
        ]));
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No simplified skills found in the record',
            'debug' => [
                'storeId' => $simplifiedSkillsStoreId,
                'recordExists' => true,
                'skillsCount' => $record['skills_count'],
                'simplifiedSkillsEmpty' => empty($record['simplified_skills']),
                'jsonError' => json_last_error_msg()
            ]
        ]);
        exit;
    }
    
    // Cluster skills into units
    $skillClusters = clusterSkillsIntoUnits($simplifiedSkills, $numUnits);
    
    // Generate outline structure
    $outline = generateOutlineStructure(
        $courseId,
        $skillClusters,
        $lessonsPerUnit,
        $record['subject'],
        $record['grade_level']
    );
    
    // Create course directory structure if it doesn't exist
    $courseDir = __DIR__ . '/../../course/' . $courseId;
    if (!is_dir($courseDir)) {
        mkdir($courseDir, 0755, true);
    }
    
    // Save outline to file
    $outlineFile = $courseDir . '/outline.json';
    file_put_contents($outlineFile, json_encode($outline, JSON_PRETTY_PRINT));
    
    // Update or create course metadata
    $metadataFile = $courseDir . '/metadata.json';
    $metadata = [];
    if (file_exists($metadataFile)) {
        $metadata = json_decode(file_get_contents($metadataFile), true) ?: [];
    }
    
    $metadata['outlineStatus'] = 'generated_pending_approval';
    $metadata['outlineGeneratedAt'] = date('Y-m-d H:i:s');
    $metadata['outlineGeneratedBy'] = $adminUserId;
    $metadata['simplifiedSkillsStoreId'] = $simplifiedSkillsStoreId;
    $metadata['numUnits'] = $numUnits;
    $metadata['lessonsPerUnit'] = $lessonsPerUnit;
    
    file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
    
    // Calculate metrics
    $metrics = calculateOutlineMetrics($outline);
    
    // Log the generation
    error_log(sprintf(
        "Outline generated: course=%s, storeId=%d, units=%d, lessons=%d, skills=%d, admin=%d",
        $courseId,
        $simplifiedSkillsStoreId,
        $numUnits,
        $numUnits * $lessonsPerUnit,
        count($simplifiedSkills),
        $adminUserId
    ));
    
    echo json_encode([
        'success' => true,
        'message' => 'Course outline generated successfully',
        'outline' => $outline,
        'metrics' => $metrics,
        'outlineFile' => 'course/' . $courseId . '/outline.json',
        'outlineStatus' => 'generated_pending_approval'
    ]);
    
} catch (Exception $e) {
    error_log("Error generating outline: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate outline: ' . $e->getMessage()
    ]);
}

/**
 * Cluster skills into units using keyword-based similarity
 * 
 * @param array $skills Simplified skills array
 * @param int $numUnits Number of units to create
 * @return array Array of skill clusters
 */
function clusterSkillsIntoUnits($skills, $numUnits) {
    // Extract keywords from all skills
    $skillKeywords = [];
    foreach ($skills as $idx => $skill) {
        $keywords = extractKeywords($skill['text']);
        $skillKeywords[$idx] = [
            'skill' => $skill,
            'keywords' => $keywords
        ];
    }
    
    // If we have very few skills, use round-robin
    if (count($skills) < $numUnits * 2) {
        return roundRobinClustering($skills, $numUnits);
    }
    
    // Find initial cluster centers using keyword diversity
    $clusterCenters = findInitialClusters($skillKeywords, $numUnits);
    
    // Assign skills to nearest cluster
    $clusters = array_fill(0, $numUnits, []);
    
    foreach ($skillKeywords as $idx => $skillData) {
        $bestCluster = 0;
        $bestSimilarity = -1;
        
        foreach ($clusterCenters as $clusterIdx => $centerKeywords) {
            $similarity = calculateKeywordSimilarity($skillData['keywords'], $centerKeywords);
            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestCluster = $clusterIdx;
            }
        }
        
        $clusters[$bestCluster][] = $skillData['skill'];
    }
    
    // Balance clusters - ensure no cluster is empty
    $clusters = balanceClusters($clusters, $numUnits);
    
    return $clusters;
}

/**
 * Extract significant keywords from skill text
 */
function extractKeywords($text) {
    // Convert to lowercase and remove punctuation
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s]/', ' ', $text);
    
    // Split into words
    $words = preg_split('/\s+/', $text);
    
    // Common stop words to filter out
    $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'are', 'was', 'were', 'be',
        'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
        'would', 'could', 'should', 'may', 'might', 'can', 'that', 'this',
        'these', 'those', 'it', 'its', 'their', 'them', 'they', 'we', 'you'
    ];
    
    // Filter and return significant words (length > 3, not stop words)
    $keywords = array_filter($words, function($word) use ($stopWords) {
        return strlen($word) > 3 && !in_array($word, $stopWords);
    });
    
    return array_values(array_unique($keywords));
}

/**
 * Find initial cluster centers with maximum keyword diversity
 */
function findInitialClusters($skillKeywords, $numUnits) {
    $centers = [];
    $usedIndices = [];
    
    // Pick first center randomly
    $firstIdx = array_rand($skillKeywords);
    $centers[] = $skillKeywords[$firstIdx]['keywords'];
    $usedIndices[$firstIdx] = true;
    
    // Pick remaining centers - choose skills most different from existing centers
    for ($i = 1; $i < $numUnits; $i++) {
        $maxDistance = -1;
        $bestIdx = null;
        
        foreach ($skillKeywords as $idx => $skillData) {
            if (isset($usedIndices[$idx])) continue;
            
            // Calculate minimum similarity to any existing center
            $minSimilarity = PHP_FLOAT_MAX;
            foreach ($centers as $centerKeywords) {
                $similarity = calculateKeywordSimilarity($skillData['keywords'], $centerKeywords);
                $minSimilarity = min($minSimilarity, $similarity);
            }
            
            // We want maximum distance (minimum similarity)
            if ($minSimilarity < $maxDistance || $bestIdx === null) {
                $maxDistance = $minSimilarity;
                $bestIdx = $idx;
            }
        }
        
        if ($bestIdx !== null) {
            $centers[] = $skillKeywords[$bestIdx]['keywords'];
            $usedIndices[$bestIdx] = true;
        }
    }
    
    return $centers;
}

/**
 * Calculate keyword similarity between two keyword sets (Jaccard similarity)
 */
function calculateKeywordSimilarity($keywords1, $keywords2) {
    if (empty($keywords1) || empty($keywords2)) {
        return 0.0;
    }
    
    $intersection = count(array_intersect($keywords1, $keywords2));
    $union = count(array_unique(array_merge($keywords1, $keywords2)));
    
    return $union > 0 ? $intersection / $union : 0.0;
}

/**
 * Round-robin clustering fallback for small skill sets
 */
function roundRobinClustering($skills, $numUnits) {
    $clusters = array_fill(0, $numUnits, []);
    
    foreach ($skills as $idx => $skill) {
        $clusterIdx = $idx % $numUnits;
        $clusters[$clusterIdx][] = $skill;
    }
    
    return $clusters;
}

/**
 * Balance clusters to ensure none are empty
 */
function balanceClusters($clusters, $numUnits) {
    // Find empty and overfull clusters
    $emptyClusters = [];
    $fullClusters = [];
    
    foreach ($clusters as $idx => $cluster) {
        if (empty($cluster)) {
            $emptyClusters[] = $idx;
        } elseif (count($cluster) > 1) {
            $fullClusters[] = $idx;
        }
    }
    
    // Move skills from full to empty clusters
    foreach ($emptyClusters as $emptyIdx) {
        if (!empty($fullClusters)) {
            $fullIdx = $fullClusters[0];
            if (count($clusters[$fullIdx]) > 1) {
                // Move one skill
                $skill = array_pop($clusters[$fullIdx]);
                $clusters[$emptyIdx][] = $skill;
                
                // If this cluster is now small, remove from fullClusters
                if (count($clusters[$fullIdx]) <= 1) {
                    array_shift($fullClusters);
                }
            }
        }
    }
    
    return $clusters;
}

/**
 * Generate complete outline structure with units and lessons
 */
function generateOutlineStructure($courseId, $skillClusters, $lessonsPerUnit, $subject, $gradeLevel) {
    $units = [];
    
    foreach ($skillClusters as $unitIdx => $unitSkills) {
        $unitNumber = $unitIdx + 1;
        
        // Generate unit title based on skills
        $unitTitle = generateUnitTitle($unitSkills, $unitNumber, $subject);
        
        // Distribute skills across lessons
        $lessons = distributeSkillsToLessons($unitSkills, $lessonsPerUnit, $unitNumber);
        
        $units[] = [
            'unitNumber' => $unitNumber,
            'title' => $unitTitle,
            'skills' => $unitSkills,
            'lessons' => $lessons
        ];
    }
    
    return [
        'courseId' => $courseId,
        'subject' => $subject,
        'gradeLevel' => $gradeLevel,
        'generatedAt' => date('Y-m-d H:i:s'),
        'units' => $units
    ];
}

/**
 * Generate unit title from skills
 */
function generateUnitTitle($skills, $unitNumber, $subject) {
    // Extract common themes from skill texts
    $allKeywords = [];
    foreach ($skills as $skill) {
        $keywords = extractKeywords($skill['text']);
        $allKeywords = array_merge($allKeywords, $keywords);
    }
    
    // Count keyword frequencies
    $keywordCounts = array_count_values($allKeywords);
    arsort($keywordCounts);
    
    // Take top 2-3 keywords
    $topKeywords = array_slice(array_keys($keywordCounts), 0, 3);
    
    if (!empty($topKeywords)) {
        // Capitalize and format
        $topKeywords = array_map('ucfirst', $topKeywords);
        $theme = implode(' and ', array_slice($topKeywords, 0, 2));
        return "Unit $unitNumber: $theme";
    }
    
    // Fallback
    return "Unit $unitNumber: $subject Fundamentals";
}

/**
 * Distribute skills across lessons
 */
function distributeSkillsToLessons($unitSkills, $lessonsPerUnit, $unitNumber) {
    $lessons = [];
    $skillsPerLesson = max(1, ceil(count($unitSkills) / $lessonsPerUnit));
    
    for ($lessonIdx = 0; $lessonIdx < $lessonsPerUnit; $lessonIdx++) {
        $lessonNumber = $lessonIdx + 1;
        
        // Get skills for this lesson
        $startIdx = $lessonIdx * $skillsPerLesson;
        $lessonSkills = array_slice($unitSkills, $startIdx, $skillsPerLesson);
        
        // Skip empty lessons
        if (empty($lessonSkills)) {
            continue;
        }
        
        // Generate lesson title
        $lessonTitle = generateLessonTitle($lessonSkills, $lessonNumber);
        
        // Extract objectives from skills
        $objectives = array_map(function($skill) {
            return $skill['text'];
        }, $lessonSkills);
        
        $lessons[] = [
            'lessonNumber' => $lessonNumber,
            'title' => $lessonTitle,
            'objectives' => $objectives,
            'skillIds' => array_column($lessonSkills, 'skillId')
        ];
    }
    
    return $lessons;
}

/**
 * Generate lesson title from skills
 */
function generateLessonTitle($skills, $lessonNumber) {
    if (empty($skills)) {
        return "Lesson $lessonNumber";
    }
    
    // Extract keywords from first skill or most common keywords
    $allKeywords = [];
    foreach ($skills as $skill) {
        $keywords = extractKeywords($skill['text']);
        $allKeywords = array_merge($allKeywords, $keywords);
    }
    
    // Get most common keyword
    if (!empty($allKeywords)) {
        $keywordCounts = array_count_values($allKeywords);
        arsort($keywordCounts);
        $topKeyword = ucfirst(array_key_first($keywordCounts));
        
        return "Lesson $lessonNumber: $topKeyword";
    }
    
    return "Lesson $lessonNumber";
}

/**
 * Calculate outline metrics
 */
function calculateOutlineMetrics($outline) {
    $totalSkills = 0;
    $totalLessons = 0;
    $skillsPerUnit = [];
    $lessonsPerUnit = [];
    $objectivesPerLesson = [];
    
    foreach ($outline['units'] as $unit) {
        $unitSkillCount = count($unit['skills']);
        $unitLessonCount = count($unit['lessons']);
        
        $totalSkills += $unitSkillCount;
        $totalLessons += $unitLessonCount;
        $skillsPerUnit[] = $unitSkillCount;
        $lessonsPerUnit[] = $unitLessonCount;
        
        foreach ($unit['lessons'] as $lesson) {
            $objectivesPerLesson[] = count($lesson['objectives']);
        }
    }
    
    return [
        'totalUnits' => count($outline['units']),
        'totalLessons' => $totalLessons,
        'totalSkills' => $totalSkills,
        'avgSkillsPerUnit' => $totalSkills > 0 ? round($totalSkills / count($outline['units']), 2) : 0,
        'avgLessonsPerUnit' => $totalLessons > 0 ? round($totalLessons / count($outline['units']), 2) : 0,
        'avgObjectivesPerLesson' => !empty($objectivesPerLesson) ? round(array_sum($objectivesPerLesson) / count($objectivesPerLesson), 2) : 0,
        'skillsPerUnit' => $skillsPerUnit,
        'lessonsPerUnit' => $lessonsPerUnit
    ];
}
