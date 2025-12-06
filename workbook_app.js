// Workbook Application - Modular Navigation System
// Course → Unit → Lesson Hierarchy

// ==========================================
// STATE MANAGEMENT
// ==========================================

const AppState = {
    currentView: 'courses',  // 'courses', 'units', 'lessons', 'lesson-content'
    selectedCourse: null,
    selectedUnit: null,
    selectedLesson: null,
    courses: [],
    currentCourseData: null,
    studentId: null,
    chatHistory: []
};

// ==========================================
// INITIALIZATION
// ==========================================

document.addEventListener('DOMContentLoaded', async () => {
    // Load student info
    loadStudentInfo();
    
    // Check URL parameters for deep linking
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('course');
    const unitNumber = urlParams.get('unit');
    const lessonNumber = urlParams.get('lesson');
    
    if (courseId && unitNumber && lessonNumber) {
        // Deep link to specific lesson
        await selectCourse(courseId);
        await selectUnit(parseInt(unitNumber));
        await selectLesson(parseInt(lessonNumber));
    } else if (courseId) {
        // Course specified but no unit/lesson - show units for this course
        await selectCourse(courseId);
    } else {
        // No course specified - check session storage for last selected course
        const savedCourse = sessionStorage.getItem('selectedCourse');
        if (savedCourse) {
            // Restore last selected course
            await selectCourse(savedCourse);
        } else {
            // Show course selection
            await showCourseView();
        }
    }
    
    // Setup event listeners
    setupEventListeners();
});

function loadStudentInfo() {
    const user = JSON.parse(sessionStorage.getItem('user') || '{}');
    if (user.username) {
        document.getElementById('studentName').textContent = user.username;
        AppState.studentId = user.user_id;
    }
}

// ==========================================
// COURSE DISCOVERY
// ==========================================

async function scanAvailableCourses() {
    try {
        // Fetch available courses from API
        const response = await fetch('api/course/get_available_courses.php');
        const data = await response.json();
        
        if (data.success && data.courses) {
            // Transform API response to workbook format
            return data.courses.map(course => ({
                id: course.courseId,
                name: course.courseName,
                subject: course.subject,
                level: course.level,
                icon: course.icon || '📚',
                description: course.description || `${course.subject} course at ${course.level} level`,
                disabled: !course.available
            }));
        } else {
            console.warn('No courses found in API response');
            return [];
        }
    } catch (error) {
        console.error('Error fetching courses:', error);
        // Fallback to empty array if API fails
        return [];
    }
}

// ==========================================
// DATA LOADING
// ==========================================

async function loadCourse(courseId) {
    try {
        const response = await fetch(`api/course/courses/course_${courseId}.json`);
        if (!response.ok) {
            throw new Error(`Course file not found: course_${courseId}.json`);
        }
        
        const courseData = await response.json();
        AppState.currentCourseData = courseData;
        return courseData;
    } catch (error) {
        console.error('Error loading course:', error);
        throw error;
    }
}

async function loadLesson(courseId, unitNumber, lessonNumber) {
    try {
        // Load course data if not already loaded
        if (!AppState.currentCourseData || AppState.selectedCourse !== courseId) {
            await loadCourse(courseId);
        }
        
        const courseData = AppState.currentCourseData;
        
        // Find the unit
        const unit = courseData.units.find(u => u.unitNumber === unitNumber);
        if (!unit) {
            throw new Error(`Unit ${unitNumber} not found in course`);
        }
        
        // Find the lesson
        const lesson = unit.lessons.find(l => l.lessonNumber === lessonNumber);
        if (!lesson) {
            throw new Error(`Lesson ${lessonNumber} not found in Unit ${unitNumber}`);
        }
        
        // Check if lesson is generated (not just an outline)
        if (lesson.status === 'outline' || !lesson.explanation || lesson.explanation === '') {
            throw new Error('Lesson content not yet generated');
        }
        
        // Enrich lesson with course metadata
        return {
            ...lesson,
            courseName: courseData.courseName || 'Unknown Course',
            subject: courseData.subject || 'Unknown',
            level: courseData.level || 'Unknown',
            unitTitle: unit.unitTitle || `Unit ${unitNumber}`,
            unitNumber: unitNumber,
            courseId: courseId
        };
    } catch (error) {
        console.error('Error loading lesson:', error);
        throw error;
    }
}

