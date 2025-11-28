<?php
/**
 * Common Standards Project API Scraper
 * Fetches educational standards from the CSP API
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * Scrape standards from Common Standards Project API
 * 
 * @param string $jurisdictionId Jurisdiction ID (e.g., '0DCD3CBE12314408BDBDB97FAF45EEE8' for Alaska)
 * @param string $gradeLevel Grade level identifier
 * @param string $subject Subject area
 * @return array Result with standards data
 */
function scrapeCSPStandards($jurisdictionId, $gradeLevel, $subject) {
    $apiKey = CSP_API_KEY;
    
    if (empty($apiKey)) {
        error_log("[CSP Scraper] ERROR: CSP_API_KEY is empty or not set");
        return [
            'success' => false,
            'error' => 'CSP API key missing from environment. Check .env file.'
        ];
    }
    
    // Map grade levels to CSP format
    $gradeMap = [
        'pre_k' => 'Pre-K',
        'kindergarten' => 'K',
        'grade_1' => '01',
        'grade_2' => '02',
        'grade_3' => '03',
        'grade_4' => '04',
        'grade_5' => '05',
        'grade_6' => '06',
        'grade_7' => '07',
        'grade_8' => '08',
        'grade_9' => '09',
        'grade_10' => '10',
        'grade_11' => '11',
        'grade_12' => '12',
        'middle_school' => '06-08',
        'high_school' => '09-12'
    ];
    
    // Map subjects to CSP subject keywords (case-insensitive search)
    $subjectMap = [
        'mathematics' => 'Mathematics',
        'science' => 'Science',
        'language_arts' => 'English/Language Arts',
        'english' => 'English/Language Arts',
        'social_studies' => 'Social Studies',
        'computer_science' => 'Computer Science',
        'arts' => 'Arts',
        'physical_education' => 'Physical Education'
    ];
    
    $cspGrade = $gradeMap[$gradeLevel] ?? $gradeLevel;
    $cspSubject = $subjectMap[$subject] ?? $subject;
    
    $baseUrl = CSP_API_BASE_URL;
    
    // Step 1: Get jurisdiction details including standardSets
    $jurisdictionUrl = $baseUrl . '/jurisdictions/' . $jurisdictionId;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $jurisdictionUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token token=' . $apiKey,
        'Accept: application/json',
        'User-Agent: ProfessorHawkeinstein/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("CSP API cURL error: " . $curlError);
        return [
            'success' => false,
            'error' => 'Failed to connect to CSP API: ' . $curlError
        ];
    }
    
    if ($httpCode !== 200) {
        error_log("CSP API returned HTTP " . $httpCode . ": " . $response);
        return [
            'success' => false,
            'error' => 'CSP API returned error (HTTP ' . $httpCode . '): ' . substr($response, 0, 200)
        ];
    }
    
    $jurisdictionData = json_decode($response, true);
    
    if (!$jurisdictionData || !isset($jurisdictionData['data']['standardSets'])) {
        return [
            'success' => false,
            'error' => 'Invalid jurisdiction response from CSP API'
        ];
    }
    
    // Step 2: Find matching standard sets based on grade and subject
    $matchingStandardSets = [];
    $standardSets = $jurisdictionData['data']['standardSets'];
    
    foreach ($standardSets as $set) {
        $setSubject = $set['subject'] ?? '';
        $setLevels = $set['educationLevels'] ?? [];
        
        // Match subject (case-insensitive substring match)
        $subjectMatches = stripos($setSubject, $cspSubject) !== false;
        
        // Match grade level
        $gradeMatches = in_array($cspGrade, $setLevels);
        
        if ($subjectMatches && $gradeMatches) {
            $matchingStandardSets[] = $set;
        }
    }
    
    if (empty($matchingStandardSets)) {
        return [
            'success' => false,
            'error' => 'No matching standard sets found for ' . $cspSubject . ' Grade ' . $cspGrade,
            'available_sets' => array_map(function($s) { return $s['title'] . ' (' . $s['subject'] . ')'; }, $standardSets)
        ];
    }
    
    // Step 3: Fetch standards from each matching standard set
    $allStandards = [];
    
    foreach ($matchingStandardSets as $set) {
        $setId = $set['id'];
        $setUrl = $baseUrl . '/standard_sets/' . $setId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $setUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Token token=' . $apiKey,
            'Accept: application/json',
            'User-Agent: ProfessorHawkeinstein/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $setData = json_decode($response, true);
            if (isset($setData['data']['standards']) && is_array($setData['data']['standards'])) {
                foreach ($setData['data']['standards'] as $standard) {
                    $allStandards[] = [
                        'title' => $standard['description'] ?? $standard['statementLabel'] ?? $standard['listId'] ?? 'Untitled Standard',
                        'code' => $standard['listId'] ?? $standard['statementNotation'] ?? 'N/A',
                        'description' => $standard['description'] ?? $standard['statementLabel'] ?? '',
                        'grade' => $cspGrade,
                        'subject' => $cspSubject,
                        'jurisdiction' => $jurisdictionData['data']['title'] ?? 'Alaska',
                        'source_url' => $set['document']['sourceURL'] ?? $setUrl,
                        'document_title' => $set['document']['title'] ?? $set['title'],
                        'metadata' => json_encode($standard)
                    ];
                }
            }
        }
    }
    
    return [
        'success' => true,
        'standards' => $allStandards,
        'count' => count($allStandards),
        'standard_sets_found' => count($matchingStandardSets),
        'jurisdiction' => $jurisdictionData['data']['title'] ?? 'Alaska',
        'grade' => $cspGrade,
        'subject' => $cspSubject
    ];
}

