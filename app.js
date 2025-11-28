// AI-Powered Educational Platform - Frontend JavaScript Utilities
// Common functions for API calls, biometric access, and UI interactions

const API_BASE_URL = '/api';

// ============================================
// Authentication & Session Management
// ============================================

const Auth = {
    /**
     * Login user with credentials
     */
    async login(username, password) {
        try {
            const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                localStorage.setItem('sessionToken', data.token);
                localStorage.setItem('user', JSON.stringify(data.user));
                return { success: true, user: data.user };
            } else {
                return { success: false, message: data.message };
            }
        } catch (error) {
            console.error('Login error:', error);
            return { success: false, message: 'Connection error' };
        }
    },

    /**
     * Logout current user
     */
    async logout() {
        try {
            await fetch(`${API_BASE_URL}/auth/logout.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                }
            });
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            localStorage.removeItem('sessionToken');
            localStorage.removeItem('user');
            window.location.href = 'login.html';
        }
    },

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!localStorage.getItem('sessionToken');
    },

    /**
     * Get current user
     */
    getCurrentUser() {
        const userStr = localStorage.getItem('user');
        return userStr ? JSON.parse(userStr) : null;
    },

    /**
     * Require authentication (redirect if not logged in)
     */
    requireAuth() {
        if (!this.isAuthenticated()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    }
};

// ============================================
// Biometric Authentication
// ============================================

const Biometric = {
    mediaStream: null,
    isActive: false,
    videoElement: null,
    canvasElement: null,

    /**
     * Initialize camera and microphone access
     */
    async initialize(videoElementId) {
        try {
            this.mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480 },
                audio: true
            });

            this.videoElement = document.getElementById(videoElementId) || document.createElement('video');
            this.videoElement.srcObject = this.mediaStream;
            this.videoElement.autoplay = true;
            
            this.canvasElement = document.createElement('canvas');
            this.isActive = true;

            return { success: true };
        } catch (error) {
            console.error('Biometric initialization error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Capture current frame for facial recognition
     */
    captureFrame() {
        if (!this.videoElement || !this.isActive) return null;

        this.canvasElement.width = this.videoElement.videoWidth;
        this.canvasElement.height = this.videoElement.videoHeight;
        
        const ctx = this.canvasElement.getContext('2d');
        ctx.drawImage(this.videoElement, 0, 0);
        
        return this.canvasElement.toDataURL('image/jpeg', 0.8);
    },

    /**
     * Verify facial recognition with backend
     */
    async verifyFace(userId) {
        const frame = this.captureFrame();
        if (!frame) return { verified: false };

        try {
            const response = await fetch(`${API_BASE_URL}/biometric/verify-face.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                },
                body: JSON.stringify({ userId, frame })
            });

            return await response.json();
        } catch (error) {
            console.error('Face verification error:', error);
            return { verified: false, error: error.message };
        }
    },

    /**
     * Start continuous monitoring for anti-cheating
     */
    startMonitoring(userId, interval = 5000) {
        if (!this.isActive) return;

        this.monitoringInterval = setInterval(async () => {
            const faceResult = await this.verifyFace(userId);
            
            if (!faceResult.verified) {
                console.warn('Face verification failed - possible cheating attempt');
                this.onCheatingDetected && this.onCheatingDetected('face_not_verified');
            }
        }, interval);
    },

    /**
     * Stop monitoring and cleanup
     */
    stop() {
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
        }

        if (this.mediaStream) {
            this.mediaStream.getTracks().forEach(track => track.stop());
        }

        this.isActive = false;
    },

    /**
     * Callback for cheating detection
     */
    onCheatingDetected: null
};

// ============================================
// AI Agent Communication
// ============================================

