<?php
/**
 * Scrape Lesson Content API
 * 
 * Scrapes educational content from Wikipedia, CK-12, or custom URLs
 * and links it to a specific lesson in a course draft.
 * 
 * POST /api/admin/scrape_lesson_content.php
 * {
 *   "draftId": 2,
 *   "unitIndex": 0,
 *   "lessonIndex": 3,
 *   "lessonTitle": "Properties of Matter",
 *   "source": "wikipedia" | "ck12" | "custom",
 *   "customUrl": "https://..." (only if source=custom),
 *   "searchQuery": "optional override for search term"
 * }
 */

require_once '../../config/database.php';
require_once 'auth_check.php';
require_once '../helpers/system_agent_helper.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['draftId', 'unitIndex', 'lessonIndex', 'lessonTitle', 'source'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$draftId = (int)$input['draftId'];
$unitIndex = (int)$input['unitIndex'];
$lessonIndex = (int)$input['lessonIndex'];
$lessonTitle = $input['lessonTitle'];
$source = $input['source'];
$customUrl = $input['customUrl'] ?? null;
$searchQuery = $input['searchQuery'] ?? $lessonTitle;

$db = getDb();

// Verify draft exists
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    echo json_encode(['success' => false, 'message' => 'Draft not found']);
    exit;
}

