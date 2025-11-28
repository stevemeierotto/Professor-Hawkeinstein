<?php
/**
 * Facial Recognition Verification API
 * Sends frame to C++ microservice for facial recognition processing
 */

require_once '../../config/database.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userData = requireAuth();
$input = getJSONInput();

$frame = $input['frame'] ?? '';
$userId = $input['userId'] ?? $userData['userId'];

if (empty($frame)) {
    sendJSON(['success' => false, 'message' => 'Frame data required'], 400);
}

// Ensure user can only verify their own face (unless admin)
if ($userId != $userData['userId']) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 403);
}

try {
    $db = getDB();
    
    // Get user's stored facial signature
    $stmt = $db->prepare("SELECT facial_signature FROM users WHERE user_id = :userId");
    $stmt->execute(['userId' => $userId]);
    $user = $stmt->fetch();
    
    // Call C++ microservice for facial recognition
    $verificationRequest = [
        'userId' => $userId,
        'frameData' => $frame,
        'storedSignature' => base64_encode($user['facial_signature'] ?? ''),
        'threshold' => FACIAL_RECOGNITION_THRESHOLD
    ];
    
    $result = callAgentService('/api/biometric/verify-face', $verificationRequest);
    
    if (!$result['success']) {
        // Log failed verification
        $sessionStmt = $db->prepare("
            UPDATE sessions 
            SET cheating_flags = cheating_flags + 1 
            WHERE user_id = :userId AND is_active = 1
        ");
        $sessionStmt->execute(['userId' => $userId]);
        
        logActivity($userId, 'BIOMETRIC_FAIL', 'Facial recognition failed');
        
        sendJSON([
            'verified' => false,
            'confidence' => $result['confidence'] ?? 0,
            'message' => 'Face not recognized'
        ]);
    }
    
    // Update session with successful verification
    $sessionStmt = $db->prepare("
        UPDATE sessions 
        SET facial_verified = 1, last_activity = NOW()
        WHERE user_id = :userId AND is_active = 1
    ");
    $sessionStmt->execute(['userId' => $userId]);
    
    sendJSON([
        'verified' => true,
        'confidence' => $result['confidence'] ?? 1.0,
        'message' => 'Face verified successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Face verification error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Verification failed'], 500);
}