// ==========================================
// VIEW RENDERING
// ==========================================

async function showCourseView() {
    AppState.currentView = 'courses';
    AppState.selectedCourse = null;
    AppState.selectedUnit = null;
    AppState.selectedLesson = null;
    
    // Update breadcrumb
    updateBreadcrumb([
        { label: '📚 Workbook', action: null }
    ]);
    
    // Update view mode buttons
    document.getElementById('viewCourses').classList.add('active');
    document.getElementById('viewUnits').style.display = 'none';
    document.getElementById('viewLessons').style.display = 'none';
    
    // Show navigation panel, hide lesson panel
    document.getElementById('navigationPanel').style.display = 'block';
    document.getElementById('lessonPanel').style.display = 'none';
    
    // Load and display courses
    const courses = await scanAvailableCourses();
    AppState.courses = courses;
    
    const navContent = document.getElementById('navContent');
    navContent.innerHTML = `
        <h2 style="margin-bottom: 1rem;">Select a Course</h2>
        <div class="nav-grid">
            ${courses.map(course => `
                <div class="nav-card ${course.disabled ? 'disabled' : ''}" 
                     onclick="${course.disabled ? '' : `selectCourse('${course.id}')`}">
                    <div class="nav-card-icon">${course.icon}</div>
                    <div class="nav-card-title">${course.name}</div>
                    <div class="nav-card-meta">${course.subject} • ${course.level}</div>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-light);">
                        ${course.description}
                    </p>
                    ${course.disabled ? '<p style="font-size: 0.85rem; color: var(--primary); font-weight: 600;">🔒 Coming Soon</p>' : ''}
                </div>
            `).join('')}
        </div>
    `;
}

async function showUnitView(courseId) {
    AppState.currentView = 'units';
    AppState.selectedUnit = null;
    AppState.selectedLesson = null;
    
    // Load course data
    try {
        const courseData = await loadCourse(courseId);
        
        // Ensure courses are loaded for breadcrumb
        if (!AppState.courses || AppState.courses.length === 0) {
            AppState.courses = await scanAvailableCourses();
        }
        const course = AppState.courses.find(c => c.id === courseId);
        
        // Fallback to courseData if course not found in AppState
        const courseName = course ? course.name : (courseData.courseName || courseId);
        
        // Update breadcrumb
        updateBreadcrumb([
            { label: '📚 Workbook', action: () => showCourseView() },
            { label: courseName, action: null }
        ]);
        
        // Update view mode buttons
        document.getElementById('viewCourses').classList.remove('active');
        document.getElementById('viewUnits').style.display = 'inline-block';
        document.getElementById('viewUnits').classList.add('active');
        document.getElementById('viewLessons').style.display = 'none';
        
        // Count lessons per unit
        const units = courseData.units.map(unit => {
            const generatedCount = unit.lessons.filter(l => 
                l.status !== 'outline' && l.explanation && l.explanation !== ''
            ).length;
            return {
                ...unit,
                totalLessons: unit.lessons.length,
                generatedLessons: generatedCount
            };
        });
        
        const navContent = document.getElementById('navContent');
        navContent.innerHTML = `
            <h2 style="margin-bottom: 1rem;">Select a Unit</h2>
            <div class="nav-grid">
                ${units.map(unit => `
                    <div class="nav-card" onclick="selectUnit(${unit.unitNumber})">
                        <div class="nav-card-icon">📖</div>
                        <div class="nav-card-title">Unit ${unit.unitNumber}</div>
                        <div class="nav-card-meta">${unit.unitTitle}</div>
                        <p style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-light);">
                            ${unit.generatedLessons} of ${unit.totalLessons} lessons available
                        </p>
                    </div>
                `).join('')}
            </div>
        `;
    } catch (error) {
        showError('Failed to load course units', error.message);
    }
}

