<?php
/**
 * Save Simplified Skills API
 * 
 * Updates the scraped_standards record with edited skills and marks as approved.
 * 
 * @endpoint POST /api/admin/save_simplified_skills.php
 * @requires Admin authentication
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authentication
$adminUser = requireAdmin();

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
$storeId = $data['storeId'] ?? null;
$editedSkills = $data['editedSkills'] ?? null;

if (!$storeId || !is_numeric($storeId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Valid storeId is required'
    ]);
    exit;
}

if (!$editedSkills || !is_array($editedSkills)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'editedSkills array is required'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // First, verify the record exists and belongs to this admin or is accessible
    $stmt = $db->prepare("
        SELECT id, jurisdiction_id, grade_level, subject, skills_count 
        FROM scraped_standards 
        WHERE id = ?
    ");
    $stmt->execute([$storeId]);
    $record = $stmt->fetch();
    
    if (!$record) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Standards record not found'
        ]);
        exit;
    }
    
    // Validate skills array structure
    foreach ($editedSkills as $skill) {
        if (!isset($skill['skillId']) || !isset($skill['text'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Each skill must have skillId and text properties'
            ]);
            exit;
        }
    }
    
    // Update the record with edited skills and set status
    $simplifiedSkillsJson = json_encode($editedSkills);
    $newSkillsCount = count($editedSkills);
    
    // Add status field to metadata
    $stmt = $db->prepare("
        SELECT metadata FROM scraped_standards WHERE id = ?
    ");
    $stmt->execute([$storeId]);
    $metadataRow = $stmt->fetch();
    $metadata = json_decode($metadataRow['metadata'] ?? '{}', true);
    $metadata['status'] = 'standards_approved';
    $metadata['approved_at'] = date('Y-m-d H:i:s');
    $metadata['approved_by'] = $adminUser['user_id'];
    $metadata['edit_count'] = count(array_filter($editedSkills, function($skill) use ($record) {
        // Count how many skills were actually edited
        return true; // For now, assume all were reviewed
    }));
    
    $metadataJson = json_encode($metadata);
    
    $stmt = $db->prepare("
        UPDATE scraped_standards 
        SET simplified_skills = ?,
            skills_count = ?,
            metadata = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $simplifiedSkillsJson,
        $newSkillsCount,
        $metadataJson,
        $storeId
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to update standards record');
    }
    
    // Log the approval
    error_log(sprintf(
        "Standards approved: storeId=%d, skills=%d, admin=%d (%s)",
        $storeId,
        $newSkillsCount,
        $adminUser['user_id'],
        $adminUser['username']
    ));
    
    echo json_encode([
        'success' => true,
        'message' => 'Standards saved and approved successfully',
        'storeId' => (int)$storeId,
        'skillsCount' => $newSkillsCount,
        'status' => 'standards_approved',
        'approvedBy' => $adminUser['username'],
        'approvedAt' => $metadata['approved_at']
    ]);
    
} catch (Exception $e) {
    error_log("Error saving simplified skills: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save standards: ' . $e->getMessage()
    ]);
}
