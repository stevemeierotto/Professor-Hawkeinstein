<?php
/**
 * Course Agent API
 * 
 * This agent monitors course drafts and can execute workflow steps.
 * All standards are now generated via the Standards Analyzer Agent.
 * 
 * Endpoints:
 *   GET  ?action=status&draftId=X    - Get current workflow status
 *   POST ?action=next&draftId=X      - Execute next step automatically
 *   GET  ?action=list                - List all drafts with status
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../helpers/system_agent_helper.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_course_agent');

header('Content-Type: application/json');

// Parse input from GET params or JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? 'status';
$draftId = $_GET['draftId'] ?? $input['draftId'] ?? null;

try {
    $db = getDB();
    
    switch ($action) {
        case 'status':
            echo json_encode(getWorkflowStatus($db, $draftId));
            break;
            
        case 'list':
            echo json_encode(listAllDrafts($db));
            break;
            
        case 'next':
            echo json_encode(executeNextStep($db, $draftId));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get detailed workflow status for a draft
 */
function getWorkflowStatus($db, $draftId) {
    if (!$draftId) {
        return ['success' => false, 'message' => 'draftId required'];
    }
    
    // Get draft info
    $stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
    $stmt->execute([$draftId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$draft) {
        return ['success' => false, 'message' => 'Draft not found'];
    }
    
    // Count approved standards
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM approved_standards WHERE draft_id = ?");
    $stmt->execute([$draftId]);
    $standardsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Check for outline
    $stmt = $db->prepare("SELECT outline_id, outline_json, generated_at, approved_at FROM course_outlines WHERE draft_id = ? ORDER BY generated_at DESC LIMIT 1");
    $stmt->execute([$draftId]);
    $outline = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check lesson content status
    $lessonContentCount = 0;
    $totalLessons = 0;
    if ($outline) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT CONCAT(unit_index, '_', lesson_index)) as count FROM draft_lesson_content WHERE draft_id = ?");
        $stmt->execute([$draftId]);
        $lessonContentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $outlineData = json_decode($outline['outline_json'] ?? '[]', true);
        foreach ($outlineData as $unit) {
            $totalLessons += count($unit['lessons'] ?? []);
        }
    }
    
    // Determine current step
    $currentStep = determineCurrentStep($draft, $standardsCount, $outline, $lessonContentCount, $totalLessons);
    
    return [
        'success' => true,
        'draftId' => (int)$draftId,
        'draft' => [
            'name' => $draft['course_name'],
            'subject' => $draft['subject'],
            'grade' => $draft['grade'],
            'status' => $draft['status'],
            'created_at' => $draft['created_at']
        ],
        'workflow' => [
            'currentStep' => $currentStep['step'],
            'currentStepName' => $currentStep['name'],
            'nextAction' => $currentStep['nextAction'],
            'canAutoExecute' => $currentStep['canAuto']
        ],
        'steps' => [
            [
                'step' => 1,
                'name' => 'Create Draft',
                'status' => 'complete',
                'data' => ['draft_id' => $draftId]
            ],
            [
                'step' => 2,
                'name' => 'Generate Standards',
                'status' => $standardsCount > 0 ? 'complete' : 'pending',
                'data' => ['standards_count' => $standardsCount]
            ],
            [
                'step' => 3,
                'name' => 'Generate Outline',
                'status' => $outline ? ($outline['approved_at'] ? 'approved' : 'generated') : 'pending',
                'data' => $outline ?: null
            ],
            [
                'step' => 4,
                'name' => 'Generate Lesson Content',
                'status' => $totalLessons > 0 ? ($lessonContentCount >= $totalLessons ? 'complete' : 'in_progress') : 'pending',
                'data' => [
                    'lessons_with_content' => $lessonContentCount,
                    'total_lessons' => $totalLessons,
                    'progress_percent' => $totalLessons > 0 ? round(($lessonContentCount / $totalLessons) * 100) : 0
                ]
            ],
            [
                'step' => 5,
                'name' => 'Generate Questions',
                'status' => 'pending',
                'data' => null
            ],
            [
                'step' => 6,
                'name' => 'Review & Publish',
                'status' => 'pending',
                'data' => null
            ]
        ]
    ];
}