async function showLessonView(unitNumber) {
    AppState.currentView = 'lessons';
    AppState.selectedLesson = null;
    
    const courseData = AppState.currentCourseData;
    const course = AppState.courses.find(c => c.id === AppState.selectedCourse);
    const unit = courseData.units.find(u => u.unitNumber === unitNumber);
    
    // Update breadcrumb
    updateBreadcrumb([
        { label: '📚 Workbook', action: () => showCourseView() },
        { label: course.name, action: () => showUnitView(AppState.selectedCourse) },
        { label: `Unit ${unitNumber}`, action: null }
    ]);
    
    // Update view mode buttons
    document.getElementById('viewUnits').classList.remove('active');
    document.getElementById('viewLessons').style.display = 'inline-block';
    document.getElementById('viewLessons').classList.add('active');
    
    const navContent = document.getElementById('navContent');
    navContent.innerHTML = `
        <h2 style="margin-bottom: 1rem;">${unit.unitTitle}</h2>
        <div class="nav-grid">
            ${unit.lessons.map(lesson => {
                const isAvailable = lesson.status !== 'outline' && lesson.explanation && lesson.explanation !== '';
                return `
                    <div class="nav-card ${!isAvailable ? 'disabled' : ''}" 
                         onclick="${isAvailable ? `selectLesson(${lesson.lessonNumber})` : ''}">
                        <div class="nav-card-icon">${isAvailable ? '📄' : '🔒'}</div>
                        <div class="nav-card-title">Lesson ${lesson.lessonNumber}</div>
                        <div class="nav-card-meta">${lesson.lessonTitle}</div>
                        ${!isAvailable ? '<p style="font-size: 0.85rem; color: var(--primary); font-weight: 600; margin-top: 0.5rem;">Coming Soon</p>' : ''}
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// ==========================================
// SELECTION HANDLERS
// ==========================================

async function selectCourse(courseId) {
    AppState.selectedCourse = courseId;
    await showUnitView(courseId);
}

async function selectUnit(unitNumber) {
    AppState.selectedUnit = unitNumber;
    await showLessonView(unitNumber);
}

async function selectLesson(lessonNumber) {
    AppState.selectedLesson = lessonNumber;
    
    try {
        // Load lesson data
        const lesson = await loadLesson(
            AppState.selectedCourse,
            AppState.selectedUnit,
            lessonNumber
        );
        
        // Render lesson
        renderLesson(lesson);
        
        // Update navigation buttons
        updateLessonNavigation();
        
        // Show lesson panel, hide navigation panel
        document.getElementById('navigationPanel').style.display = 'none';
        document.getElementById('lessonPanel').style.display = 'block';
        
        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('course', AppState.selectedCourse);
        url.searchParams.set('unit', AppState.selectedUnit);
        url.searchParams.set('lesson', lessonNumber);
        window.history.pushState({}, '', url);
        
        // Notify chat agent
        addChatMessage('system', `Loaded: ${lesson.lessonTitle} (Unit ${lesson.unitNumber}, Lesson ${lessonNumber})`);
        
    } catch (error) {
        showError('Failed to load lesson', error.message);
    }
}

// ==========================================
// LESSON RENDERING
// ==========================================

function renderLesson(lesson) {
    // Update title and metadata
    document.getElementById('lessonTitle').textContent = lesson.lessonTitle;
    document.getElementById('courseInfo').textContent = 
        `${lesson.courseName} • ${lesson.unitTitle} • Lesson ${lesson.lessonNumber}`;
    
    // Render lesson content
    const contentHtml = `
        <div class="alert alert-info">
            <strong>🔒 Session Monitored:</strong> Your learning session is being monitored for academic integrity.
        </div>
        
        <section style="margin: 2rem 0;">
            <h2>📋 Learning Objectives</h2>
            <ul>
                ${lesson.objectives.map(obj => `<li>${obj}</li>`).join('')}
            </ul>
        </section>
        
        <section style="margin: 2rem 0;">
            <h2>📖 Explanation</h2>
            <div class="lesson-text">
                ${lesson.explanation}
            </div>
        </section>
        
        ${lesson.guidedExamples && lesson.guidedExamples.length > 0 ? `
            <section style="margin: 2rem 0;">
                <h2>💡 Guided Examples</h2>
                ${lesson.guidedExamples.map((example, idx) => `
                    <div class="example-box">
                        <h4>Example ${idx + 1}</h4>
                        <p><strong>Problem:</strong> ${example.problem}</p>
                        <p><strong>Solution:</strong> ${example.solution}</p>
                        ${example.explanation ? `<p><strong>Why:</strong> ${example.explanation}</p>` : ''}
                    </div>
                `).join('')}
            </section>
        ` : ''}
        
        ${lesson.practiceProblems && lesson.practiceProblems.length > 0 ? `
            <section style="margin: 2rem 0;">
                <h2>✏️ Practice Problems</h2>
                ${lesson.practiceProblems.map((problem, idx) => `
                    <div class="example-box">
                        <p><strong>Problem ${idx + 1}:</strong> ${problem.problem}</p>
                        <details style="margin-top: 0.5rem;">
                            <summary style="cursor: pointer; color: var(--primary);">Show Answer</summary>
                            <p style="margin-top: 0.5rem;"><strong>Answer:</strong> ${problem.answer}</p>
                        </details>
                    </div>
                `).join('')}
            </section>
        ` : ''}
        
        ${lesson.quiz && lesson.quiz.length > 0 ? `
            <section style="margin: 2rem 0;">
                <h2>📝 Quiz Questions</h2>
                ${lesson.quiz.map((question, idx) => `
                    <div class="example-box">
                        <p><strong>Question ${idx + 1}:</strong> ${question.question}</p>
                        ${question.options ? `
                            <ul style="list-style: none; padding-left: 0;">
                                ${question.options.map(opt => `<li style="padding: 0.25rem 0;">⚪ ${opt}</li>`).join('')}
                            </ul>
                        ` : ''}
                        <details style="margin-top: 0.5rem;">
                            <summary style="cursor: pointer; color: var(--primary);">Show Answer</summary>
                            <p style="margin-top: 0.5rem;"><strong>Answer:</strong> ${question.answer}</p>
                            ${question.explanation ? `<p><strong>Explanation:</strong> ${question.explanation}</p>` : ''}
                        </details>
                    </div>
                `).join('')}
            </section>
        ` : ''}
        
        ${lesson.vocabulary && lesson.vocabulary.length > 0 ? `
            <section style="margin: 2rem 0;">
                <h2>📚 Vocabulary</h2>
                <ul>
                    ${lesson.vocabulary.map(vocab => `
                        <li><strong>${vocab.term}:</strong> ${vocab.definition}</li>
                    `).join('')}
                </ul>
            </section>
        ` : ''}
    `;
    
    document.getElementById('lessonContent').innerHTML = contentHtml;
    
    // Update video tab
    document.getElementById('videoTitle').textContent = lesson.lessonTitle;
    document.getElementById('videoDuration').textContent = 
        lesson.duration ? `Duration: ${lesson.duration}` : '';
    
    // Update notes tab
    document.getElementById('lessonSummary').innerHTML = lesson.summary || 
        '<p style="color: var(--text-light);">No summary available for this lesson.</p>';
    
    // Update practice tab (quiz questions)
    if (lesson.quiz && lesson.quiz.length > 0) {
        document.getElementById('practiceQuestions').innerHTML = lesson.quiz.map((q, idx) => `
            <div class="example-box" style="margin-bottom: 1rem;">
                <p><strong>Question ${idx + 1}:</strong> ${q.question}</p>
                ${q.options ? `
                    <ul style="list-style: none; padding-left: 0;">
                        ${q.options.map(opt => `<li style="padding: 0.25rem 0;">⚪ ${opt}</li>`).join('')}
                    </ul>
                ` : ''}
            </div>
        `).join('');
    } else {
        document.getElementById('practiceQuestions').innerHTML = 
            '<p style="color: var(--text-light);">No practice questions available for this lesson.</p>';
    }
}

// ==========================================
// NAVIGATION HELPERS
// ==========================================

function updateBreadcrumb(items) {
    const breadcrumb = document.getElementById('breadcrumb');
    breadcrumb.innerHTML = items.map((item, idx) => {
        if (item.action) {
            return `<a href="#" onclick="event.preventDefault(); (${item.action})(); return false;">${item.label}</a>`;
        } else {
            return `<span>${item.label}</span>`;
        }
    }).join(' <span>›</span> ');
}

function updateLessonNavigation() {
    const courseData = AppState.currentCourseData;
    const unit = courseData.units.find(u => u.unitNumber === AppState.selectedUnit);
    const currentLesson = AppState.selectedLesson;
    
    // Find previous lesson
    let prevLesson = null;
    if (currentLesson > 1) {
        const prev = unit.lessons.find(l => l.lessonNumber === currentLesson - 1);
        if (prev && prev.status !== 'outline' && prev.explanation) {
            prevLesson = currentLesson - 1;
        }
    }
    
    // Find next lesson
    let nextLesson = null;
    if (currentLesson < unit.lessons.length) {
        const next = unit.lessons.find(l => l.lessonNumber === currentLesson + 1);
        if (next && next.status !== 'outline' && next.explanation) {
            nextLesson = currentLesson + 1;
        }
    }
    
    // Update buttons
    const prevBtn = document.getElementById('prevLesson');
    const nextBtn = document.getElementById('nextLesson');
    
    if (prevLesson) {
        prevBtn.disabled = false;
        prevBtn.onclick = () => selectLesson(prevLesson);
    } else {
        prevBtn.disabled = true;
        prevBtn.onclick = null;
    }
    
    if (nextLesson) {
        nextBtn.disabled = false;
        nextBtn.onclick = () => selectLesson(nextLesson);
    } else {
        nextBtn.disabled = true;
        nextBtn.onclick = null;
    }
}

// ==========================================
// ERROR HANDLING
// ==========================================

function showError(title, message) {
    const navContent = document.getElementById('navContent');
    navContent.innerHTML = `
        <div class="error-message">
            <h3>⚠️ ${title}</h3>
            <p>${message}</p>
            <button class="btn btn-primary" onclick="showCourseView()" style="margin-top: 1rem;">
                ← Back to Courses
            </button>
        </div>
    `;
}

// ==========================================
// EVENT LISTENERS
// ==========================================

function setupEventListeners() {
    // Media tabs
    const mediaTabs = document.querySelectorAll('.media-tab');
    mediaTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active from all tabs and panels
            document.querySelectorAll('.media-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.media-panel').forEach(p => p.classList.remove('active'));
            
            // Add active to clicked tab and corresponding panel
            tab.classList.add('active');
            const tabName = tab.getAttribute('data-tab');
            document.getElementById(`${tabName}-panel`).classList.add('active');
        });
    });
    
    // Back to lessons button
    document.getElementById('backToLessons').addEventListener('click', () => {
        document.getElementById('navigationPanel').style.display = 'block';
        document.getElementById('lessonPanel').style.display = 'none';
        showLessonView(AppState.selectedUnit);
    });
    
    // View mode buttons
    document.getElementById('viewCourses').addEventListener('click', () => showCourseView());
    document.getElementById('viewUnits').addEventListener('click', () => {
        if (AppState.selectedCourse) {
            showUnitView(AppState.selectedCourse);
        }
    });
    document.getElementById('viewLessons').addEventListener('click', () => {
        if (AppState.selectedUnit) {
            showLessonView(AppState.selectedUnit);
        }
    });
    
    // Chat functionality
    const chatInput = document.getElementById('workbookChatInput');
    const sendBtn = document.getElementById('workbookSendBtn');
    
    sendBtn.addEventListener('click', sendChatMessage);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendChatMessage();
    });
}