try {
    // Determine URL to scrape
    $url = null;
    $searchUrl = null;
    
    switch ($source) {
        case 'wikipedia':
            // Wikipedia API search
            $result = scrapeWikipedia($searchQuery, $draft['grade'], $draft['subject']);
            break;
            
        case 'ck12':
            // CK-12 scraping
            $result = scrapeCK12($searchQuery, $draft['grade'], $draft['subject']);
            break;
            
        case 'custom':
            if (empty($customUrl)) {
                echo json_encode(['success' => false, 'message' => 'Custom URL required when source=custom']);
                exit;
            }
            $result = scrapeCustomUrl($customUrl);
            break;
            
        case 'generate':
            // LLM generation - create content directly from standard
            $result = generateWithLLM($lessonTitle, $searchQuery, $draft['grade'], $draft['subject']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid source. Use: wikipedia, ck12, custom, or generate']);
            exit;
    }
    
    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }
    
    // Delete existing content for this lesson before adding new
    // First get the content_ids that are linked to this lesson
    $stmt = $db->prepare("
        SELECT content_id FROM draft_lesson_content 
        WHERE draft_id = ? AND unit_index = ? AND lesson_index = ?
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
    $existingContentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete the links
    $stmt = $db->prepare("
        DELETE FROM draft_lesson_content 
        WHERE draft_id = ? AND unit_index = ? AND lesson_index = ?
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
    
    // Delete the old scraped content (only if it was specifically for this lesson)
    if (!empty($existingContentIds)) {
        $placeholders = implode(',', array_fill(0, count($existingContentIds), '?'));
        $stmt = $db->prepare("
            DELETE FROM scraped_content 
            WHERE content_id IN ($placeholders)
            AND JSON_EXTRACT(metadata, '$.draft_id') = ?
            AND JSON_EXTRACT(metadata, '$.unit_index') = ?
            AND JSON_EXTRACT(metadata, '$.lesson_index') = ?
        ");
        $params = array_merge($existingContentIds, [$draftId, $unitIndex, $lessonIndex]);
        $stmt->execute($params);
    }
    
    // Store new generated content
    $stmt = $db->prepare("
        INSERT INTO scraped_content (
            url, title, content_text, content_html, metadata, 
            credibility_score, grade_level, subject, content_type, scraped_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'educational', ?)
    ");
    
    $metadata = json_encode([
        'source' => $source,
        'search_query' => $searchQuery,
        'lesson_title' => $lessonTitle,
        'draft_id' => $draftId,
        'unit_index' => $unitIndex,
        'lesson_index' => $lessonIndex,
        'scrape_timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $adminId = getAdminId();
    $stmt->execute([
        $result['url'],
        $result['title'],
        $result['content'],
        $result['html'] ?? '',
        $metadata,
        $result['credibility'] ?? 0.75,
        $draft['grade'],
        $draft['subject'],
        $adminId
    ]);
    
    $contentId = $db->lastInsertId();
    
    // Link content to lesson
    $stmt = $db->prepare("
        INSERT INTO draft_lesson_content (draft_id, unit_index, lesson_index, content_id, relevance_score)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE content_id = VALUES(content_id), relevance_score = VALUES(relevance_score)
    ");
    $stmt->execute([$draftId, $unitIndex, $lessonIndex, $contentId, $result['relevance'] ?? 0.80]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Content scraped and linked to lesson',
        'contentId' => $contentId,
        'title' => $result['title'],
        'url' => $result['url'],
        'contentLength' => strlen($result['content']),
        'source' => $source
    ]);
    
} catch (Exception $e) {
    error_log("Scrape lesson content error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Scraping failed: ' . $e->getMessage()]);
}

/**
 * Scrape content from Wikipedia using their API
 */
function scrapeWikipedia($searchQuery, $grade, $subject) {
    // Step 1: Search for best matching article
    $searchUrl = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'opensearch',
        'search' => $searchQuery,
        'limit' => 5,
        'namespace' => 0,
        'format' => 'json'
    ]);
    
    $searchResult = fetchUrl($searchUrl);
    if (!$searchResult['success']) {
        return ['success' => false, 'message' => 'Wikipedia search failed: ' . $searchResult['error']];
    }
    
    $searchData = json_decode($searchResult['content'], true);
    if (empty($searchData[1])) {
        return ['success' => false, 'message' => 'No Wikipedia articles found for: ' . $searchQuery];
    }
    
    // Get the first result
    $articleTitle = $searchData[1][0];
    $articleUrl = $searchData[3][0] ?? "https://en.wikipedia.org/wiki/" . urlencode(str_replace(' ', '_', $articleTitle));
    
    // Step 2: Get article content (plain text extract)
    $contentUrl = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'titles' => $articleTitle,
        'prop' => 'extracts|info',
        'exintro' => false,  // Get full article, not just intro
        'explaintext' => true,  // Plain text
        'exsectionformat' => 'plain',
        'inprop' => 'url',
        'format' => 'json'
    ]);
    
    $contentResult = fetchUrl($contentUrl);
    if (!$contentResult['success']) {
        return ['success' => false, 'message' => 'Wikipedia content fetch failed'];
    }
    
    $contentData = json_decode($contentResult['content'], true);
    $pages = $contentData['query']['pages'] ?? [];
    $page = array_values($pages)[0] ?? null;
    
    if (!$page || isset($page['missing'])) {
        return ['success' => false, 'message' => 'Wikipedia article not found'];
    }
    
    $extract = $page['extract'] ?? '';
    
    // Limit content length for educational use (first ~5000 chars is usually enough)
    if (strlen($extract) > 8000) {
        $extract = substr($extract, 0, 8000) . "\n\n[Content truncated for educational use]";
    }
    
    return [
        'success' => true,
        'url' => $articleUrl,
        'title' => $articleTitle . ' (Wikipedia)',
        'content' => $extract,
        'html' => '',
        'credibility' => 0.75,  // Wikipedia is medium-high credibility
        'relevance' => 0.85
    ];
}

/**
 * Scrape content from CK-12
 */
function scrapeCK12($searchQuery, $grade, $subject) {
    // CK-12 doesn't have a public API, so we'll construct a search URL and scrape
    $searchUrl = 'https://www.ck12.org/search/?q=' . urlencode($searchQuery);
    
    // For now, we'll scrape the search page and try to find relevant content
    // In production, you might want to use their partner API if available
    
    $result = fetchUrl($searchUrl);
    if (!$result['success']) {
        return ['success' => false, 'message' => 'CK-12 fetch failed: ' . $result['error']];
    }
    
    // Parse the page to extract content
    $html = $result['content'];
    
    // Extract title
    $title = 'CK-12 Educational Content';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
    }
    
    // Extract main content (simplified)
    $text = extractTextContent($html);
    
    if (strlen($text) < 100) {
        return ['success' => false, 'message' => 'Could not extract meaningful content from CK-12'];
    }
    
    return [
        'success' => true,
        'url' => $searchUrl,
        'title' => $title,
        'content' => $text,
        'html' => $html,
        'credibility' => 0.90,  // CK-12 is high credibility educational content
        'relevance' => 0.80
    ];
}

/**
 * Scrape content from a custom URL
 */
function scrapeCustomUrl($url) {
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'Invalid URL format'];
    }
    
    // Check for blocked domains
    $blockedDomains = ['facebook.com', 'twitter.com', 'instagram.com', 'tiktok.com'];
    $parsedUrl = parse_url($url);
    $domain = $parsedUrl['host'] ?? '';
    
    foreach ($blockedDomains as $blocked) {
        if (stripos($domain, $blocked) !== false) {
            return ['success' => false, 'message' => 'Social media sites are not allowed'];
        }
    }
    
    $result = fetchUrl($url);
    if (!$result['success']) {
        return ['success' => false, 'message' => 'Failed to fetch URL: ' . $result['error']];
    }
    
    $html = $result['content'];
    
    // Extract title
    $title = 'Untitled Page';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
    }
    
    // Extract text content
    $text = extractTextContent($html);
    
    // Calculate credibility based on domain
    $credibility = calculateDomainCredibility($domain);
    
    return [
        'success' => true,
        'url' => $url,
        'title' => $title,
        'content' => $text,
        'html' => $html,
        'credibility' => $credibility,
        'relevance' => 0.70  // Custom URLs have unknown relevance
    ];
}

