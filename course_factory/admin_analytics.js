/**
 * Admin Analytics Dashboard JavaScript
 * Handles chart rendering, API calls, and data visualization
 */

// Chart instances (global)
let masteryChart = null;
let activityTrendChart = null;
let masteryTrendChart = null;
let engagementTrendChart = null;

// Initialize dashboard on page load
document.addEventListener('DOMContentLoaded', () => {
    // Set default date range (last 30 days)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    
    document.getElementById('startDate').value = formatDate(startDate);
    document.getElementById('endDate').value = formatDate(endDate);
    
    // Load initial data
    loadOverview();
    loadCourses();
    loadTrends();
});

/**
 * Switch between tabs
 */
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tabName}-tab`).classList.add('active');
    
    // Load tab-specific data
    switch(tabName) {
        case 'overview':
            loadOverview();
            break;
        case 'courses':
            loadCourses();
            break;
        case 'trends':
            loadTrends();
            break;
        case 'agents':
            loadAgents();
            break;
    }
}

/**
 * Refresh all analytics data
 */
function refreshAnalytics() {
    const activeTab = document.querySelector('.tab.active').textContent.toLowerCase();
    
    switch(activeTab) {
        case 'overview':
            loadOverview();
            break;
        case 'courses':
            loadCourses();
            break;
        case 'trends':
            loadTrends();
            break;
        case 'agents':
            loadAgents();
            break;
    }
}

/**
 * Load overview analytics
 */
async function loadOverview() {
    try {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        const response = await authenticatedFetch(
            `/api/admin/analytics/overview.php?startDate=${startDate}&endDate=${endDate}`
        );
        
        if (!response.success) {
            throw new Error(response.message || 'Failed to load overview');
        }
        
        // Update platform health metrics
        document.getElementById('totalStudents').textContent = response.platformHealth.totalStudents.toLocaleString();
        document.getElementById('activeCourses').textContent = response.platformHealth.activeCourses;
        document.getElementById('weeklyActiveUsers').textContent = response.platformHealth.weeklyActiveUsers.toLocaleString();
        document.getElementById('avgMastery').textContent = response.engagement.avgMastery.toFixed(1) + '%';
        
        // Update engagement metrics
        document.getElementById('lessonsCompleted').textContent = response.engagement.lessonsCompleted.toLocaleString();
        document.getElementById('quizzesPassed').textContent = response.engagement.quizzesPassed.toLocaleString();
        document.getElementById('studyHours').textContent = response.engagement.totalStudyHours.toLocaleString();
        document.getElementById('highAchievers').textContent = response.engagement.highAchievers.toLocaleString();
        
        // Render mastery distribution chart
        renderMasteryChart(response.masteryDistribution);
        
        // Populate top courses table
        populateTopCourses(response.topCourses);
        
        // Populate top agents table (if in overview)
        if (response.topAgents) {
            populateAgentsTable(response.topAgents);
        }
        
    } catch (error) {
        console.error('Error loading overview:', error);
        alert('Failed to load analytics overview');
    }
}

/**
 * Load course analytics
 */
async function loadCourses() {
    try {
        const loading = document.getElementById('coursesLoading');
        const table = document.getElementById('coursesTable');
        
        loading.style.display = 'block';
        table.style.display = 'none';
        
        const response = await authenticatedFetch('/api/admin/analytics/course.php');
        
        if (!response.success) {
            throw new Error(response.message || 'Failed to load courses');
        }
        
        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '';
        
        response.courses.forEach(course => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${course.course_name}</strong></td>
                <td><span class="status-badge ${getMasteryClass(course.avg_mastery_score)}">${course.difficulty_level}</span></td>
                <td>${course.total_enrolled || 0}</td>
                <td>${course.active_students || 0}</td>
                <td>${course.completed_students || 0}</td>
                <td>${course.completion_rate ? parseFloat(course.completion_rate).toFixed(1) + '%' : 'N/A'}</td>
                <td>${course.avg_mastery_score ? parseFloat(course.avg_mastery_score).toFixed(1) + '%' : 'N/A'}</td>
                <td>${course.avg_study_time_hours ? parseFloat(course.avg_study_time_hours).toFixed(1) + 'h' : 'N/A'}</td>
            `;
            tbody.appendChild(row);
        });
        
        loading.style.display = 'none';
        table.style.display = 'table';
        
    } catch (error) {
        console.error('Error loading courses:', error);
        document.getElementById('coursesLoading').textContent = 'Failed to load course data';
    }
}

/**
 * Load trend analytics
 */
async function loadTrends() {
    try {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const period = document.getElementById('periodSelect').value;
        
        const response = await authenticatedFetch(
            `/api/admin/analytics/timeseries.php?startDate=${startDate}&endDate=${endDate}&period=${period}`
        );
        
        if (!response.success) {
            throw new Error(response.message || 'Failed to load trends');
        }
        
        renderActivityTrend(response.data, period);
        renderMasteryTrend(response.data, period);
        renderEngagementTrend(response.data, period);
        
    } catch (error) {
        console.error('Error loading trends:', error);
        alert('Failed to load trend data');
    }
}

/**
 * Load agent analytics
 */
async function loadAgents() {
    try {
        const response = await authenticatedFetch('/api/admin/analytics/overview.php');
        
        if (!response.success || !response.topAgents) {
            throw new Error('Failed to load agent data');
        }
        
        populateAgentsTable(response.topAgents);
        
    } catch (error) {
        console.error('Error loading agents:', error);
        alert('Failed to load agent data');
    }
}

