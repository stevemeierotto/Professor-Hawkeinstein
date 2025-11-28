<?php
/**
 * Database Configuration
 * MariaDB 10.7+ with vector support
 */

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Set as environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Database connection parameters
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'professorhawkeinstein_platform');
define('DB_USER', getenv('DB_USER') ?: 'professorhawkeinstein_user');
define('DB_PASS', getenv('DB_PASS') ?: 'BT1716lit');
define('DB_CHARSET', 'utf8mb4');

// C++ Agent Microservice Configuration
define('AGENT_SERVICE_URL', 'http://localhost:8080');
define('AGENT_SERVICE_TIMEOUT', 30); // seconds

// Session configuration
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('JWT_EXPIRY', 86400); // 24 hours
define('SESSION_NAME', 'PROFESSORHAWKEINSTEIN_SESSION');

// Security
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_jwt_secret_key_here_change_in_production');
define('PASSWORD_PEPPER', getenv('PASSWORD_PEPPER') ?: 'additional_security_pepper_change_in_production');

// Common Standards Project API
define('CSP_API_KEY', getenv('CSP_API_KEY') ?: '');
define('CSP_API_BASE_URL', 'https://api.commonstandardsproject.com/api/v1');

// Biometric settings
define('FACIAL_RECOGNITION_THRESHOLD', 0.85);
define('VOICE_RECOGNITION_THRESHOLD', 0.80);
define('CHEATING_FLAG_LIMIT', 3); // Number of failed verifications before alert

// File upload settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('MEDIA_PATH', __DIR__ . '/../media/');

// Application settings
define('APP_NAME', 'Professor Hawkeinstein\'s Educational Foundation');
define('APP_VERSION', '1.0.0');
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true');

/**
 * Get PDO database connection
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Set CORS headers for API responses
 */
function setCORSHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Get JSON input from request body
 */
function getJSONInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Send JSON response
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Verify JWT token
 */
function verifyToken($token) {
    if (empty($token)) {
        return false;
    }
    
    // Simple JWT verification (use proper library in production like firebase/php-jwt)
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($header, $payload, $signature) = $parts;
    
    // Verify signature
    $validSignature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
    $validSignature = base64_encode($validSignature);
    $validSignature = str_replace(['+', '/', '='], ['-', '_', ''], $validSignature);
    
    if ($signature !== $validSignature) {
        return false;
    }
    
    // Decode payload
    $payloadData = json_decode(base64_decode($payload), true);
    
    // Check expiration
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return false;
    }
    
    return $payloadData;
}

/**
 * Generate JWT token
 */
function generateToken($userId, $username, $role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'userId' => $userId,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + SESSION_LIFETIME
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
}

/**
 * Require authentication
 */
function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        sendJSON(['success' => false, 'message' => 'No authorization token provided'], 401);
    }
    
    $token = $matches[1];
    $userData = verifyToken($token);
    
    if (!$userData) {
        sendJSON(['success' => false, 'message' => 'Invalid or expired token'], 401);
    }
    
    return $userData;
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password . PASSWORD_PEPPER, PASSWORD_ARGON2ID);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password . PASSWORD_PEPPER, $hash);
}

/**
 * Log activity
 */
function logActivity($userId, $action, $details = '') {
    $logFile = __DIR__ . '/../logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] User $userId: $action - $details\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Communicate with C++ Agent Microservice
 */
function callAgentService($endpoint, $data) {
    $url = AGENT_SERVICE_URL . $endpoint;
    
    $jsonData = json_encode($data);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_BUFFERSIZE => 1024,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]
    ]);
    
    try {
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            error_log("Agent service error: Failed to connect to $url - $curlError");
            return ['success' => false, 'message' => 'Agent service unavailable'];
        }
        
        if ($httpCode !== 200) {
            error_log("Agent service error: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Agent service error'];
        }
        
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            error_log("Agent service error: Invalid JSON response - $response");
            return ['success' => false, 'message' => 'Invalid response from agent service'];
        }
        
        return $decodedResponse;
    } catch (Exception $e) {
        error_log("Agent service exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Agent service unavailable'];
    }
}

// Initialize error logging
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Create media directory if it doesn't exist
if (!is_dir(MEDIA_PATH)) {
    mkdir(MEDIA_PATH, 0755, true);
}