/**
 * Determine which step we're on
 */
function determineCurrentStep($draft, $standardsCount, $outline, $lessonContentCount = 0, $totalLessons = 0) {
    // Step 1 always complete (we have a draft)
    
    // Step 2: Generate/Approve standards
    if ($standardsCount == 0) {
        return [
            'step' => 2,
            'name' => 'Generate Standards',
            'nextAction' => 'Use the Standards Analyzer Agent to generate standards for ' . $draft['subject'] . ' ' . $draft['grade'],
            'canAuto' => true
        ];
    }
    
    // Step 3: Generate outline
    if (!$outline) {
        return [
            'step' => 3,
            'name' => 'Generate Outline',
            'nextAction' => 'Go to Course Wizard Step 3 to generate the outline',
            'canAuto' => true
        ];
    }
    
    // Step 4: Generate lesson content
    if ($lessonContentCount < $totalLessons) {
        return [
            'step' => 4,
            'name' => 'Generate Lesson Content',
            'nextAction' => "Generate content for lessons ($lessonContentCount/$totalLessons done)",
            'canAuto' => true
        ];
    }
    
    // Step 5: Generate questions
    return [
        'step' => 5,
        'name' => 'Generate Questions',
        'nextAction' => 'Generate question banks for each lesson',
        'canAuto' => true
    ];
}

/**
 * List all drafts with their workflow status
 */
