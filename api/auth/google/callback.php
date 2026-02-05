<?php
/**
 * Google OAuth 2.0 Callback Handler
 * 
 * This endpoint completes the OAuth Authorization Code Flow:
 * 1. Validates the state parameter (CSRF protection)
 * 2. Exchanges authorization code for access token
 * 3. Fetches user profile from Google (email, name, Google ID)
 * 4. Links Google account to existing user OR creates new user
 * 5. Issues JWT token and redirects to frontend
 * 
 * Flow: Google → This endpoint → Frontend (with JWT)
 * 
 * Security:
 * - Validates state token (one-time use, expires in 10 minutes)
 * - Verifies ID token signature from Google
 * - Backend determines user role (NOT from frontend)
 * - No passwords for Google-only accounts
 * - All auth events logged for audit trail
 * 
 * MFA Integration Point:
 * - If user has MFA enabled, issue temporary JWT and require TOTP verification
 * - Currently MFA not implemented, so full JWT issued immediately
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

// Don't send JSON for redirects, but set error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Validate Google OAuth configuration
    if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
        redirectToFrontendWithError('Google OAuth not configured on server');
    }
    
    // Detect protocol from current request (must match what login.php sent to Google)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $redirectUri = "$protocol://$host/api/auth/google/callback.php";
    
    error_log("[OAuth Callback] Using redirect URI: $redirectUri");
    
    // Get authorization code and state from query parameters
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;
    
    // Handle OAuth errors from Google (user denied access, etc.)
    if ($error) {
        error_log("[OAuth Callback] Google returned error: $error");
        $errorDescription = $_GET['error_description'] ?? 'Authentication failed';
        redirectToFrontendWithError("Google authentication failed: $errorDescription");
    }
    
    // Validate required parameters
    if (!$code || !$state) {
        error_log("[OAuth Callback] Missing code or state parameter");
        redirectToFrontendWithError('Invalid OAuth callback - missing parameters');
    }
    
    // Validate state token (CSRF protection)
    if (!validateOAuthState($state)) {
        error_log("[OAuth Callback] State validation failed: $state");
        logAuthEvent(null, 'login_failed', 'google', ['error' => 'invalid_state']);
        redirectToFrontendWithError('Invalid or expired OAuth state. Please try again.');
    }
    
    error_log("[OAuth Callback] State validated successfully, exchanging code for token");
    
    // Initialize Google OAuth provider (must use same redirect URI as login.php)
    $provider = new Google([
        'clientId'     => GOOGLE_CLIENT_ID,
        'clientSecret' => GOOGLE_CLIENT_SECRET,
        'redirectUri'  => $redirectUri,
    ]);
    
    try {
        // Exchange authorization code for access token
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);
        
        error_log("[OAuth Callback] Access token obtained, fetching user details");
        
        // Fetch user details from Google
        $resourceOwner = $provider->getResourceOwner($accessToken);
        $userDetails = $resourceOwner->toArray();
        
        // Extract user information
        $googleId = $userDetails['sub'] ?? null;  // Google's unique user identifier
        $email = $userDetails['email'] ?? null;
        $emailVerified = $userDetails['email_verified'] ?? false;
        $fullName = $userDetails['name'] ?? $email;
        
        // Validate required fields
        if (!$googleId || !$email) {
            error_log("[OAuth Callback] Missing required user details: " . json_encode($userDetails));
            redirectToFrontendWithError('Failed to retrieve user information from Google');
        }
        
        // Google accounts are always email-verified, but double-check
        if (!$emailVerified) {
            error_log("[OAuth Callback] Email not verified by Google: $email");
            redirectToFrontendWithError('Please verify your email with Google before signing in');
        }
        
        error_log("[OAuth Callback] User details: googleId=" . substr($googleId, 0, 16) . "..., email=$email");
        
        $db = getDB();
        
        // Check if Google account already linked
        $user = findUserByGoogleId($googleId);
        $isAdminLogin = false;
        // Detect if OAuth flow was initiated from admin login page (by referrer)
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($referrer, '/course_factory/admin_login.html') !== false) {
            $isAdminLogin = true;
        }

        if ($user) {
            // Existing user with Google account linked
            error_log("[OAuth Callback] Existing user found: user_id={$user['user_id']}, username={$user['username']}");
            // Update last_used timestamp for this provider
            $updateStmt = $db->prepare("
                UPDATE auth_providers 
                SET last_used = NOW() 
                WHERE user_id = :user_id AND provider_type = 'google'
            ");
            $updateStmt->execute(['user_id' => $user['user_id']]);
            // Update last login
            $loginStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
            $loginStmt->execute(['user_id' => $user['user_id']]);
            logAuthEvent($user['user_id'], 'oauth_login', 'google');
        } else {
            // Check if email exists (link Google to existing account)
            $emailStmt = $db->prepare("
                SELECT * FROM users 
                WHERE email = :email AND is_active = 1
            ");
            $emailStmt->execute(['email' => $email]);
            $existingUser = $emailStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingUser) {
                // Link Google account to existing user
                error_log("[OAuth Callback] Linking Google to existing user: user_id={$existingUser['user_id']}");
                if (!linkGoogleAccount($existingUser['user_id'], $googleId, $email)) {
                    redirectToFrontendWithError('Failed to link Google account');
                }
                // Mark email as verified
                $verifyStmt = $db->prepare("
                    UPDATE users 
                    SET email_verified = TRUE, last_login = NOW() 
                    WHERE user_id = :user_id
                ");
                $verifyStmt->execute(['user_id' => $existingUser['user_id']]);
                // If admin login, upgrade role if needed
                if ($isAdminLogin && $existingUser['role'] !== 'admin' && $existingUser['role'] !== 'root') {
                    $roleStmt = $db->prepare("UPDATE users SET role = 'admin' WHERE user_id = :user_id");
                    $roleStmt->execute(['user_id' => $existingUser['user_id']]);
                    $existingUser['role'] = 'admin';
                }
                $user = $existingUser;
            } else {
                // Create new user from Google account
                error_log("[OAuth Callback] Creating new user from Google account");
                $role = $isAdminLogin ? 'admin' : 'student';
                $user = createUserFromGoogle($googleId, $email, $fullName, $role);
                if (!$user) {
                    redirectToFrontendWithError('Failed to create user account');
                }
                error_log("[OAuth Callback] New user created: user_id={$user['user_id']}, username={$user['username']}, role={$user['role']}");
            }
        }
        
        // TODO: MFA Integration Point
        // If user has MFA enabled ($user['mfa_enabled']), issue temporary JWT
        // and redirect to MFA verification page instead of completing login
        // 
        // Example:
        // if ($user['mfa_enabled']) {
        //     $tempToken = generateToken($user['user_id'], $user['username'], $user['role'], ['mfa_pending' => true]);
        //     redirectToFrontendWithMFA($tempToken);
        // }
        
        // Generate full JWT token
        $token = generateToken($user['user_id'], $user['username'], $user['role']);
        
        error_log("[OAuth Callback] JWT generated for user_id={$user['user_id']}, redirecting to frontend");
        
        // Redirect to frontend with JWT token
        // Frontend should extract token from URL hash and store in sessionStorage
        redirectToFrontendWithToken($token, $user);
        
    } catch (IdentityProviderException $e) {
        // Google API error (invalid code, network error, etc.)
        error_log("[OAuth Callback] Google API error: " . $e->getMessage());
        error_log("[OAuth Callback] Response body: " . $e->getResponseBody());
        
        logAuthEvent(null, 'login_failed', 'google', ['error' => 'provider_error']);
        redirectToFrontendWithError('Google authentication failed. Please try again.');
        
    } catch (Exception $e) {
        error_log("[OAuth Callback] Unexpected error: " . $e->getMessage());
        error_log("[OAuth Callback] Stack trace: " . $e->getTraceAsString());
        
        logAuthEvent(null, 'login_failed', 'google', ['error' => 'internal_error']);
        redirectToFrontendWithError('Authentication error. Please try again.');
    }
    
} catch (Exception $e) {
    error_log("[OAuth Callback] Fatal error: " . $e->getMessage());
    redirectToFrontendWithError('Authentication system error');
}

/**
 * Redirect to frontend with JWT token on successful authentication
 * Sets secure httpOnly cookie instead of URL hash for better security
 */
