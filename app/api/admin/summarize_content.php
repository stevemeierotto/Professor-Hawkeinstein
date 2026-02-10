<?php
/**
 * Content Extraction API
 * Uses Professor Hawkeinstein Agent Service to extract clean instructional content
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

if (!isset($input['content_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Content ID required']);
    exit;
}

$contentId = intval($input['content_id']);
$agentId = isset($input['agent_id']) ? intval($input['agent_id']) : 1;

try {
    $db = getDB();
    
    // Get content
    $stmt = $db->prepare("SELECT content_text, title, subject, grade_level FROM educational_content WHERE content_id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();
    
    if (!$content) {
        http_response_code(404);
        echo json_encode(['error' => 'Content not found']);
        exit;
    }
    
    // Phase 1: Basic regex cleaning
    $regexCleaned = cleanContentWithRegex($content['content_text']);
    
    // Phase 2: AI extraction of instructional content
    $extractedContent = extractInstructionalContent($agentId, $regexCleaned, $content['title'], $content['subject'], $content['grade_level']);
    
    // Phase 3: Generate human-readable summary for preview
    $summary = generatePreviewSummary($agentId, $extractedContent, $content['title']);
    
    // Store both extracted content and summary
    $updateStmt = $db->prepare("
        UPDATE educational_content 
        SET cleaned_text = ?, content_summary = ?
        WHERE content_id = ?
    ");
    $updateStmt->execute([$extractedContent, $summary, $contentId]);
    
    echo json_encode([
        'success' => true,
        'content_id' => $contentId,
        'extracted_content' => $extractedContent,
        'summary' => $summary,
        'original_chars' => strlen($content['content_text']),
        'extracted_chars' => strlen($extractedContent),
        'summary_chars' => strlen($summary)
    ]);
    
} catch (Exception $e) {
    error_log("Content extraction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to extract content: ' . $e->getMessage()]);
}

/**
 * Phase 1: Basic regex-based cleaning
 */
function cleanContentWithRegex($text) {
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove common website elements
    $noise_patterns = [
        '/sign\s+in.*?join\s+now/i',
        '/login.*?logout/i',
        '/cookie\s+policy|privacy\s+policy|terms\s+of\s+service/i',
        '/advertisement|sponsored|sponsored\s+content/i',
        '/follow\s+us|social\s+media|share\s+this/i',
        '/click\s+here|read\s+more|learn\s+more(?!\s+about)/i',
        '/loading\.\.\.|loading/i',
        '/back\s+to\s+top|go\s+to\s+main|skip\s+to/i',
        '/recommendations|awards|games|videos|fluency\s+zone/i',
        '/dashboard|menu|navigation|breadcrumb/i',
    ];
    
    foreach ($noise_patterns as $pattern) {
        $text = preg_replace($pattern, '', $text);
    }
    
    // Remove multiple spaces
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Phase 2: AI-powered extraction of instructional content only
 */
function extractInstructionalContent($agentId, $text, $title, $subject, $gradeLevel) {
    // If text is very short, return as-is
    if (strlen($text) < 200) {
        return $text;
    }
    
    // Truncate to 5000 chars for processing
    $textForExtraction = substr($text, 0, 5000);
    
    // Build content extraction prompt - VERY strict about preserving all items
    $prompt = "Copy ONLY the educational skill list from the text below. Preserve ALL items exactly as written.

CRITICAL RULES:
1. Keep EVERY section header (A., B., C., D., etc.)
2. Keep EVERY numbered item (1, 2, 3, 4, etc.) with its full description
3. Keep the exact wording - do NOT summarize or combine items
4. Preserve the structure and hierarchy

REMOVE ONLY:
- \"Sign in\", \"Skip to content\", navigation menus
- \"Recommendations\", \"Awards\", \"Games\", \"Videos\"
- Buttons, ads, UI elements
- Emojis and decorative symbols

EXAMPLE INPUT:
A. Counting
1 Count to 10
2 Count to 20
B. Addition
1 Add single digits
2 Add with pictures

EXAMPLE OUTPUT (exact copy):
A. Counting
1 Count to 10
2 Count to 20
B. Addition
1 Add single digits
2 Add with pictures

Now extract from this text:
{$textForExtraction}

Educational content:";

    try {
        error_log("Calling Content Extraction Agent (ID: $agentId)");
        
        $response = callAgentService('/agent/chat', [
            'userId' => 1,
            'agentId' => $agentId,
            'message' => $prompt
        ]);
        
        if ($response && isset($response['response']) && !empty(trim($response['response']))) {
            $extracted = trim($response['response']);
            
            // Post-process: Remove ONLY meta-commentary, not content
            $unwanted_patterns = [
                '/^.*?(?:You have completed|Thank you for|Good luck with|Let us know|The summary is|This is the).*?$/im',
                '/🚀|📚|👨‍💻|👩‍🎓|👨‍🏫|✅|🎯|��/u',
            ];
            
            foreach ($unwanted_patterns as $pattern) {
                $extracted = preg_replace($pattern, '', $extracted);
            }
            
            // Clean up extra whitespace but preserve structure
            $extracted = preg_replace('/\n{3,}/', "\n\n", $extracted);
            $extracted = trim($extracted);
            
            error_log("Extraction successful: " . strlen($extracted) . " chars");
            return $extracted;
        }
        
        error_log("Agent returned empty response, using regex-cleaned version");
        return $textForExtraction;
        
    } catch (Exception $e) {
        error_log("Extraction failed: " . $e->getMessage());
        return $textForExtraction;
    }
}

/**
 * Phase 3: Generate short preview summary from extracted content
 */
function generatePreviewSummary($agentId, $extractedContent, $title) {
    // If extracted content is short, use it as summary
    if (strlen($extractedContent) < 500) {
        return $extractedContent;
    }
    
    // Truncate extracted content for summary generation
    $contentForSummary = substr($extractedContent, 0, 3000);
    
    // Calculate appropriate summary length
    $wordCount = str_word_count($contentForSummary);
    if ($wordCount < 150) {
        $summaryLength = "50-75 words";
    } elseif ($wordCount < 500) {
        $summaryLength = "75-100 words";
    } else {
        $summaryLength = "100-150 words";
    }
    
    $prompt = "Summarize this educational content in {$summaryLength}. List the main topics covered. No meta-commentary.

{$contentForSummary}

Summary:";

    try {
        $response = callAgentService('/agent/chat', [
            'userId' => 1,
            'agentId' => $agentId,
            'message' => $prompt
        ]);
        
        if ($response && isset($response['response']) && !empty(trim($response['response']))) {
            return trim($response['response']);
        }
        
        // Fallback: return first 500 chars
        return substr($extractedContent, 0, 500) . '...';
        
    } catch (Exception $e) {
        return substr($extractedContent, 0, 500) . '...';
    }
}

?>
