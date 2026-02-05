/**
 * Admin Authentication Utility
 * Automatically adds JWT token to all API requests
 * Include this script in all admin pages AFTER auth_storage.js
 */

// Check if user is logged in and has admin role
function checkAdminAuth() {
    console.log('[admin_auth] Checking authentication...');
    const user = getAdminSession('user');
    console.log('[admin_auth] User data:', user);
    if (!user) {
        // Not logged in - redirect to admin login
        console.log('[admin_auth] No user - redirecting to login');
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

// Run auth check on page load
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