/**
 * Store CSP standards in database as ONE comprehensive entry
 * 
 * @param array $standards Array of standard objects
 * @param int $scrapedBy User ID who initiated the scrape
 * @return array Result with inserted ID
 */
function storeCSPStandards($standards, $scrapedBy) {
    if (empty($standards)) {
        return ['success' => false, 'error' => 'No standards to store'];
    }
    
    $db = getDB();
    
    // Get common attributes from first standard
    $firstStandard = $standards[0];
    $grade = $firstStandard['grade'];
    $subject = $firstStandard['subject'];
    $jurisdiction = $firstStandard['jurisdiction'];
    $sourceUrl = $firstStandard['source_url'];
    
    // Create comprehensive title
    $title = sprintf('%s - Grade %s %s Standards (%d items)', 
                     $jurisdiction, $grade, $subject, count($standards));
    
    // Build combined content text (all descriptions)
    $contentText = '';
    foreach ($standards as $standard) {
        $contentText .= sprintf("[%s] %s\n%s\n\n", 
                               $standard['code'], 
                               $standard['title'],
                               $standard['description']);
    }
    
    // Build HTML with all standards
    $contentHtml = '<div class="standards-collection">';
    $contentHtml .= sprintf('<h2>%s - Grade %s %s</h2>', $jurisdiction, $grade, $subject);
    $contentHtml .= sprintf('<p><strong>Total Standards:</strong> %d</p>', count($standards));
    
    foreach ($standards as $standard) {
        $contentHtml .= '<div class="standard-item" style="margin: 1rem 0; padding: 1rem; border-left: 3px solid #4a90e2;">';
        $contentHtml .= '<h3 style="margin: 0 0 0.5rem 0; color: #4a90e2;">' . htmlspecialchars($standard['code']) . '</h3>';
        $contentHtml .= '<h4 style="margin: 0 0 0.5rem 0;">' . htmlspecialchars($standard['title']) . '</h4>';
        $contentHtml .= '<p style="margin: 0;">' . htmlspecialchars($standard['description']) . '</p>';
        $contentHtml .= '</div>';
    }
    $contentHtml .= '</div>';
    
    // Create metadata with all standards
    $metadata = json_encode([
        'source' => 'Common Standards Project API',
        'jurisdiction' => $jurisdiction,
        'total_standards' => count($standards),
        'scraped_by' => $scrapedBy,
        'scrape_timestamp' => date('Y-m-d H:i:s'),
        'standards' => array_map(function($s) {
            return [
                'code' => $s['code'],
                'title' => $s['title'],
                'description' => $s['description'],
                'document_title' => $s['document_title'] ?? ''
            ];
        }, $standards)
    ]);
    
    // CSP standards have high credibility
    $credibilityScore = 0.95;
    
    $stmt = $db->prepare("
        INSERT INTO scraped_content (
            url, title, content_type, content_text, content_html,
            metadata, credibility_score, grade_level, subject,
            scraped_by, scraped_at
        ) VALUES (?, ?, 'standard', ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $sourceUrl,
        $title,
        $contentText,
        $contentHtml,
        $metadata,
        $credibilityScore,
        $grade,
        $subject,
        $scrapedBy
    ]);
    
    $insertedId = $db->lastInsertId();
    
    return [
        'success' => true,
        'inserted_count' => 1,
        'standards_count' => count($standards),
        'id' => $insertedId
    ];
}
?>