/**
 * Fetch URL using cURL
 */
function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Educational Bot) ProfessorHawkeinstein/1.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/json,application/xhtml+xml',
            'Accept-Language: en-US,en;q=0.9'
        ]
    ]);
    
    $content = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($content === false) {
        return ['success' => false, 'error' => $error];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }
    
    return ['success' => true, 'content' => $content];
}

/**
 * Extract clean text from HTML
 */
function extractTextContent($html) {
    // Remove script and style
    $text = preg_replace('/<script[^>]*?>.*?<\/script>/is', '', $html);
    $text = preg_replace('/<style[^>]*?>.*?<\/style>/is', '', $text);
    $text = preg_replace('/<nav[^>]*?>.*?<\/nav>/is', '', $text);
    $text = preg_replace('/<footer[^>]*?>.*?<\/footer>/is', '', $text);
    $text = preg_replace('/<header[^>]*?>.*?<\/header>/is', '', $text);
    
    // Try to get main/article content
    if (preg_match('/<main[^>]*?>(.*?)<\/main>/is', $text, $matches)) {
        $text = $matches[1];
    } elseif (preg_match('/<article[^>]*?>(.*?)<\/article>/is', $text, $matches)) {
        $text = $matches[1];
    } elseif (preg_match('/<body[^>]*?>(.*?)<\/body>/is', $text, $matches)) {
        $text = $matches[1];
    }
    
    // Strip tags and clean
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Limit length
    if (strlen($text) > 10000) {
        $text = substr($text, 0, 10000) . "\n\n[Content truncated]";
    }
    
    return $text;
}

/**
 * Calculate credibility score based on domain
 */