/**
 * Render mastery distribution pie chart
 */
function renderMasteryChart(distribution) {
    const ctx = document.getElementById('masteryChart');
    
    if (masteryChart) {
        masteryChart.destroy();
    }
    
    masteryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['< 50%', '50-69%', '70-89%', '90%+'],
            datasets: [{
                data: [
                    distribution.below50 || 0,
                    distribution['50to69'] || 0,
                    distribution['70to89'] || 0,
                    distribution['90plus'] || 0
                ],
                backgroundColor: [
                    '#e74c3c',
                    '#f39c12',
                    '#3498db',
                    '#27ae60'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: false
                }
            }
        }
    });
}

/**
 * Render activity trend line chart
 */
function renderActivityTrend(data, period) {
    const ctx = document.getElementById('activityTrendChart');
    
    if (activityTrendChart) {
        activityTrendChart.destroy();
    }
    
    const labels = data.map(d => {
        if (period === 'weekly') return d.week_start;
        if (period === 'monthly') return d.month_start;
        return d.date;
    });
    
    activityTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Active Users',
                    data: data.map(d => d.total_active_users),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.3
                },
                {
                    label: 'New Users',
                    data: data.map(d => d.new_users),
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Render mastery trend line chart
 */
function renderMasteryTrend(data, period) {
    const ctx = document.getElementById('masteryTrendChart');
    
    if (masteryTrendChart) {
        masteryTrendChart.destroy();
    }
    
    const labels = data.map(d => {
        if (period === 'weekly') return d.week_start;
        if (period === 'monthly') return d.month_start;
        return d.date;
    });
    
    masteryTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Mastery Score (%)',
                data: data.map(d => d.avg_mastery_score),
                borderColor: '#9b59b6',
                backgroundColor: 'rgba(155, 89, 182, 0.1)',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

/**
 * Render engagement trend chart
 */
function renderEngagementTrend(data, period) {
    const ctx = document.getElementById('engagementTrendChart');
    
    if (engagementTrendChart) {
        engagementTrendChart.destroy();
    }
    
    const labels = data.map(d => {
        if (period === 'weekly') return d.week_start;
        if (period === 'monthly') return d.month_start;
        return d.date;
    });
    
    engagementTrendChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Lessons Completed',
                    data: data.map(d => d.lessons_completed),
                    backgroundColor: 'rgba(52, 152, 219, 0.7)'
                },
                {
                    label: 'Quizzes Passed',
                    data: data.map(d => d.quizzes_passed),
                    backgroundColor: 'rgba(39, 174, 96, 0.7)'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Populate top courses table
 */
function populateTopCourses(courses) {
    const tbody = document.querySelector('#topCoursesTable tbody');
    tbody.innerHTML = '';
    
    courses.forEach(course => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${course.course_name}</strong></td>
            <td>${course.avg_mastery_score ? parseFloat(course.avg_mastery_score).toFixed(1) + '%' : 'N/A'}</td>
            <td>${course.completion_rate ? parseFloat(course.completion_rate).toFixed(1) + '%' : 'N/A'}</td>
            <td>${course.total_enrolled || 0}</td>
        `;
        tbody.appendChild(row);
    });
}

/**
 * Populate agents table
 */
function populateAgentsTable(agents) {
    const tbody = document.querySelector('#agentsTable tbody');
    tbody.innerHTML = '';
    
    agents.forEach(agent => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${agent.agent_name}</strong></td>
            <td>${agent.total_interactions?.toLocaleString() || 0}</td>
            <td>${agent.unique_users_served?.toLocaleString() || 0}</td>
            <td>${agent.avg_student_mastery ? agent.avg_student_mastery.toFixed(1) + '%' : 'N/A'}</td>
        `;
        tbody.appendChild(row);
    });
}

/**
 * Export data
 */
async function exportData() {
    const dataset = prompt('Enter dataset to export:\n- user_progress\n- course_metrics\n- platform_aggregate\n- agent_metrics', 'user_progress');
    
    if (!dataset) return;
    
    const format = confirm('Export as CSV? (Cancel for JSON)') ? 'csv' : 'json';
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    const url = `/api/admin/analytics/export.php?dataset=${dataset}&format=${format}&startDate=${startDate}&endDate=${endDate}`;
    
    // Open in new window to trigger download
    window.open(url, '_blank');
}

/**
 * Utility: Format date as YYYY-MM-DD
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Utility: Get mastery class for badge styling
 */
function getMasteryClass(mastery) {
    if (!mastery) return 'low';
    if (mastery >= 80) return 'high';
    if (mastery >= 60) return 'medium';
    return 'low';
}

/**
 * Authenticated fetch wrapper (uses admin_auth.js)
 */
async function authenticatedFetch(url, options = {}) {
    const token = getAdminSession('token');
    
    if (!token) {
        window.location.href = 'admin_login.html';
        throw new Error('Not authenticated');
    }
    
    const headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    const response = await fetch(url, {
        ...options,
        headers
    });
    
    if (response.status === 401) {
        localStorage.removeItem('adminToken');
        window.location.href = 'admin_login.html';
        throw new Error('Session expired');
    }
    
    return await response.json();
}

/**
 * Logout
 */
function logout() {
    localStorage.removeItem('adminToken');
    window.location.href = 'admin_login.html';
}