const Agent = {
    /**
     * Send message to AI agent
     */
    async sendMessage(agentId, message, context = {}) {
        try {
            const user = Auth.getCurrentUser();
            const response = await fetch(`${API_BASE_URL}/agent/chat.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                },
                body: JSON.stringify({
                    userId: user.userId,
                    agentId,
                    message,
                    context
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Agent communication error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get agent conversation history
     */
    async getHistory(agentId, limit = 50) {
        try {
            const user = Auth.getCurrentUser();
            const response = await fetch(
                `${API_BASE_URL}/agent/history.php?userId=${user.userId}&agentId=${agentId}&limit=${limit}`,
                {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                    }
                }
            );

            return await response.json();
        } catch (error) {
            console.error('Error fetching history:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get available agents for user
     */
    async getAvailableAgents() {
        try {
            const response = await fetch(`${API_BASE_URL}/agent/list.php`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Error fetching agents:', error);
            return { success: false, error: error.message };
        }
    }
};

// ============================================
// Progress Tracking
// ============================================

const Progress = {
    /**
     * Get student progress overview
     */
    async getOverview() {
        try {
            const user = Auth.getCurrentUser();
            const response = await fetch(
                `${API_BASE_URL}/progress/overview.php?userId=${user.userId}`,
                {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                    }
                }
            );

            return await response.json();
        } catch (error) {
            console.error('Error fetching progress:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get progress for specific course
     */
    async getCourseProgress(courseId) {
        try {
            const user = Auth.getCurrentUser();
            const response = await fetch(
                `${API_BASE_URL}/progress/course.php?userId=${user.userId}&courseId=${courseId}`,
                {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                    }
                }
            );

            return await response.json();
        } catch (error) {
            console.error('Error fetching course progress:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Update progress metric
     */
    async updateMetric(courseId, metricType, metricValue, notes = '') {
        try {
            const user = Auth.getCurrentUser();
            const response = await fetch(`${API_BASE_URL}/progress/update.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                },
                body: JSON.stringify({
                    userId: user.userId,
                    courseId,
                    metricType,
                    metricValue,
                    notes
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Error updating progress:', error);
            return { success: false, error: error.message };
        }
    }
};

// ============================================
// Course Management
// ============================================

const Course = {
    /**
     * Get user's enrolled courses
     */
    async getEnrolledCourses() {
        try {
            const user = Auth.getCurrentUser();
            const response = await fetch(
                `${API_BASE_URL}/course/enrolled.php?userId=${user.userId}`,
                {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                    }
                }
            );

            return await response.json();
        } catch (error) {
            console.error('Error fetching courses:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get course details
     */
    async getCourse(courseId) {
        try {
            const response = await fetch(
                `${API_BASE_URL}/course/detail.php?courseId=${courseId}`,
                {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                    }
                }
            );

            return await response.json();
        } catch (error) {
            console.error('Error fetching course details:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get available courses (for enrollment)
     */
    async getAvailableCourses() {
        try {
            const response = await fetch(`${API_BASE_URL}/course/available.php`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('sessionToken')}`
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Error fetching available courses:', error);
            return { success: false, error: error.message };
        }
    }
};

// ============================================
// UI Utilities
// ============================================

const UI = {
    /**
     * Show loading spinner
     */
    showLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = '<div class="spinner"></div>';
        }
    },

    /**
     * Show alert message
     */
    showAlert(message, type = 'info', duration = 5000) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '10000';
        alertDiv.style.minWidth = '300px';

        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.remove();
        }, duration);
    },

    /**
     * Format timestamp
     */
    formatTime(date) {
        return new Date(date).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Format date
     */
    formatDate(date) {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
};

// ============================================
// Export for module usage (if using modules)
// ============================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Auth, Biometric, Agent, Progress, Course, UI };
}

// ============================================
// Global initialization
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Check session on page load
    const sessionToken = localStorage.getItem('sessionToken');
    if (sessionToken) {
        // Validate session is still active
        fetch(`${API_BASE_URL}/auth/validate.php`, {
            headers: { 'Authorization': `Bearer ${sessionToken}` }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.valid) {
                Auth.logout();
            }
        })
        .catch(err => console.error('Session validation error:', err));
    }
});