// ==========================================
// CHAT FUNCTIONALITY
// ==========================================

async function sendChatMessage() {
    const input = document.getElementById('workbookChatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message to chat
    addChatMessage('user', message);
    input.value = '';
    
    // Get current lesson context
    let context = '';
    if (AppState.selectedLesson) {
        const lesson = await loadLesson(
            AppState.selectedCourse,
            AppState.selectedUnit,
            AppState.selectedLesson
        );
        context = `Currently studying: ${lesson.lessonTitle} (${lesson.courseName}, Unit ${lesson.unitNumber})`;
    }
    
    // Send to agent API
    try {
        const response = await fetch('api/agent/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userId: AppState.studentId || 1,
                agentId: 1, // Professor Hawkeinstein
                message: context ? `${context}\n\nStudent question: ${message}` : message
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            addChatMessage('agent', data.response);
        } else {
            addChatMessage('agent', 'Sorry, I encountered an error. Please try again.');
        }
    } catch (error) {
        console.error('Chat error:', error);
        addChatMessage('agent', 'Sorry, I could not connect to the server.');
    }
}

function addChatMessage(type, message) {
    const chatMessages = document.getElementById('workbookChat');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    
    if (type === 'agent') {
        messageDiv.innerHTML = `
            <div class="message-avatar">PH</div>
            <div class="message-content">
                <p><strong>Professor Hawkeinstein</strong></p>
                <p>${message}</p>
                <small style="opacity: 0.7;">Just now</small>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="message-content">
                <p>${message}</p>
                <small style="opacity: 0.7;">Just now</small>
            </div>
        `;
    }
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    AppState.chatHistory.push({ type, message, timestamp: new Date() });
}
