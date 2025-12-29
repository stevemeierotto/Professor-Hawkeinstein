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
    const user = getStudentSession('user') || {};
    if (user.username) {
        document.getElementById('studentName').textContent = user.username;
        AppState.studentId = user.user_id || user.userId;
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
        
        // Fetch actual lesson content from database (convert to 0-based indexing)
        const response = await fetch(`api/course/get_lesson_content.php?courseId=${courseId}&unitIndex=${unitNumber - 1}&lessonIndex=${lessonNumber - 1}`);
        
        if (!response.ok) {
            console.error('HTTP Error:', response.status, response.statusText);
            throw new Error(`Failed to fetch lesson content: ${response.status} ${response.statusText}`);
        }
        
        const contentData = await response.json();
        
        console.log('API Response:', contentData);
        console.log('success:', contentData.success, 'hasContent:', contentData.hasContent);
        
        if (!contentData.success || !contentData.hasContent) {
            console.error('Content validation failed. Response:', JSON.stringify(contentData, null, 2));
            throw new Error('Lesson content not yet generated');
        }
        
        // Enrich lesson with course metadata and actual content
        return {
            ...lesson,
            courseName: courseData.courseName || 'Unknown Course',
            subject: courseData.subject || 'Unknown',
            level: courseData.level || 'Unknown',
            unitTitle: unit.unitTitle || `Unit ${unitNumber}`,
            unitNumber: unitNumber,
            // Add fetched content
            explanation: contentData.content.text,
            contentHtml: contentData.content.html,
            videoUrl: contentData.content.videoUrl,
            questions: contentData.questions,
            questionCounts: contentData.questionCounts,
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

function renderMediaPanel(lesson) {
    const mediaPanel = document.getElementById('mediaPanel');
    if (!mediaPanel) return;
    
    const hasVideo = lesson.videoUrl && lesson.videoUrl.trim() !== '';
    
    mediaPanel.innerHTML = `
        <!-- Lesson Title in Sidebar -->
        <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--primary-color); color: white; border-radius: 12px;">
            <h3 style="margin: 0; font-size: 1.1rem;">${lesson.lessonTitle}</h3>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; opacity: 0.9;">Lesson ${lesson.lessonNumber}</p>
        </div>
        
        <!-- Video Section -->
        <div style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; overflow: hidden; ${hasVideo ? 'cursor: pointer;' : ''}">
            <div style="padding: 1rem;">
                <h3 style="color: white; margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    🎥 Video Lesson
                    ${hasVideo ? '<span style="font-size: 0.8rem; opacity: 0.8;">Click to expand</span>' : ''}
                </h3>
            </div>
            ${hasVideo ? `
            <div class="video-thumbnail" data-video-url="${lesson.videoUrl}" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: rgba(0,0,0,0.2);">
                <iframe 
                    src="https://www.youtube.com/embed/${lesson.videoUrl}?enablejsapi=1" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen
                    id="videoPlayer"
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                </iframe>
            </div>
            <button class="expand-video-btn" style="width: 100%; padding: 0.5rem; background: rgba(255,255,255,0.9); border: none; cursor: pointer; font-size: 0.85rem; color: #667eea; font-weight: 600;">
                ⛶ Expand to 75%
            </button>
            ` : `
            <div style="padding: 1.5rem; background: rgba(255, 255, 255, 0.9); text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📹</div>
                <p style="color: #6c757d; font-size: 0.9rem; margin: 0;">No video available</p>
                <p style="color: #6c757d; font-size: 0.75rem; margin-top: 0.25rem;">Video content can be added by instructors</p>
            </div>
            `}
        </div>
        
        <!-- Visuals Section -->
        <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 12px; overflow: hidden; border: 2px dashed #6c63ff;">
            <div style="padding: 1rem;">
                <h3 style="margin: 0; font-size: 1rem;">🎨 Visual Learning</h3>
            </div>
            <div style="padding: 1.5rem; background: white; text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📊📈🔬</div>
                <p style="color: var(--text-light); font-size: 0.85rem; margin: 0;">Diagrams & visuals</p>
                <p style="color: var(--text-light); font-size: 0.75rem; margin-top: 0.25rem;">Coming soon</p>
            </div>
        </div>
    `;
    
    // Add click handler for expand button
    if (hasVideo) {
        const expandBtn = mediaPanel.querySelector('.expand-video-btn');
        console.log('Expand button found:', expandBtn);
        if (expandBtn) {
            expandBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Expand button clicked!');
                expandVideo(lesson.videoUrl);
            });
        } else {
            console.error('Expand button NOT found!');
        }
    }
}

function expandVideo(videoUrl) {
    console.log('expandVideo called with:', videoUrl);
    
    // Remove any existing modal first
    const existingModal = document.getElementById('videoModal');
    if (existingModal) existingModal.remove();
    
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'videoModal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(0, 0, 0, 0.9)';
    modal.style.zIndex = '10000';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.padding = '2rem';
    modal.style.boxSizing = 'border-box';
    
    modal.innerHTML = `
        <div style="position: relative; width: 75vw; max-width: 1200px; height: 75vh; max-height: 80vh;">
            <!-- Close button - fixed position in top right corner of screen -->
            <button id="closeVideoBtn" style="position: fixed; top: 20px; right: 20px; background: #ff4757; color: white; border: none; border-radius: 30px; padding: 12px 24px; font-size: 1.1rem; font-weight: bold; cursor: pointer; z-index: 10002; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.4); transition: all 0.2s;">
                ✕ Close Video
            </button>
            <iframe 
                src="https://www.youtube.com/embed/${videoUrl}?autoplay=1" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen
                style="width: 100%; height: 100%; border-radius: 12px; border: 3px solid white;">
            </iframe>
        </div>
    `;
    
    document.body.appendChild(modal);
    console.log('Modal appended to body');
    
    // Close button handler
    document.getElementById('closeVideoBtn').addEventListener('click', closeVideoModal);
    
    // Click outside to close
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeVideoModal();
    });
    
    // ESC key to close
    document.addEventListener('keydown', handleEscapeKey);
}