function calculateDomainCredibility($domain) {
    $domain = strtolower($domain);
    
    // High credibility
    $high = ['.edu', '.gov', 'khanacademy.org', 'ck12.org', 'pbslearningmedia.org', 
             'nationalgeographic.com', 'smithsonianmag.com', 'nasa.gov'];
    foreach ($high as $d) {
        if (strpos($domain, $d) !== false) return 0.95;
    }
    
    // Medium-high credibility
    $mediumHigh = ['wikipedia.org', 'britannica.com', 'scholastic.com', 'education.com'];
    foreach ($mediumHigh as $d) {
        if (strpos($domain, $d) !== false) return 0.80;
    }
    
    // Medium credibility
    $medium = ['mathisfun.com', 'sciencekids.co.nz', 'ducksters.com'];
    foreach ($medium as $d) {
        if (strpos($domain, $d) !== false) return 0.70;
    }
    
    return 0.50;  // Unknown
}

/**
 * Generate lesson content using LLM
 * 
 * Creates age-appropriate educational content directly from the standard description
 */
function generateWithLLM($lessonTitle, $standardDescription, $grade, $subject) {
    // Extract grade number for age-appropriate language
    $gradeNum = preg_replace('/[^0-9]/', '', $grade) ?: '2';
    $age = 5 + intval($gradeNum); // Approximate age
    
    // Build the user prompt (system prompt comes from the Content Creator agent)
    $prompt = <<<PROMPT
Create educational lesson content for Grade $gradeNum students (age $age) about:
"$standardDescription"

Requirements:
- Use simple vocabulary appropriate for Grade $gradeNum
- Write 8-10 paragraphs (about 800-1000 words total)
- Start with an engaging introduction that explains why this topic matters
- Include detailed explanations with multiple real-world examples kids can relate to
- Break down complex concepts into simple steps
- Provide key vocabulary words with simple definitions
- Cover the topic thoroughly so students fully understand the concept

Write ONLY the lesson content. Do NOT include review questions, activities, experiments, or fun facts - those will be added separately.
PROMPT;

    // Call the Content Creator system agent
    $agentResponse = callSystemAgent('content', $prompt);
    
    // Log which agent was used
    if (isset($agentResponse['_system_agent'])) {
        error_log("Content generated by: " . $agentResponse['_system_agent']['agent_name'] . 
                  " (temp=" . $agentResponse['_system_agent']['temperature'] . 
                  ", max_tokens=" . $agentResponse['_system_agent']['max_tokens'] . ")");
    }

    if (empty($agentResponse['response'])) {
        return ['success' => false, 'message' => 'LLM did not generate content. Is the agent service running?'];
    }

    $content = trim($agentResponse['response']);
    
    // Clean up any markdown artifacts
    $content = preg_replace('/^#+\s*/m', '', $content); // Remove # headers
    
    // Remove any agent name prefixes like "Professor Hawkeinstein:" or "Content Creator:"
    $content = preg_replace('/^(Professor Hawkeinstein|Content Creator|Assistant):\s*/mi', '', $content);
    
    // Remove duplicate concluding paragraphs (sometimes LLM repeats the ending)
    $lines = explode("\n", $content);
    $uniqueLines = [];
    $lastLine = '';
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine !== $lastLine || empty($trimmedLine)) {
            $uniqueLines[] = $line;
            $lastLine = $trimmedLine;
        }
    }
    $content = implode("\n", $uniqueLines);
    
    $content = trim($content);
    
    if (strlen($content) < 50) {
        return ['success' => false, 'message' => 'Generated content too short: ' . $content];
    }

    // Create HTML version
    $html = '<div class="generated-lesson">' . nl2br(htmlspecialchars($content)) . '</div>';
    
    // Generate a clean title
    $cleanTitle = substr($lessonTitle, 0, 80);
    if (strlen($lessonTitle) > 80) $cleanTitle .= '...';
    
    return [
        'success' => true,
        'url' => 'llm://generated/' . time() . '/' . md5($standardDescription),
        'title' => 'Lesson: ' . $cleanTitle . ' (AI Generated)',
        'content' => $content,
        'html' => $html,
        'credibility' => 0.85,
        'relevance' => 0.95
    ];
}
