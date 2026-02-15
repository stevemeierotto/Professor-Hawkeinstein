<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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

// Database connection parameters - Docker MySQL on port 3307
// CRITICAL: Use 127.0.0.1 (not 'localhost') to force TCP connection to Docker
// localhost can resolve to Unix socket or local system DB instead of Docker container
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: 3307);  // Docker MySQL port
define('DB_NAME', getenv('DB_NAME') ?: 'professorhawkeinstein_platform');
define('DB_USER', getenv('DB_USER') ?: 'professorhawkeinstein_user');
define('DB_PASS', getenv('DB_PASS') ?: 'BT1716lit');
define('DB_CHARSET', 'utf8mb4');

// C++ Agent Microservice Configuration
define('AGENT_SERVICE_URL', getenv('AGENT_SERVICE_URL') ?: 'http://127.0.0.1:8080');
define('AGENT_SERVICE_TIMEOUT', 30); // seconds

// Session configuration
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('JWT_EXPIRY', 86400); // 24 hours
define('SESSION_NAME', 'PROFESSORHAWKEINSTEIN_SESSION');

// Security
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_jwt_secret_key_here_change_in_production');
define('PASSWORD_PEPPER', getenv('PASSWORD_PEPPER') ?: 'additional_security_pepper_change_in_production');

// Google OAuth 2.0 Configuration
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');

// Auto-detect protocol for OAuth redirect URI (supports HTTP dev and HTTPS mkcert)
if (!getenv('GOOGLE_REDIRECT_URI')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $defaultRedirectUri = "$protocol://$host/api/auth/google/callback.php";
    define('GOOGLE_REDIRECT_URI', $defaultRedirectUri);
} else {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI'));
}

// Biometric settings removed for liability reasons
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
/**
 * Get PDO database connection
 * DEBUGGING: Static cache removed to verify actual DB connection
 */
