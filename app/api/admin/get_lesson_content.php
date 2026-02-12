<?php
/**
 * Get Lesson Content API
 * 
 * Returns scraped content linked to a specific lesson
 * 
 * GET /api/admin/get_lesson_content.php?draftId=X&unitIndex=Y&lessonIndex=Z
 * 
 * Or get all content for a draft:
 * GET /api/admin/get_lesson_content.php?draftId=X
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/auth_check.php';
requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_get_lesson_content');

header('Content-Type: application/json');

$draftId = isset($_GET['draftId']) ? (int)$_GET['draftId'] : 0;
$unitIndex = isset($_GET['unitIndex']) ? (int)$_GET['unitIndex'] : null;
$lessonIndex = isset($_GET['lessonIndex']) ? (int)$_GET['lessonIndex'] : null;

if (!$draftId) {
    echo json_encode(['success' => false, 'message' => 'draftId required']);
    exit;
}

$db = getDb();

try {
    if ($unitIndex !== null && $lessonIndex !== null) {
        // Get content for specific lesson - return full content_text for review
        $stmt = $db->prepare("
            SELECT 
                dlc.id as link_id,
                dlc.unit_index,
                dlc.lesson_index,
                dlc.relevance_score,
                dlc.approved,
                dlc.approved_at,
                dlc.added_at,
                sc.content_id,
                sc.url,
                sc.title,
                sc.content_type,
                sc.content_text,
                sc.video_url,
                sc.credibility_score,
                sc.scraped_at
            FROM draft_lesson_content dlc
            JOIN educational_content sc ON dlc.content_id = sc.content_id
            WHERE dlc.draft_id = ? AND dlc.unit_index = ? AND dlc.lesson_index = ?
            ORDER BY dlc.relevance_score DESC
        ");
        $stmt->execute([$draftId, $unitIndex, $lessonIndex]);
        $content = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'draftId' => $draftId,
            'unitIndex' => $unitIndex,
            'lessonIndex' => $lessonIndex,
            'content' => $content,
            'contentCount' => count($content)
        ]);
    } else {
        // Get all content for draft, grouped by lesson
        $stmt = $db->prepare("
            SELECT 
                dlc.unit_index,
                dlc.lesson_index,
                COUNT(*) as content_count,
                MAX(dlc.approved) as approved,
                MAX(dlc.approved_at) as approved_at,
                GROUP_CONCAT(sc.title SEPARATOR '|||') as titles
            FROM draft_lesson_content dlc
            JOIN educational_content sc ON dlc.content_id = sc.content_id
            WHERE dlc.draft_id = ?
            GROUP BY dlc.unit_index, dlc.lesson_index
            ORDER BY dlc.unit_index, dlc.lesson_index
        ");
        $stmt->execute([$draftId]);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format for easier use
        $lessonContent = [];
        foreach ($summary as $row) {
            $key = "u{$row['unit_index']}_l{$row['lesson_index']}";
            $lessonContent[$key] = [
                'unitIndex' => (int)$row['unit_index'],
                'lessonIndex' => (int)$row['lesson_index'],
                'contentCount' => (int)$row['content_count'],
                'approved' => (bool)$row['approved'],
                'approvedAt' => $row['approved_at'],
                'titles' => explode('|||', $row['titles'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'draftId' => $draftId,
            'lessonContent' => $lessonContent,
            'totalLessonsWithContent' => count($summary)
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
