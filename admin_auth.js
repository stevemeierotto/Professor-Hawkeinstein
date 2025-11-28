/**
 * Admin Authentication Utility
 * Automatically adds JWT token to all API requests
 * Include this script in all admin pages AFTER auth_storage.js
 */

// Check if user is logged in and has admin role
function checkAdminAuth() {
    console.log('[admin_auth] Checking authentication...');
    const token = getAdminSession('token');
    const user = getAdminSession('user');
    
    console.log('[admin_auth] Token:', token ? 'present' : 'missing');
    console.log('[admin_auth] User data:', user);
    
    if (!token || !user) {
        // Not logged in - redirect to admin login
        console.log('[admin_auth] No token or user - redirecting to login');
        window.location.href = 'admin_login.html';
        return false;
    }
    
    console.log('[admin_auth] User role:', user.role);
    if (user.role !== 'admin' && user.role !== 'root') {
        // Not an admin - redirect to admin login
        console.log('[admin_auth] User is not admin/root - access denied');
        alert('Admin access required');
        window.location.href = 'admin_login.html';
        return false;
    }
    
    console.log('[admin_auth] Authentication successful');
    return true;
}

// Override fetch to automatically include Authorization header
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    console.log('[admin_auth] Fetch called with URL:', url);
    console.log('[admin_auth] URL includes /api/:', url.includes('/api/'));
    console.log('[admin_auth] URL startsWith api/:', url.startsWith('api/'));
    
    // Only add token for API calls (handles both '/api/' and 'api/')
    if (url.includes('/api/') || url.startsWith('api/')) {
        const token = getAdminSession('token');
        console.log('[admin_auth] Token from storage:', token ? 'EXISTS (length ' + token.length + ')' : 'MISSING');
        if (token) {
            // Ensure options object exists
            options = options || {};
            // Create headers object properly - handle existing headers
            if (!options.headers) {
                options.headers = {};
            }
            // Convert Headers object to plain object if needed
            if (options.headers instanceof Headers) {
                const plainHeaders = {};
                options.headers.forEach((value, key) => {
                    plainHeaders[key] = value;
                });
                options.headers = plainHeaders;
            }
            // Add Authorization header
            options.headers['Authorization'] = `Bearer ${token}`;
            console.log('[admin_auth] Added auth header to:', url);
            console.log('[admin_auth] Headers:', JSON.stringify(options.headers));
        } else {
            console.error('[admin_auth] WARNING: No token found in sessionStorage!');
        }
    }
    return originalFetch(url, options);
};

// Check auth on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkAdminAuth);
} else {
    checkAdminAuth();
}

// Logout function (call from logout buttons)
function adminLogout() {
    clearAdminSession();
    window.location.href = 'admin_login.html';
}