function getDB($forceReconnect = false) {
    // REMOVED static caching - create new connection every time
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // LOG ACTUAL DATABASE IMMEDIATELY
        $result = $pdo->query('SELECT DATABASE() as db');
        $actual_db = $result->fetch(PDO::FETCH_ASSOC)['db'];
        error_log("[getDB] DSN dbname=" . DB_NAME . " | ACTUAL connected to: " . $actual_db);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("[getDB] Connection failed: " . $e->getMessage() . " | DSN dbname=" . DB_NAME);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
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
        error_log("[verifyToken] Empty token");
        return false;
    }
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        $payloadData = (array)$decoded;
        // Check expiration
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            error_log("[verifyToken] Token expired. Exp: " . $payloadData['exp'] . " Now: " . time());
            return false;
        }
        error_log("[verifyToken] Token valid for user: " . ($payloadData['username'] ?? 'unknown'));
        return $payloadData;
    } catch (Exception $e) {
        error_log("[verifyToken] JWT decode error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate JWT token
 */
function generateToken($userId, $username, $role) {
    $payload = [
        'userId' => $userId,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + SESSION_LIFETIME
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

/**
 * Require authentication
 * Checks cookies first (more secure), then falls back to Authorization header (backward compatibility)
 */
function requireAuth() {
    $token = null;
    
    // Check secure cookie first (preferred for HTTPS)
    if (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    // Fallback to Authorization header (backward compatibility)
    else {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    if (empty($token)) {
        sendJSON(['success' => false, 'message' => 'No authorization token provided'], 401);
    }
    
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
 * Get admin ID from JWT token in cookie or Authorization header
 */
function getAdminId() {
    $token = null;
    
    // Check cookie first
    if (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    // Fallback to Authorization header
    else {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    if ($token) {
        $userData = verifyToken($token);
        if ($userData && isset($userData['userId'])) {
            return $userData['userId'];
        }
    }
    return null;
}

/**
 * Log activity
 */
function logActivity($userId, $action, $details = '') {
    $logFile = __DIR__ . '/../logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] User $userId: $action - $details\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Communicate with C++ Agent Microservice
 */
function callAgentService($endpoint, $data) {
    $url = AGENT_SERVICE_URL . $endpoint;
    
    $jsonData = json_encode($data);
    
    error_log("[callAgentService] Calling $url with data length: " . strlen($jsonData));
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 900,  // 15 minutes for longer LLM generations on slower CPUs
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
        
        error_log("[callAgentService] HTTP $httpCode, response length: " . strlen($response) . ", error: $curlError");
        
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
            error_log("Agent service error: Invalid JSON response - " . substr($response, 0, 200));
            return ['success' => false, 'message' => 'Invalid response from agent service'];
        }
        
        return $decodedResponse;
    } catch (Exception $e) {
        error_log("Agent service exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Agent service unavailable'];
    }
}

/**
 * Resolve course identifier to both course_id and draft_id
 * Handles: numeric course_id, string courseId (like '2nd_grade_science'), or numeric draft_id
 * 
 * @param string|int $courseIdentifier
 * @return array|null ['course_id' => int, 'draft_id' => int] or null if not found
 */
function resolveCourseIds($courseIdentifier) {
    $db = getDB();
    
    // Case 1: Numeric input - check if it's a course_id or draft_id
    if (is_numeric($courseIdentifier)) {
        $courseIdentifier = (int)$courseIdentifier;
        
        // Try as course_id first
        $stmt = $db->prepare("SELECT course_id, draft_id FROM courses WHERE course_id = :id");
        $stmt->execute(['id' => $courseIdentifier]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'course_id' => (int)$result['course_id'],
                'draft_id' => (int)$result['draft_id']
            ];
        }
        
        // Try as draft_id
        $stmt = $db->prepare("SELECT course_id, draft_id FROM courses WHERE draft_id = :id");
        $stmt->execute(['id' => $courseIdentifier]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'course_id' => (int)$result['course_id'],
                'draft_id' => (int)$result['draft_id']
            ];
        }
    }
    
    // Case 2: String input like '2nd_grade_science'
    // Convert to readable format
    $courseName = ucwords(str_replace('_', ' ', $courseIdentifier));
    
    $stmt = $db->prepare("SELECT course_id, draft_id FROM courses WHERE course_name = :name");
    $stmt->execute(['name' => $courseName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return [
            'course_id' => (int)$result['course_id'],
            'draft_id' => (int)$result['draft_id']
        ];
    }
    
    return null;
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

/**
 * Generate a cryptographically secure OAuth state token
 * @return string 64-character hex string
 */
function generateOAuthState() {
    return bin2hex(random_bytes(32));
}

/**
 * Store OAuth state token in database with expiration
 * @param string $state State token
 * @param int $expiryMinutes Minutes until expiration (default 10)
 * @param string|null $inviteToken Optional invitation token to link with this OAuth flow
 * @return bool Success
 */
function storeOAuthState($state, $expiryMinutes = 10, $inviteToken = null) {
    try {
        $db = getDB();
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
        
        $stmt = $db->prepare("
            INSERT INTO oauth_states (state_token, expires_at, invite_token) 
            VALUES (:state, :expires, :invite_token)
        ");
        
        return $stmt->execute([
            'state' => $state,
            'expires' => $expiresAt,
            'invite_token' => $inviteToken
        ]);
    } catch (Exception $e) {
        error_log("Error storing OAuth state: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate and consume OAuth state token (one-time use)
 * Returns the invitation token if one was stored with this state
 * @param string $state State token to validate
 * @return string|null Invitation token if present, empty string if not, null if invalid state
 */
function validateOAuthState($state) {
    try {
        $db = getDB();
        
        // Check if state exists and not expired, retrieve invite_token if present
        $stmt = $db->prepare("
            SELECT state_token, invite_token FROM oauth_states 
            WHERE state_token = :state 
            AND expires_at > NOW()
        ");
        $stmt->execute(['state' => $state]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            error_log("OAuth state validation failed: state not found or expired");
            return null;
        }
        
        $inviteToken = $result['invite_token'] ?? null;
        
        // Delete state token (one-time use)
        $deleteStmt = $db->prepare("DELETE FROM oauth_states WHERE state_token = :state");
        $deleteStmt->execute(['state' => $state]);
        
        // Clean up expired states
        $cleanupStmt = $db->prepare("DELETE FROM oauth_states WHERE expires_at <= NOW()");
        $cleanupStmt->execute();
        
        // Return the invite token (may be null)
        return $inviteToken;
    } catch (Exception $e) {
        error_log("Error validating OAuth state: " . $e->getMessage());
        return null;
    }
}

/**
 * Log authentication event for audit trail
 * @param int|null $userId User ID (null for failed attempts)
 * @param string $eventType Event type (login_success, login_failed, oauth_link, etc.)
 * @param string $authMethod Authentication method (local, google)
 * @param array|null $metadata Additional context
 */
function logAuthEvent($userId, $eventType, $authMethod, $metadata = null) {
    try {
        $db = getDB();
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO auth_events 
            (user_id, event_type, auth_method, ip_address, user_agent, metadata)
            VALUES (:user_id, :event_type, :auth_method, :ip_address, :user_agent, :metadata)
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'event_type' => $eventType,
            'auth_method' => $authMethod,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging auth event: " . $e->getMessage());
        return false;
    }
}

/**
 * Find user by Google provider ID (sub claim)
 * @param string $googleId Google's unique user identifier (sub)
 * @return array|null User data if found
 */
function findUserByGoogleId($googleId) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT u.* 
            FROM users u
            INNER JOIN auth_providers ap ON u.user_id = ap.user_id
            WHERE ap.provider_type = 'google' 
            AND ap.provider_user_id = :google_id
            AND u.is_active = 1
        ");
        
        $stmt->execute(['google_id' => $googleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error finding user by Google ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Link Google account to existing user
 * @param int $userId User ID
 * @param string $googleId Google sub claim
 * @param string $googleEmail Email from Google
 * @return bool Success
 */
function linkGoogleAccount($userId, $googleId, $googleEmail) {
    try {
        $db = getDB();
        
        // Check if Google account already linked to another user
        $checkStmt = $db->prepare("
            SELECT user_id FROM auth_providers 
            WHERE provider_type = 'google' 
            AND provider_user_id = :google_id
        ");
        $checkStmt->execute(['google_id' => $googleId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing && $existing['user_id'] != $userId) {
            error_log("Google account already linked to different user");
            return false;
        }
        
        // Link Google account
        $stmt = $db->prepare("
            INSERT INTO auth_providers 
            (user_id, provider_type, provider_user_id, provider_email, is_primary, last_used)
            VALUES (:user_id, 'google', :google_id, :google_email, FALSE, NOW())
            ON DUPLICATE KEY UPDATE 
                provider_email = :google_email,
                last_used = NOW()
        ");
        
        $success = $stmt->execute([
            'user_id' => $userId,
            'google_id' => $googleId,
            'google_email' => $googleEmail
        ]);
        
        if ($success) {
            logAuthEvent($userId, 'oauth_link', 'google', ['email' => $googleEmail]);
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Error linking Google account: " . $e->getMessage());
        return false;
    }
}

/**
 * Create new user from Google OAuth
 * @param string $googleId Google sub claim
 * @param string $email Email from Google
 * @param string $fullName Full name from Google
 * @return array|null User data if created successfully
 */
function createUserFromGoogle($googleId, $email, $fullName, $role = 'student') {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Generate unique username from email
        $username = explode('@', $email)[0];
        $baseUsername = $username;
        $counter = 1;
        
        // Check for username conflicts
        while (true) {
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = :username");
            $checkStmt->execute(['username' => $username]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        // Create user (no password for OAuth-only accounts)
        $stmt = $db->prepare("
            INSERT INTO users 
            (username, email, password_hash, full_name, role, email_verified)
            VALUES (:username, :email, NULL, :full_name, :role, TRUE)
        ");
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'role' => $role
        ]);
        
        $userId = $db->lastInsertId();
        
        // Link Google provider
        $providerStmt = $db->prepare("
            INSERT INTO auth_providers 
            (user_id, provider_type, provider_user_id, provider_email, is_primary)
            VALUES (:user_id, 'google', :google_id, :google_email, TRUE)
        ");
        
        $providerStmt->execute([
            'user_id' => $userId,
            'google_id' => $googleId,
            'google_email' => $email
        ]);
        
        $db->commit();
        
        // Fetch created user
        $userStmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $userStmt->execute(['user_id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        logAuthEvent($userId, 'login_success', 'google', ['new_account' => true]);
        
        return $user;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error creating user from Google: " . $e->getMessage());
        return null;
    }
}

/**
 * Find pending admin invitation by token
 * @param string $token Invitation token
 * @return array|null Invitation data if valid and not expired
 */
function findPendingInvitation($token) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT * FROM admin_invitations 
            WHERE invite_token = :token 
            AND used_at IS NULL 
            AND expires_at > NOW()
        ");
        
        $stmt->execute(['token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error finding invitation: " . $e->getMessage());
        return null;
    }
}

/**
 * Mark invitation as used
 * @param string $token Invitation token
 * @param int $userId User ID who accepted the invite
 * @return bool Success
 */
function markInvitationUsed($token, $userId) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            UPDATE admin_invitations 
            SET used_at = NOW(), used_by_user_id = :user_id
            WHERE invite_token = :token
        ");
        
        return $stmt->execute([
            'token' => $token,
            'user_id' => $userId
        ]);
    } catch (Exception $e) {
        error_log("Error marking invitation as used: " . $e->getMessage());
        return false;
    }
}

/**
 * List all admin invitations (for management UI)
 * @param bool $pendingOnly Show only unused invitations
 * @return array Array of invitation records
 */
function listAdminInvitations($pendingOnly = false) {
    try {
        $db = getDB();
        
        $sql = "
            SELECT 
                ai.*,
                invited_by_user.username as invited_by_username,
                used_by_user.username as used_by_username
            FROM admin_invitations ai
            LEFT JOIN users invited_by_user ON ai.invited_by = invited_by_user.user_id
            LEFT JOIN users used_by_user ON ai.used_by_user_id = used_by_user.user_id
        ";
        
        if ($pendingOnly) {
            $sql .= " WHERE ai.used_at IS NULL AND ai.expires_at > NOW()";
        }
        
        $sql .= " ORDER BY ai.created_at DESC";
        
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error listing invitations: " . $e->getMessage());
        return [];
    }
}