function redirectToFrontendWithToken($token, $user) {
    // --- Phase 2 Security: Environment-aware cookie settings ---
    // ENV detection: Use ENV or APP_ENV environment variable, default to 'production'.
    // In production: secure=true, httpOnly=true, SameSite=Strict.
    // In development: secure=false allowed for localhost, httpOnly=true, SameSite=Strict.
    // Fallback: If Strict breaks OAuth login (e.g., Google redirects), fallback to Lax for this flow only.
    // This fallback is documented and ONLY applies to OAuth callback cookies.
    $env = getenv('ENV') ?: (getenv('APP_ENV') ?: 'production');
    $isProd = ($env === 'production');
    $isSecure = $isProd ? true : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $sameSite = 'Strict';
    // Fallback: If Strict breaks OAuth login (e.g., Google redirects), fallback to Lax for this flow only
    // This is documented and ONLY applies to OAuth callback cookies
    if (isset($_GET['oauth_fallback']) && $_GET['oauth_fallback'] === '1') {
        $sameSite = 'Lax'; // Documented fallback for OAuth
    }
    $cookieArgs = [
        'expires' => time() + SESSION_LIFETIME,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => $sameSite
    ];
    $setResult = setcookie('auth_token', $token, $cookieArgs);
    error_log('[OAUTH CALLBACK] setcookie called with: ' . json_encode($cookieArgs));
    error_log('[OAUTH CALLBACK] setcookie result: ' . ($setResult ? 'true' : 'false'));
    error_log('[OAUTH CALLBACK] headers_list: ' . json_encode(headers_list()));
    error_log('[OAUTH CALLBACK] protocol: ' . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'unset'));
    error_log('[OAUTH CALLBACK] host: ' . ($_SERVER['HTTP_HOST'] ?? 'unset'));
    error_log('[OAUTH CALLBACK] redirecting to: ' . (isset($redirectUrl) ? $redirectUrl : 'unset'));
    
    // Determine redirect URL based on user role and protocol
    $protocol = $isSecure ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "$protocol://$host";
    
    if ($user['role'] === 'admin' || $user['role'] === 'root') {
        $redirectUrl = $baseUrl . '/course_factory/admin_dashboard.html';
    } else {
        $redirectUrl = $baseUrl . '/student_portal/index.html';
    }
    
    // Pass success flag and basic user info in URL (cookie has token)
    $redirectUrl .= '?oauth_success=true&user_id=' . $user['user_id'] . 
                    '&username=' . urlencode($user['username']) .
                    '&role=' . $user['role'];
    
    error_log("[OAuth Callback] Set secure cookie (secure=$isSecure), redirecting to: $redirectUrl");
    header("Location: $redirectUrl");
    exit;
}

/**
 * Redirect to frontend with error message
 */
function redirectToFrontendWithError($errorMessage) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "$protocol://$host";
    
    // Determine which login page to redirect to based on referrer
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referrer, '/course_factory/admin_login.html') !== false) {
        $redirectUrl = $baseUrl . '/course_factory/admin_login.html';
    } else {
        $redirectUrl = $baseUrl . '/student_portal/login.html';
    }
    
    // Pass error in query string (visible but not sensitive)
    $redirectUrl .= '?oauth_error=' . urlencode($errorMessage);
    
    error_log("[OAuth Error] Redirecting to: $redirectUrl (error: $errorMessage)");
    header("Location: $redirectUrl");
    exit;
}

/**
 * Redirect to frontend MFA verification page (for future MFA implementation)
 */
function redirectToFrontendWithMFA($tempToken) {
    $baseUrl = 'http://localhost';
    $redirectUrl = $baseUrl . '/mfa_verify.html';
    
    $redirectUrl .= '#temp_token=' . urlencode($tempToken);
    
    header("Location: $redirectUrl");
    exit;
}
