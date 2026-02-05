/**
 * Session Storage Helper Utilities
 * Provides role-namespaced storage for admin and student sessions
 * Prevents cross-tab session conflicts
 */

// Admin session helpers
function setAdminSession(key, value) {
    const namespacedKey = `admin_${key}`;
    if (typeof value === 'object') {
        sessionStorage.setItem(namespacedKey, JSON.stringify(value));
    } else {
        sessionStorage.setItem(namespacedKey, value);
    }
}

function getAdminSession(key) {
    const namespacedKey = `admin_${key}`;
    let value = sessionStorage.getItem(namespacedKey);
    // Fallback: if token missing in sessionStorage, try localStorage (for legacy or reload cases)
    if (!value && key === 'token') {
        value = localStorage.getItem('adminToken');
        if (value) {
            // Sync to sessionStorage for current session
            sessionStorage.setItem(namespacedKey, value);
        }
    }
    if (!value) return null;
    // Try to parse as JSON, return raw value if parsing fails
    try {
        return JSON.parse(value);
    } catch (e) {
        return value;
    }
}

function removeAdminSession(key) {
    const namespacedKey = `admin_${key}`;
    sessionStorage.removeItem(namespacedKey);
}

function clearAdminSession() {
    // Remove all admin-namespaced keys
    const keys = Object.keys(sessionStorage);
    keys.forEach(key => {
        if (key.startsWith('admin_')) {
            sessionStorage.removeItem(key);
        }
    });
}

// Student session helpers
function setStudentSession(key, value) {
    const namespacedKey = `student_${key}`;
    if (typeof value === 'object') {
        sessionStorage.setItem(namespacedKey, JSON.stringify(value));
    } else {
        sessionStorage.setItem(namespacedKey, value);
    }
}

function getStudentSession(key) {
    const namespacedKey = `student_${key}`;
    const value = sessionStorage.getItem(namespacedKey);
    if (!value) return null;
    
    // Try to parse as JSON, return raw value if parsing fails
    try {
        return JSON.parse(value);
    } catch (e) {
        return value;
    }
}

function removeStudentSession(key) {
    const namespacedKey = `student_${key}`;
    sessionStorage.removeItem(namespacedKey);
}

function clearStudentSession() {
    // Remove all student-namespaced keys
    const keys = Object.keys(sessionStorage);
    keys.forEach(key => {
        if (key.startsWith('student_')) {
            sessionStorage.removeItem(key);
        }
    });
}

// Check if user is authenticated (for any role)
function isAuthenticated() {
    return !!(getAdminSession('token') || getStudentSession('token'));
}

// Get current user data (checks both admin and student)
function getCurrentUser() {
    const adminUser = getAdminSession('user');
    if (adminUser) return { ...adminUser, sessionType: 'admin' };
    
    const studentUser = getStudentSession('user');
    if (studentUser) return { ...studentUser, sessionType: 'student' };
    
    return null;
}

// Get current token (checks both admin and student)
function getCurrentToken() {
    return getAdminSession('token') || getStudentSession('token');
}
