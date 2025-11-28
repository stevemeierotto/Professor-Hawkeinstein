<?php
/**
 * Web Scraper API
 * Scrapes educational content from URLs with metadata capture
 * Now supports Common Standards Project API integration
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/Professor_Hawkeinstein/logs/scraper_errors.log');

header('Content-Type: application/json');
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/scraper_csp.php';

// Require admin authorization
$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = getJSONInput();

// Check source type
$sourceType = $input['source_type'] ?? 'url';

// Route to appropriate scraper
if ($sourceType === 'csp_api') {
    // Common Standards Project API scraping
    if (!isset($input['state']) || !isset($input['grade_level']) || !isset($input['subject_area'])) {
        http_response_code(400);
        echo json_encode(['error' => 'State, grade level, and subject area are required for CSP API']);
        exit;
    }
    
    // Map state codes to CSP jurisdiction IDs
    $jurisdictionMap = [
        'AK' => '0DCD3CBE12314408BDBDB97FAF45EEE8', // Alaska
        // Add more states as needed
    ];
    
    $stateCode = $input['state'];
    $jurisdictionId = $jurisdictionMap[$stateCode] ?? null;
    
    if (!$jurisdictionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported state: ' . $stateCode . '. Currently only Alaska (AK) is supported.']);
        exit;
    }
    
    $gradeLevel = $input['grade_level'];
    $subjectArea = $input['subject_area'];
    
    try {
        // Scrape from CSP API using jurisdiction ID
        $result = scrapeCSPStandards($jurisdictionId, $gradeLevel, $subjectArea);
        
        if (!$result['success']) {
            http_response_code(500);
            echo json_encode([
                'error' => $result['error'],
                'debug' => $result
            ]);
            exit;
        }
        
        // Store standards in database
        $storeResult = storeCSPStandards($result['standards'], $admin['userId']);
        
        if (!$storeResult['success']) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to store standards']);
            exit;
        }
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Standards scraped successfully from Common Standards Project',
            'standards_count' => $result['count'],
            'jurisdiction' => $result['jurisdiction'],
            'grade' => $result['grade'],
            'subject' => $result['subject'],
            'inserted_count' => $storeResult['inserted_count']
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log('CSP API scraper error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'CSP API scraping failed: ' . $e->getMessage()]);
        exit;
    }
}

// Traditional URL scraping (existing code continues below)
if (!isset($input['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL is required']);
    exit;
}

$url = $input['url'];
$gradeLevel = $input['grade_level'] ?? null;
$subjectArea = $input['subject_area'] ?? null;

// Validate URL
$validatedUrl = validateScraperUrl($url);
if (!$validatedUrl) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or unsafe URL']);
    exit;
}

try {
    // Scrape the URL
    $result = scrapeUrl($validatedUrl);
    
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['error' => $result['error']]);
        exit;
    }
    
    // Extract domain for credibility scoring
    $parsedUrl = parse_url($validatedUrl);
    $domain = $parsedUrl['host'] ?? '';
    
    // Calculate basic credibility score based on domain
    $credibilityScore = calculateCredibilityScore($domain);
    
    // Store in database
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO scraped_content (
            url, title, content_text, content_html, 
            metadata, credibility_score, grade_level, subject
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $metadata = json_encode([
        'scrape_timestamp' => date('Y-m-d H:i:s'),
        'content_length' => strlen($result['raw_content']),
        'text_length' => strlen($result['extracted_text']),
        'has_images' => $result['has_images'],
        'has_links' => $result['has_links'],
        'headers_found' => $result['headers_found'],
        'domain' => $domain,
        'scraped_by' => $admin['userId']
    ]);
    
    $stmt->execute([
        $validatedUrl,
        $result['page_title'],
        $result['extracted_text'],
        $result['raw_content'],
        $metadata,
        $credibilityScore,
        $gradeLevel,
        $subjectArea
    ]);
    
    $contentId = $db->lastInsertId();
    
    // Log admin action
    logAdminAction(
        $admin['userId'],
        'CONTENT_SCRAPED',
        "Scraped content from: $validatedUrl",
        ['content_id' => $contentId, 'domain' => $domain]
    );
    
    echo json_encode([
        'success' => true,
        'content_id' => $contentId,
        'page_title' => $result['page_title'],
        'text_length' => strlen($result['extracted_text']),
        'credibility_score' => $credibilityScore,
        'domain' => $domain
    ]);
    
} catch (Exception $e) {
    error_log("Scraper error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to scrape content: ' . $e->getMessage()]);
}

/**
 * Scrape content from a URL
 * 
 * @param string $url URL to scrape
 * @return array Result with success status and content
 */
