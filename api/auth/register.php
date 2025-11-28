<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log errors to file instead of display
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/Professor_Hawkeinstein/logs/register_errors.log');

try {
    // Direct database connection with existing credentials
    $db = new PDO(
        'mysql:host=localhost;dbname=professorhawkeinstein_platform;charset=utf8mb4',
        'professorhawkeinstein_user',
        'BT1716lit'
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid input');
    }

    // Validate input
    $fullName = trim($input['fullName'] ?? '');
    $email = trim($input['email'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $facialDescriptor = $input['facialDescriptor'] ?? [];

    // Validation rules
    if (strlen($fullName) < 2 || strlen($fullName) > 100) {
        throw new Exception('Full name must be between 2 and 100 characters');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        throw new Exception('Username must be 3-20 characters (letters, numbers, underscores)');
    }

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        throw new Exception('Password must contain uppercase, lowercase, and numbers');
    }

    if (empty($facialDescriptor) || !is_array($facialDescriptor)) {
        throw new Exception('Facial recognition data is required');
    }

    // Check if username already exists
    $stmt = $db->prepare('SELECT user_id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Username already taken');
    }

    // Check if email already exists
    $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already registered');
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Serialize facial descriptor (array of floats)
    $facialSignature = serialize($facialDescriptor);

    // Insert user
    $stmt = $db->prepare(
        'INSERT INTO users (username, email, password_hash, full_name, facial_signature, role) 
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    
    $stmt->execute([
        $username,
        $email,
        $passwordHash,
        $fullName,
        $facialSignature,
        'student'
    ]);

    $userId = $db->lastInsertId();

    // Log registration
    error_log("New user registered: ID=$userId, Username=$username, Email=$email");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'userId' => $userId
    ]);

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