function listAllDrafts($db) {
    $stmt = $db->query("
        SELECT d.*, 
               (SELECT COUNT(*) FROM approved_standards WHERE draft_id = d.draft_id) as standards_count,
               (SELECT COUNT(*) FROM course_outlines WHERE draft_id = d.draft_id) as has_outline
        FROM course_drafts d
        ORDER BY d.created_at DESC
    ");
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($drafts as $draft) {
        $step = 1;
        if ($draft['standards_count'] > 0) $step = 2;
        if ($draft['has_outline'] > 0) $step = 3;
        
        $result[] = [
            'draftId' => (int)$draft['draft_id'],
            'name' => $draft['course_name'],
            'subject' => $draft['subject'],
            'grade' => $draft['grade'],
            'status' => $draft['status'],
            'currentStep' => $step,
            'standardsCount' => (int)$draft['standards_count'],
            'hasOutline' => $draft['has_outline'] > 0
        ];
    }
    
    return ['success' => true, 'drafts' => $result];
}

/**
 * Execute the next step automatically using agents
 */
function executeNextStep($db, $draftId) {
    $status = getWorkflowStatus($db, $draftId);
    if (!$status['success']) {
        return $status;
    }
    
    $currentStep = $status['workflow']['currentStep'];
    $draft = $status['draft'];
    
    switch ($currentStep) {
        case 2:
            // Auto-generate standards using Standards Analyzer Agent
            return autoGenerateStandards($db, $draftId, $draft);
            
        case 3:
            // Auto-generate outline using Outline Generator Agent
            return autoGenerateOutline($db, $draftId);
            
        default:
            return [
                'success' => false, 
                'message' => "Step $currentStep requires manual execution. " . $status['workflow']['nextAction']
            ];
    }
}

/**
 * Auto-generate standards using the Standards Analyzer Agent
 */
function autoGenerateStandards($db, $draftId, $draft) {
    // Build natural language prompt that the LLM can understand
    $prompt = "Generate educational standards for {$draft['grade']} {$draft['subject']}. Format each standard with a code and description.";
    
    $agentResponse = callSystemAgent('standards', $prompt);
    
    if (empty($agentResponse['response'])) {
        return ['success' => false, 'message' => 'Standards Analyzer Agent did not respond'];
    }
    
    // Parse response - agent returns JSON with standards array
    $rawResponse = trim($agentResponse['response']);
    $rawResponse = preg_replace('/^```json?\s*/i', '', $rawResponse);
    $rawResponse = preg_replace('/\s*```$/i', '', $rawResponse);
    
    $result = json_decode($rawResponse, true);
    
    // Handle both formats: {"standards": [...]} or direct array [...]
    $standards = $result['standards'] ?? $result;
    
    if (!is_array($standards)) {
        // Try to extract JSON array from response
        if (preg_match('/\[[\s\S]*\]/m', $rawResponse, $matches)) {
            $standards = json_decode($matches[0], true);
        }
    }
    
    // Fallback: Parse plain text format (e.g., "6.1a: Students will be able to...")
    if (!is_array($standards) || empty($standards)) {
        $lines = array_filter(array_map('trim', explode("\n", $rawResponse)));
        $standards = [];
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z0-9]+\.?[0-9]*[a-z]?):\s*(.+)$/i', $line, $m)) {
                $standards[] = [
                    'id' => $m[1],
                    'statement' => $m[2]
                ];
            }
        }
    }
    
    if (!is_array($standards) || empty($standards)) {
        return ['success' => false, 'message' => 'Could not parse standards from agent response', 'raw' => substr($rawResponse, 0, 200)];
    }
    
    // Insert standards - handle agent's output format
    $stmt = $db->prepare("
        INSERT INTO approved_standards (draft_id, standard_code, description, approved_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $inserted = 0;
    foreach ($standards as $std) {
        // Agent returns: id, statement, skills
        $code = $std['id'] ?? $std['code'] ?? $std['standard_code'] ?? '';
        $desc = $std['statement'] ?? $std['description'] ?? '';
        if ($desc) {
            $stmt->execute([$draftId, $code, $desc]);
            $inserted++;
        }
    }
    
    // Update draft status
    $db->prepare("UPDATE course_drafts SET status = 'standards_approved' WHERE draft_id = ?")->execute([$draftId]);
    
    return [
        'success' => true,
        'message' => "Generated and approved $inserted standards",
        'standardsCount' => $inserted,
        'nextStep' => 3,
        'nextAction' => 'Generate course outline'
    ];
}

/**
 * Auto-generate outline using the Outline Generator Agent
 */
function autoGenerateOutline($db, $draftId) {
    // Get approved standards
    $stmt = $db->prepare("SELECT standard_code, description FROM approved_standards WHERE draft_id = ? ORDER BY standard_id ASC");
    $stmt->execute([$draftId]);
    $standards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($standards)) {
        return ['success' => false, 'message' => 'No approved standards found'];
    }
    
    // Get draft info
    $stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
    $stmt->execute([$draftId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $standardsList = "";
    foreach ($standards as $std) {
        $standardsList .= "- " . ($std['standard_code'] ? $std['standard_code'] . ": " : "") . $std['description'] . "\n";
    }
    
    $prompt = "Create a course outline for '{$draft['course_name']}'. Return ONLY a JSON array of units. Each unit has 'title' and 'lessons' array. Each lesson has 'title' and 'description'. Standards:\n$standardsList";
    
    $agentResponse = callSystemAgent('outline', $prompt);
    
    if (empty($agentResponse['response'])) {
        return ['success' => false, 'message' => 'Outline Generator Agent did not respond'];
    }
    
    $resp = $agentResponse['response'];
    $outline = null;
    
    if (preg_match('/\[.*\]/s', $resp, $matches)) {
        $outline = json_decode($matches[0], true);
    }
    
    if (!$outline) {
        return ['success' => false, 'message' => 'Could not parse outline from agent response'];
    }
    
    // Save outline
    $outlineJson = json_encode($outline, JSON_PRETTY_PRINT);
    $stmt = $db->prepare("INSERT INTO course_outlines (draft_id, outline_json) VALUES (?, ?)");
    $stmt->execute([$draftId, $outlineJson]);
    
    // Update draft status
    $db->prepare("UPDATE course_drafts SET status = 'outline_review' WHERE draft_id = ?")->execute([$draftId]);
    
    $unitCount = count($outline);
    $lessonCount = 0;
    foreach ($outline as $unit) {
        $lessonCount += count($unit['lessons'] ?? []);
    }
    
    return [
        'success' => true,
        'message' => "Generated outline with $unitCount units and $lessonCount lessons",
        'nextStep' => 4,
        'nextAction' => 'Generate lesson content'
    ];
}