function scrapeUrl($url) {
    // Use cURL for HTTP GET request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Educational Content Bot) Professor Hawkeinstein/1.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: en-US,en;q=0.9'
        ]
    ]);
    
    $rawContent = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($rawContent === false) {
        return ['success' => false, 'error' => 'Failed to fetch URL: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'HTTP ' . $httpCode . ' error from URL'];
    }
    
    if (empty($rawContent)) {
        return ['success' => false, 'error' => 'Empty response from URL'];
    }
    
    // Parse HTML and extract content
    $extracted = extractContent($rawContent);
    
    return [
        'success' => true,
        'raw_content' => $rawContent,
        'extracted_text' => $extracted['text'],
        'page_title' => $extracted['title'],
        'has_images' => $extracted['has_images'],
        'has_links' => $extracted['has_links'],
        'headers_found' => $extracted['headers_found']
    ];
}

/**
 * Extract and clean content from HTML (using regex - no DOM required)
 * 
 * @param string $html Raw HTML content
 * @return array Extracted content components
 */
function extractContent($html) {
    // Extract page title
    $title = 'Untitled Page';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
    }
    
    // Remove script and style tags with their content
    $text = preg_replace('/<script[^>]*?>.*?<\/script>/is', '', $html);
    $text = preg_replace('/<style[^>]*?>.*?<\/style>/is', '', $text);
    $text = preg_replace('/<noscript[^>]*?>.*?<\/noscript>/is', '', $text);
    
    // Extract body content if available
    if (preg_match('/<body[^>]*?>(.*?)<\/body>/is', $text, $matches)) {
        $text = $matches[1];
    }
    
    // Remove all HTML tags
    $text = strip_tags($text);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Clean up whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Check for images and links
    $hasImages = preg_match('/<img[^>]*>/i', $html) === 1;
    $hasLinks = preg_match('/<a[^>]*>/i', $html) === 1;
    
    // Count headers (h1-h6)
    $headersFound = preg_match_all('/<h[1-6][^>]*>/i', $html);
    
    return [
        'title' => $title,
        'text' => $text,
        'has_images' => $hasImages,
        'has_links' => $hasLinks,
        'headers_found' => $headersFound
    ];
}

/**
 * Calculate credibility score based on domain
 * 
 * @param string $domain Domain name
 * @return float Credibility score 0.00-1.00
 */
function calculateCredibilityScore($domain) {
    $domain = strtolower($domain);
    
    // High credibility domains
    $highCredibility = [
        '.edu', '.gov', 'khanacademy.org', 'commoncore.org',
        'nctm.org', 'nih.gov', 'nasa.gov', 'si.edu'
    ];
    
    // Medium credibility domains
    $mediumCredibility = [
        'wikipedia.org', 'britannica.com', 'mathisfun.com',
        'education.com', 'scholastic.com'
    ];
    
    // Check for high credibility
    foreach ($highCredibility as $trusted) {
        if (strpos($domain, $trusted) !== false) {
            return 0.95;
        }
    }
    
    // Check for medium credibility
    foreach ($mediumCredibility as $medium) {
        if (strpos($domain, $medium) !== false) {
            return 0.75;
        }
    }
    
    // Default credibility - needs review
    return 0.50;
}