function handleEscapeKey(e) {
    if (e.key === 'Escape') closeVideoModal();
}

function closeVideoModal() {
    const modal = document.getElementById('videoModal');
    if (modal) {
        modal.remove();
        document.removeEventListener('keydown', handleEscapeKey);
    }
}

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
        
        // Show all units
        const units = courseData.units.map(unit => ({
            ...unit,
            totalLessons: unit.lessons.length
        }));
        
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
                            ${unit.totalLessons} lesson${unit.totalLessons !== 1 ? 's' : ''}
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
                // Always show lessons as available - content check happens on click
                return `
                    <div class="nav-card" onclick="selectLesson(${lesson.lessonNumber})">
                        <div class="nav-card-icon">📄</div>
                        <div class="nav-card-title">Lesson ${lesson.lessonNumber}</div>
                        <div class="nav-card-meta">${lesson.lessonTitle}</div>
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
    console.log('Rendering lesson with videoUrl:', lesson.videoUrl);
    
    // Update metadata only (title is now in media panel)
    document.getElementById('lessonTitle').textContent = lesson.lessonTitle;
    document.getElementById('courseInfo').textContent = 
        `${lesson.courseName} • ${lesson.unitTitle}`;
    
    // Render media panel (video and visuals)
    renderMediaPanel(lesson);
    
    // Render lesson content
    const contentHtml = `
        ${lesson.objectives && lesson.objectives.length > 0 ? `
        <section style="margin: 2rem 0;">
            <h2>📋 Learning Objectives</h2>
            <ul>
                ${lesson.objectives.map(obj => `<li>${obj}</li>`).join('')}
            </ul>
        </section>
        ` : ''}
        
        <section style="margin: 2rem 0;">
            <h2>📖 Lesson Content</h2>
            <div class="lesson-text" style="line-height: 1.8; font-size: 1.05rem;">
                ${lesson.contentHtml || lesson.explanation.replace(/\n/g, '<br>')}
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
    
    // Enable/disable quiz button based on question availability
    const openQuizBtn = document.getElementById('openQuizBtn');
    if (openQuizBtn) {
        if (lesson.questions && lesson.questionCounts && lesson.questionCounts.total > 0) {
            openQuizBtn.disabled = false;
            openQuizBtn.textContent = `✏️ Take Lesson Quiz (${lesson.questionCounts.total} questions)`;
        } else {
            openQuizBtn.disabled = true;
            openQuizBtn.textContent = '✏️ Quiz Not Available';
        }
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
    
    // Find previous lesson (always available if exists)
    let prevLesson = null;
    if (currentLesson > 1) {
        prevLesson = currentLesson - 1;
    }
    
    // Find next lesson (always available if exists)
    let nextLesson = null;
    if (currentLesson < unit.lessons.length) {
        nextLesson = currentLesson + 1;
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
    // Quiz button - opens quiz in new window
    const openQuizBtn = document.getElementById('openQuizBtn');
    if (openQuizBtn) {
        openQuizBtn.addEventListener('click', () => {
            if (!AppState.currentCourseData || !AppState.selectedLesson) {
                alert('Please select a lesson first');
                return;
            }

            const courseData = AppState.currentCourseData;
            const unit = courseData.units.find(u => u.unitNumber === AppState.selectedUnit);
            const lesson = unit.lessons.find(l => l.lessonNumber === AppState.selectedLesson);
            
            // Build quiz URL with parameters
            const quizUrl = `quiz.html?` +
                `courseId=${encodeURIComponent(AppState.selectedCourse)}` +
                `&unitIndex=${AppState.selectedUnit - 1}` +
                `&lessonIndex=${AppState.selectedLesson - 1}` +
                `&courseName=${encodeURIComponent(courseData.title)}` +
                `&lessonTitle=${encodeURIComponent(lesson.title)}`;
            
            // Open quiz in new window
            window.open(quizUrl, 'quiz_window', 'width=1000,height=800,scrollbars=yes,resizable=yes');
        });
    }
    
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
    
    // Send to agent API via PHP proxy
    try {
        const token = getStudentSession('token') || '';
        
        const payload = {
            agentId: 1, // Professor Hawkeinstein
            message: context ? `${context}\n\nStudent question: ${message}` : message
        };
        
        console.log('[Workbook Chat] Calling PHP proxy with payload:', payload);
        
        const response = await fetch('api/agent/chat.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });
        
        const responseText = await response.text();
        console.log('[Workbook Chat] Raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('[Workbook Chat] JSON parse error:', parseError);
            console.error('[Workbook Chat] Response was:', responseText.substring(0, 500));
            addChatMessage('agent', 'Sorry, received an invalid response from the server.');
            return;
        }
        
        console.log('[Workbook Chat] Parsed response:', data);
        
        if (data.success && data.response) {
            addChatMessage('agent', data.response);
        } else {
            console.error('[Workbook Chat] Error in response:', data);
            addChatMessage('agent', 'Sorry, I encountered an error: ' + (data.message || 'No response'));
        }
    } catch (error) {
        console.error('[Workbook Chat] Error:', error);
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
