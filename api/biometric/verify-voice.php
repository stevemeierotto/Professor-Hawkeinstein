<?php
/**
 * Voice Authentication Verification API
 * Sends audio to C++ microservice for voice recognition processing
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();
$input = getJSONInput();

$audioData = $input['audioData'] ?? '';
$userId = $input['userId'] ?? $userData['userId'];

if (empty($audioData)) {
    sendJSON(['success' => false, 'message' => 'Audio data required'], 400);
}

if ($userId != $userData['userId']) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 403);
}

try {
    $db = getDB();
    
    // Get user's stored voice signature
    $stmt = $db->prepare("SELECT voice_signature FROM users WHERE user_id = :userId");
    $stmt->execute(['userId' => $userId]);
    $user = $stmt->fetch();
    
    // Call C++ microservice for voice recognition
    $verificationRequest = [
        'userId' => $userId,
        'audioData' => $audioData,
        'storedSignature' => base64_encode($user['voice_signature'] ?? ''),
        'threshold' => VOICE_RECOGNITION_THRESHOLD
    ];
    
    $result = callAgentService('/api/biometric/verify-voice', $verificationRequest);
    
    if (!$result['success']) {
        $sessionStmt = $db->prepare("
            UPDATE sessions 
            SET cheating_flags = cheating_flags + 1 
            WHERE user_id = :userId AND is_active = 1
        ");
        $sessionStmt->execute(['userId' => $userId]);
        
        logActivity($userId, 'BIOMETRIC_FAIL', 'Voice recognition failed');
        
        sendJSON([
            'verified' => false,
            'confidence' => $result['confidence'] ?? 0,
            'message' => 'Voice not recognized'
        ]);
    }
    
    // Update session
    $sessionStmt = $db->prepare("
        UPDATE sessions 
        SET voice_verified = 1, last_activity = NOW()
        WHERE user_id = :userId AND is_active = 1
    ");
    $sessionStmt->execute(['userId' => $userId]);
    
    sendJSON([
        'verified' => true,
        'confidence' => $result['confidence'] ?? 1.0,
        'message' => 'Voice verified successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Voice verification error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Verification failed'], 500);
}
