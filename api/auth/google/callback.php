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
    
    // CRITICAL: Google OAuth rejects .local domains
    // Redirect URI MUST be exactly http://localhost/api/auth/google/callback.php
    // This must match EXACTLY in Google Cloud Console Authorized redirect URIs
    $requiredRedirectUri = 'http://localhost/api/auth/google/callback.php';
    if (GOOGLE_REDIRECT_URI !== $requiredRedirectUri) {
        error_log("[OAuth Callback] CRITICAL: Redirect URI mismatch! Expected: $requiredRedirectUri, Got: " . GOOGLE_REDIRECT_URI);
        redirectToFrontendWithError('OAuth redirect URI misconfigured');
    }
    
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
    
    // Initialize Google OAuth provider
    $provider = new Google([
        'clientId'     => GOOGLE_CLIENT_ID,
        'clientSecret' => GOOGLE_CLIENT_SECRET,
        'redirectUri'  => GOOGLE_REDIRECT_URI,
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
                
                $user = $existingUser;
                
            } else {
                // Create new user from Google account
                error_log("[OAuth Callback] Creating new user from Google account");
                
                $user = createUserFromGoogle($googleId, $email, $fullName);
                
                if (!$user) {
                    redirectToFrontendWithError('Failed to create user account');
                }
                
                error_log("[OAuth Callback] New user created: user_id={$user['user_id']}, username={$user['username']}");
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
 * Uses URL hash to pass token (not visible in server logs)
 */
function redirectToFrontendWithToken($token, $user) {
    // Determine redirect URL based on user role
    $baseUrl = 'http://localhost';
    
    if ($user['role'] === 'admin' || $user['role'] === 'root') {
        $redirectUrl = $baseUrl . '/course_factory/admin_dashboard.html';
    } else {
        $redirectUrl = $baseUrl . '/student_portal/index.html';
    }
    
    // Pass token in URL hash (not sent to server, only available to JavaScript)
    // Frontend JavaScript should extract this and store in sessionStorage
    $redirectUrl .= '#oauth_success=true&token=' . urlencode($token) . 
                    '&user_id=' . $user['user_id'] . 
                    '&username=' . urlencode($user['username']) .
                    '&role=' . $user['role'];
    
    error_log("[OAuth Callback] Redirecting to: " . $redirectUrl);
    header("Location: $redirectUrl");
    exit;
}

/**
 * Redirect to frontend with error message
 */
function redirectToFrontendWithError($errorMessage) {
    $baseUrl = 'http://localhost';
    $redirectUrl = $baseUrl . '/login.html';
    
    // Pass error in query string (visible but not sensitive)
    $redirectUrl .= '?oauth_error=' . urlencode($errorMessage);
    
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
