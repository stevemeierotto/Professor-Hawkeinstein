<?php
/**
 * Generate Standards API
 * 
 * Uses the Standards Analyzer Agent to generate educational standards
 * for a given subject and grade level.
 * 
 * POST /api/admin/generate_standards.php
 * {
 *   "subject": "Science",
 *   "grade": "3rd Grade"
 * }
 * 
 * Returns:
 * {
 *   "success": true,
 *   "standards": [
 *     {"id": "S1", "statement": "...", "skills": ["skill1", "skill2"]},
 *     ...
 *   ],
 *   "count": 10,
 *   "metadata": {...}
 * }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../helpers/system_agent_helper.php';
requireAdmin();


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['subject']) || empty($input['grade'])) {
    echo json_encode(['success' => false, 'message' => 'Subject and grade are required']);
    exit;
}

$subject = trim($input['subject']);
$grade = trim($input['grade']);

try {
    // Build detailed prompt - small LLMs need explicit instructions
    $prompt = <<<PROMPT
Create 8-12 educational standards for {$grade} {$subject}.

Each standard must include:
- A unique ID (like S1, S2, S3...)
- A clear statement of what students should learn
- 2-3 specific skills

Return ONLY a JSON array like this:
[
  {"id": "S1", "statement": "Students will understand...", "skills": ["skill 1", "skill 2"]},
  {"id": "S2", "statement": "Students will be able to...", "skills": ["skill 1", "skill 2"]}
]

Generate the standards now:
PROMPT;

    // Call the Standards Analyzer system agent
    $agentResponse = callSystemAgent('standards', $prompt);
    
    if (empty($agentResponse['response'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Standards Analyzer Agent did not generate a response. Is the agent service running?'
        ]);
        exit;
    }
    
    $rawResponse = trim($agentResponse['response']);
    
    // Log for debugging
    error_log("[Generate Standards] Agent response: " . substr($rawResponse, 0, 500));
    
    // Note: Timestamp and markdown cleaning now handled by cleanAgentResponse() in callSystemAgent()
    
    // Remove any title/header lines before JSON (e.g., "3rd Grade Science Standards")
    // Look for lines that don't start with [ or {
    $lines = explode("\n", $rawResponse);
    $cleanedResponse = '';
    $foundJson = false;
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (!$foundJson && ($trimmedLine[0] === '[' || $trimmedLine[0] === '{')) {
            $foundJson = true;
        }
        if ($foundJson) {
            $cleanedResponse .= $line . "\n";
        }
    }
    $rawResponse = trim($cleanedResponse ?: $rawResponse);
    
    // Parse the JSON response - agent should return JSON but may return plain text
    $parsed = json_decode($rawResponse, true);
    
    // If direct parse fails, try to extract JSON object
    if (!is_array($parsed)) {
        if (preg_match('/\{[\s\S]*\}/m', $rawResponse, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
    }
    
    // If still no JSON, try to extract JSON array
    if (!is_array($parsed)) {
        if (preg_match('/\[[\s\S]*\]/m', $rawResponse, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
    }
    
    // Fallback: Parse plain text format
    if (!is_array($parsed)) {
        error_log("[Generate Standards] Parsing as plain text...");
        $lines = array_filter(array_map('trim', explode("\n", $rawResponse)));
        $parsed = [];
        $counter = 1;
        foreach ($lines as $line) {
            // Match numbered list: "1. Description" or "1) Description"
            if (preg_match('/^(\d+)[.)]\s*(.+)$/i', $line, $m)) {
                $parsed[] = [
                    'id' => 'S' . $m[1],
                    'statement' => $m[2],
                    'skills' => []
                ];
            }
            // Match patterns like "6.1a: Description" or "S1: Description"
            elseif (preg_match('/^([A-Za-z0-9]+\.?[0-9]*[a-z]?):\s*(.+)$/i', $line, $m)) {
                $parsed[] = [
                    'id' => $m[1],
                    'statement' => $m[2],
                    'skills' => []
                ];
            }
        }
    }
    
    if (!is_array($parsed) || empty($parsed)) {
        error_log("[Generate Standards] Failed to parse response: " . $rawResponse);
        echo json_encode([
            'success' => false, 
            'message' => 'Could not parse standards from agent response',
            'raw_response' => substr($rawResponse, 0, 500)
        ]);
        exit;
    }
    
    // Extract standards array from response
    $standards = $parsed['standards'] ?? $parsed;
    
    if (!is_array($standards) || empty($standards)) {
        echo json_encode([
            'success' => false,
            'message' => 'No standards found in agent response'
        ]);
        exit;
    }
    
    // Normalize standards format for the UI
    $normalizedStandards = [];
    foreach ($standards as $idx => $std) {
        $normalizedStandards[] = [
            'id' => $std['id'] ?? ('S' . ($idx + 1)),
            'code' => $std['id'] ?? ('S' . ($idx + 1)),
            'statement' => $std['statement'] ?? '',
            'description' => $std['statement'] ?? '',
            'skills' => $std['skills'] ?? []
        ];
    }
    
    // Filter out empty standards
    $normalizedStandards = array_filter($normalizedStandards, function($s) {
        return !empty($s['statement']);
    });
    $normalizedStandards = array_values($normalizedStandards);
    
    echo json_encode([
        'success' => true,
        'standards' => $normalizedStandards,
        'count' => count($normalizedStandards),
        'metadata' => [
            'subject' => $subject,
            'grade' => $grade,
            'generated_at' => date('Y-m-d H:i:s'),
            'agent' => $agentResponse['_system_agent']['agent_name'] ?? 'Standards Analyzer'
        ]
    ]);

} catch (Exception $e) {
    error_log("[Generate Standards] Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error generating standards: ' . $e->getMessage()
    ]);
}
