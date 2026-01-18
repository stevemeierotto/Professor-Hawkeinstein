<?php
/**
 * Google OAuth 2.0 Login Initiator
 * 
 * This endpoint starts the OAuth Authorization Code Flow:
 * 1. Generates a secure CSRF state token
 * 2. Stores it in the database with 10-minute expiration
 * 3. Redirects user to Google's authorization endpoint
 * 
 * Flow: User clicks "Sign in with Google" → This endpoint → Google login page
 * 
 * Security:
 * - State parameter prevents CSRF attacks
 * - Server-side OAuth only (no client-side JS SDK)
 * - Minimal scopes requested (email, profile)
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Validate Google OAuth configuration
    if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Google OAuth not configured. Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env'
        ]);
        exit;
    }
    
    // CRITICAL: Google OAuth rejects .local domains
    // Redirect URI MUST be exactly http://localhost/api/auth/google/callback.php
    // This must match EXACTLY in Google Cloud Console Authorized redirect URIs
    $requiredRedirectUri = 'http://localhost/api/auth/google/callback.php';
    if (GOOGLE_REDIRECT_URI !== $requiredRedirectUri) {
        error_log("[OAuth Login] CRITICAL: Redirect URI mismatch! Expected: $requiredRedirectUri, Got: " . GOOGLE_REDIRECT_URI);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'OAuth redirect URI misconfigured. Must use localhost for local development.'
        ]);
        exit;
    }
    
    // Initialize Google OAuth provider
    $provider = new Google([
        'clientId'     => GOOGLE_CLIENT_ID,
        'clientSecret' => GOOGLE_CLIENT_SECRET,
        'redirectUri'  => GOOGLE_REDIRECT_URI,
    ]);
    
    // Generate cryptographically secure state token (CSRF protection)
    $state = generateOAuthState();
    
    // Store state in database with 10-minute expiration
    if (!storeOAuthState($state)) {
        throw new Exception('Failed to store OAuth state token');
    }
    
    // Build authorization URL with minimal scopes
    $authorizationUrl = $provider->getAuthorizationUrl([
        'state' => $state,
        'scope' => [
            'openid',
            'email',
            'profile'
        ],
        // Force account selection (useful for users with multiple Google accounts)
        'prompt' => 'select_account',
        // Request fresh authentication
        'access_type' => 'online'
    ]);
    
    error_log("[OAuth Login] Generated state: " . substr($state, 0, 16) . "... Redirecting to Google");
    
    // Return authorization URL for frontend redirect
    // Frontend should redirect user to this URL
    echo json_encode([
        'success' => true,
        'authorization_url' => $authorizationUrl,
        'state' => $state  // Frontend should store this for validation (optional)
    ]);
    
} catch (Exception $e) {
    error_log("[OAuth Login] Error: " . $e->getMessage());
    error_log("[OAuth Login] Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to initialize Google OAuth login',
        'error' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}
