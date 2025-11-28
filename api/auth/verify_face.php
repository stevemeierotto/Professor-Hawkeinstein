<?php
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/Professor_Hawkeinstein/logs/verify_errors.log');

try {
    // Direct database connection with existing credentials
    $db = new PDO(
        'mysql:host=localhost;dbname=professorhawkeinstein_platform;charset=utf8mb4',
        'professorhawkeinstein_user',
        'BT1716lit'
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid input');
    }

    $username = trim($input['username'] ?? '');
    $facialDescriptor = $input['facialDescriptor'] ?? [];

    if (!$username || empty($facialDescriptor)) {
        throw new Exception('Username and facial data required');
    }

    // Get user's stored facial signature
    $stmt = $db->prepare('SELECT user_id, facial_signature FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    if (!$user['facial_signature']) {
        throw new Exception('No facial data on file');
    }

    // Deserialize stored facial signature
    $storedDescriptor = unserialize($user['facial_signature']);
    $currentDescriptor = $facialDescriptor;

    // Calculate Euclidean distance
    if (count($storedDescriptor) !== count($currentDescriptor)) {
        throw new Exception('Facial data format mismatch');
    }

    $distance = 0;
    for ($i = 0; $i < count($storedDescriptor); $i++) {
        $diff = $storedDescriptor[$i] - $currentDescriptor[$i];
        $distance += $diff * $diff;
    }
    $distance = sqrt($distance);

    // Threshold for face matching (typical: 0.6)
    $threshold = 0.6;
    $match = $distance < $threshold;

    error_log("Facial verification for user: $username, distance: $distance, match: " . ($match ? 'true' : 'false'));

    // Return verification result
    echo json_encode([
        'success' => true,
        'match' => $match,
        'userId' => $user['user_id'],
        'distance' => round($distance, 4),
        'message' => $match ? 'Facial match confirmed' : 'Facial match failed'
    ]);

} catch (Exception $e) {
    error_log('Verification error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
