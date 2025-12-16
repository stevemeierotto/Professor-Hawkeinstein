<?php
// Generate course outline for a draft (Step 3 of wizard)
require_once '../../config/database.php';
require_once 'auth_check.php';
require_once '../helpers/system_agent_helper.php';
requireAdmin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['draftId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing draftId.']);
    exit;
}

$draftId = intval($input['draftId']);
$db = getDb();

$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$draft) {
    echo json_encode(['success' => false, 'message' => 'Draft not found.']);
    exit;
}

// Fetch approved standards in order (S1 first, S12 last)
$stmt = $db->prepare("SELECT standard_code, description FROM approved_standards WHERE draft_id = ? ORDER BY standard_id ASC");
$stmt->execute([$draftId]);
$standards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Try LLM-based outline generation first (uses Outline Generator agent)
$outline = generateOutlineWithLLM($draft, $standards);

// Fallback to pattern-based organization if LLM fails
if (empty($outline)) {
    error_log("[generate_draft_outline] LLM generation failed, falling back to pattern-based organization");
    $outline = organizeStandardsIntoOutline($standards);
}

$outlineJson = json_encode($outline, JSON_PRETTY_PRINT);

$stmt = $db->prepare("INSERT INTO course_outlines (draft_id, outline_json) VALUES (?, ?)");
$stmt->execute([$draftId, $outlineJson]);

$db->prepare("UPDATE course_drafts SET status = 'outline_review' WHERE draft_id = ?")->execute([$draftId]);

echo json_encode(['success' => true, 'outline' => $outlineJson]);

function organizeStandardsIntoOutline($standards) {
    if (empty($standards)) {
        return [];
    }
    
    // Check if agent-generated standards (S1, S2, S3...)
    $firstCode = trim($standards[0]['standard_code'] ?? '');
    $isAgentGenerated = preg_match('/^S\d+$/', $firstCode);
    
    if ($isAgentGenerated) {
        // Agent-generated: keep original order (S1, S2, S3...)
        $lessons = [];
        foreach ($standards as $std) {
            $code = trim($std['standard_code'] ?? '');
            $desc = trim($std['description'] ?? '');
            if (!empty($desc)) {
                $lessons[] = [
                    'title' => ($code ? "$code: " : '') . substr($desc, 0, 60) . (strlen($desc) > 60 ? '...' : ''),
                    'description' => $desc,
                    'standard_code' => $code
                ];
            }
        }
        return [[
            'title' => 'Course Content',
            'description' => '',
            'lessons' => $lessons
        ]];
    }
    
    // Scraped standards: Parse Alaska/NGSS format
    $units = [];
    $pendingLessons = [];
    
    foreach ($standards as $std) {
        $code = trim($std['standard_code'] ?? '');
        $desc = trim($std['description'] ?? '');
        
        if (preg_match('/^[A-Z]\.$/', $code)) {
            $units[] = ['title' => "$code $desc", 'description' => '', 'lessons' => $pendingLessons];
            $pendingLessons = [];
        } elseif ($code === 'N/A' && stripos($desc, 'should understand') === false && strlen($desc) < 80) {
            $units[] = ['title' => $desc, 'description' => '', 'lessons' => $pendingLessons];
            $pendingLessons = [];
        } elseif ($code === 'N/A' && stripos($desc, 'should understand') !== false) {
            // Skip
        } elseif (preg_match('/^[K\d]-/', $code)) {
            $pendingLessons[] = ['title' => "$code: " . substr($desc, 0, 60), 'description' => $desc, 'standard_code' => $code];
        } elseif (preg_match('/^\d+\)$/', $code)) {
            $pendingLessons[] = ['title' => substr($desc, 0, 60), 'description' => $desc, 'standard_code' => $code];
        } elseif (!empty($desc) && $code !== 'N/A') {
            $pendingLessons[] = ['title' => ($code ? "$code: " : '') . substr($desc, 0, 60), 'description' => $desc, 'standard_code' => $code];
        }
    }
    
    if (!empty($pendingLessons)) {
        $units[] = ['title' => 'Additional Content', 'description' => '', 'lessons' => $pendingLessons];
    }
    
    return array_reverse($units);
}

function generateOutlineWithLLM($draft, $standards) {
    $standardsList = "";
    foreach ($standards as $std) {
        $standardsList .= "- " . ($std['standard_code'] ? $std['standard_code'] . ": " : "") . $std['description'] . "\n";
    }
    
    $prompt = <<<PROMPT
Create a course outline for "{$draft['course_name']}" ({$draft['grade']} {$draft['subject']}).

Standards to cover:
{$standardsList}

Organize these standards into 3-5 units with 2-4 lessons each. Return ONLY a JSON array:
[
  {
    "title": "Unit 1: Topic Name",
    "description": "Brief unit description",
    "lessons": [
      {"title": "Lesson 1", "description": "What students will learn", "standard_code": "S1"},
      {"title": "Lesson 2", "description": "What students will learn", "standard_code": "S2"}
    ]
  }
]
PROMPT;

    error_log("[generateOutlineWithLLM] Calling Outline Generator agent for draft {$draft['draft_id']}");
    $agentResponse = callSystemAgent('outline', $prompt);
    
    if (isset($agentResponse['_system_agent'])) {
        error_log("[generateOutlineWithLLM] Using agent: " . $agentResponse['_system_agent']['agent_name']);
    }
    
    if (!empty($agentResponse['response'])) {
        $resp = trim($agentResponse['response']);
        error_log("[generateOutlineWithLLM] Agent response length: " . strlen($resp) . " chars");
        
        // Try to extract JSON array from response
        if (preg_match('/\[.*\]/s', $resp, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded && is_array($decoded)) {
                error_log("[generateOutlineWithLLM] Successfully parsed outline with " . count($decoded) . " units");
                return $decoded;
            }
        }
        
        error_log("[generateOutlineWithLLM] Failed to parse JSON from agent response");
    } else {
        error_log("[generateOutlineWithLLM] Agent returned empty response");
    }
    
    return [];
}
