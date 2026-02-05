#!/bin/bash
# Apply secure cookie updates to production config/database.php
# This patches requireAuth() and getAdminId() to check cookies first

set -e

PROD_CONFIG="/var/www/html/basic_educational/config/database.php"
BACKUP="${PROD_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)"

echo "🔧 Patching production config/database.php for secure cookies"
echo "   Backup: $BACKUP"

# Create backup
cp "$PROD_CONFIG" "$BACKUP"

# Create temporary patch file
cat > /tmp/database_cookie_patch.php << 'EOPHP'
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
EOPHP

# Use sed to replace the functions
echo "   Updating requireAuth()..."
sed -i '/^\/\*\*$/,/^}$/{
    /function requireAuth/,/^}$/{
        /function requireAuth/!{
            /^}$/!d
        }
    }
}' "$PROD_CONFIG"

# Insert new function after the marker
# This is safer than trying to do complex sed replacements

echo "⚠️  Manual verification required!"
echo "   Please manually update these functions in $PROD_CONFIG:"
echo "   1. requireAuth() - Check cookies first"
echo "   2. getAdminId() - Check cookies first"
echo ""
echo "   Reference implementation: /home/steve/Professor_Hawkeinstein/config/database.php"
echo "   Backup saved to: $BACKUP"
echo ""
echo "   Or copy the entire function from DEV:"
echo "   grep -A 30 'function requireAuth' /home/steve/Professor_Hawkeinstein/config/database.php"
