<?php
/**
 * User Login API Endpoint
 */


require_once __DIR__ . '/../helpers/security_headers.php';
set_api_security_headers();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/Professor_Hawkeinstein/logs/login_errors.log');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }

    // Find user by username or email
    // STEP 4: Include auth_provider_required to check login method enforcement
    $stmt = $db->prepare("
        SELECT user_id, username, email, password_hash, full_name, role, is_active, auth_provider_required
        FROM users
        WHERE (username = ? OR email = ?) AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        error_log("Login attempt failed: user not found for username: $username");
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // ============================================================================
    // STEP 4: Enforce authentication provider requirement
    // ============================================================================
    // If user has auth_provider_required set, they MUST use that provider.
    // This check happens BEFORE password verification for security reasons:
    // - Don't reveal password validity if login method is wrong
    // - Fail fast with clear guidance
    //
    // SAFETY: Only affects users with auth_provider_required set (invited admins)
    // - Students: auth_provider_required = NULL → check skipped
    // - Legacy admins: auth_provider_required = NULL → check skipped
    // - Invited admins: auth_provider_required = 'google' → password login blocked
    //
    // MIGRATION SAFETY: If column doesn't exist yet, skip enforcement entirely.
    // This allows system to work before migrations run.
    // ============================================================================
    if (isset($user['auth_provider_required']) && !empty($user['auth_provider_required'])) {
        $requiredProvider = $user['auth_provider_required'];
        
        // User is trying password login but must use OAuth
        if ($requiredProvider === 'google') {
            http_response_code(403);
            error_log("Login attempt blocked: user {$user['username']} must use Google SSO (tried password)");
            
            // Log the attempt for security monitoring
            logAuthEvent($user['user_id'], 'login_failed', 'local', [
                'error' => 'wrong_auth_provider',
                'required' => 'google',
                'attempted' => 'local'
            ]);
            
            echo json_encode([
                'success' => false,
                'message' => 'This account requires Google Sign-In',
                'error_code' => 'AUTH_PROVIDER_REQUIRED',
                'required_provider' => 'google',
                'instructions' => 'Please use the "Sign in with Google" button instead of password login.'
            ]);
            exit;
        }
        
        // Future providers (Microsoft, Clever, etc.) would be checked here
        // Example:
        // if ($requiredProvider === 'microsoft') { ... }
    }

    // Verify password
    // This only runs if:
    // - auth_provider_required is NULL (no restriction), OR
    // - auth_provider_required is 'local' (password explicitly allowed)
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        error_log("Login attempt failed: invalid password for username: $username");
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // Update last login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->execute([$user['user_id']]);

    // Generate JWT token with role
    $token = generateToken($user['user_id'], $user['username'], $user['role']);

    // --- Phase 2 Security: Environment-aware cookie settings ---
        // ENV detection: Use ENV or APP_ENV environment variable, default to 'production'.
        // In production: secure=true, httpOnly=true, SameSite=Strict.
        // In development: secure=false allowed for localhost, httpOnly=true, SameSite=Strict.
        // If Strict breaks login, fallback to Lax only for affected flows (see OAuth callback).
    $env = getenv('ENV') ?: (getenv('APP_ENV') ?: 'production');
    $isProd = ($env === 'production');
    $isSecure = $isProd ? true : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $sameSite = 'Strict';

    // If Strict breaks login (e.g., for OAuth or cross-site flows), fallback to Lax below (see OAuth callback)
    setcookie('auth_token', $token, [
        'expires' => time() + SESSION_LIFETIME,
        'path' => '/',
        'secure' => $isSecure, // Always true in production, false allowed in dev for localhost
        'httponly' => true,
        'samesite' => $sameSite
    ]);

    // Log successful login and cookie policy
    error_log("User logged in successfully: ID={$user['user_id']}, Username={$user['username']}, Role={$user['role']}, Cookie secure={$isSecure}, SameSite={$sameSite}, ENV={$env}");

    // Return success with user data and JWT token (also in response for backward compatibility)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'userId' => $user['user_id'],
            'username' => $user['username'],
            'name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
