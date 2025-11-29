# Workbook Navigation System Guide

## Overview

The workbook navigation system provides a **modular, hierarchical Course → Unit → Lesson navigation** structure for students to browse and access course content. The system gracefully handles missing or ungenerated lessons and provides seamless integration with the AI chat assistant.

**Architecture:** Three-level hierarchy with state management and URL parameter support

**Files:**
- `workbook.html` - Main HTML structure (800+ lines)
- `workbook_app.js` - JavaScript application logic (600+ lines)
- `student_dashboard.html` - Entry point with Workbook button in sidebar

**Key Features:**
- ✅ Modular course/unit/lesson selection
- ✅ Breadcrumb navigation with clickable history
- ✅ Previous/Next lesson buttons
- ✅ URL parameters for deep linking
- ✅ Graceful handling of ungenerated lessons
- ✅ AI chat with lesson context
- ✅ Three media tabs (Video, Notes, Practice)
- ✅ Complete lesson renderer

---

## Navigation Hierarchy

### Level 1: Course Selection

**View:** Grid of available courses

**Data Source:** `scanAvailableCourses()` function

**Course Object Structure:**
```javascript
{
  id: 'algebra_1',              // Course identifier
  name: 'Algebra I',            // Display name
  subject: 'Mathematics',       // Subject area
  level: 'Grade 9',             // Grade level
  icon: '📐',                   // Emoji icon
  description: '...',           // Course description
  disabled: false               // Whether course is available
}
```

**UI Elements:**
- Course cards with icon, name, subject, level, description
- "Coming Soon" badge for disabled courses
- Click to select course and navigate to units

**Code Location:** `showCourseView()` in `workbook_app.js`

---

### Level 2: Unit Selection

**View:** Grid of 6 units (typical course structure)

**Data Source:** Course JSON file (`api/course/courses/course_{courseId}.json`)

**Unit Display:**
- Unit number and title
- Lesson count: "X of Y lessons available"
- Counts lessons with actual content (not outline status)

**UI Elements:**
- Unit cards with number, title, lesson count
- Click to select unit and navigate to lessons

**Code Location:** `showUnitView(courseId)` in `workbook_app.js`

**Data Loading:**
```javascript
const courseData = await loadCourse(courseId);
const units = courseData.units.map(unit => {
  const generatedCount = unit.lessons.filter(l => 
    l.status !== 'outline' && l.explanation && l.explanation !== ''
  ).length;
  return { ...unit, totalLessons, generatedLessons: generatedCount };
});
```

---

### Level 3: Lesson Selection

**View:** Grid of lessons within selected unit

**Data Source:** Unit data from course JSON

**Lesson Availability Check:**
```javascript
const isAvailable = 
  lesson.status !== 'outline' && 
  lesson.explanation && 
  lesson.explanation !== '';
```

**UI Elements:**
- Lesson cards with number and title
- 📄 icon for available lessons
- 🔒 icon + "Coming Soon" for unavailable lessons
- Click to load lesson content

**Code Location:** `showLessonView(unitNumber)` in `workbook_app.js`

---

### Level 4: Lesson Content

**View:** Full lesson with reading material, examples, practice, quiz

**Data Source:** `loadLesson(courseId, unitNumber, lessonNumber)`

**Lesson Renderer:** Displays all lesson schema fields:

1. **Learning Objectives** - Bulleted list
2. **Explanation** - Main content text
3. **Guided Examples** - Problem/solution pairs
4. **Practice Problems** - With collapsible answers
5. **Quiz Questions** - With options and explanations
6. **Vocabulary** - Terms and definitions
7. **Summary** - Key takeaways

**Media Tabs:**
- **Video Tab:** Placeholder for video content (duration, title)
- **Notes Tab:** Lesson summary
- **Practice Tab:** Quiz questions for self-assessment

**Navigation Buttons:**
- ← Previous Lesson (disabled if none available)
- 📚 Back to Lessons (returns to lesson grid)
- Next Lesson → (disabled if none available)

**Code Location:** `renderLesson(lesson)` in `workbook_app.js`

---

## State Management

### AppState Object

**Purpose:** Centralized state tracking for navigation

**Structure:**
```javascript
const AppState = {
  currentView: 'courses',        // Current navigation level
  selectedCourse: null,           // Selected course ID
  selectedUnit: null,             // Selected unit number
  selectedLesson: null,           // Selected lesson number
  courses: [],                    // Available courses list
  currentCourseData: null,        // Loaded course JSON data
  studentId: null,                // Current student ID
  chatHistory: []                 // Chat message history
};
```

**Views:**
- `'courses'` - Course selection grid
- `'units'` - Unit selection grid
- `'lessons'` - Lesson selection grid
- `'lesson-content'` - Full lesson display

**State Updates:**
- `selectCourse(courseId)` → Sets `selectedCourse`, loads course data, shows units
- `selectUnit(unitNumber)` → Sets `selectedUnit`, shows lessons
- `selectLesson(lessonNumber)` → Sets `selectedLesson`, loads and renders lesson

---

## Data Loading Functions

### `scanAvailableCourses()`

**Purpose:** Returns list of available courses

**Current Implementation:** Hardcoded array

**Expansion Path:** Can be enhanced to dynamically scan `api/course/courses/` directory

**Returns:** Array of course objects

---

### `loadCourse(courseId)`

**Purpose:** Load complete course data from JSON file

**File Location:** `api/course/courses/course_{courseId}.json`

**Process:**
1. Fetch course JSON file
2. Parse response
3. Store in `AppState.currentCourseData`
4. Return course data object

**Error Handling:** Throws error if file not found

**Example:**
```javascript
const courseData = await loadCourse('algebra_1');
// courseData.units[0].lessons[0]...
```

---

### `loadLesson(courseId, unitNumber, lessonNumber)`

**Purpose:** Load specific lesson with validation and metadata enrichment

**Process:**
1. Load course data (if not already loaded)
2. Find unit by `unitNumber`
3. Find lesson by `lessonNumber`
4. Validate lesson has content (not outline status)
5. Enrich with course metadata
6. Return enriched lesson object

**Validation Checks:**
- Unit exists in course
- Lesson exists in unit
- Lesson status is not 'outline'
- Lesson has explanation content

**Enrichment:** Adds metadata to lesson object:
```javascript
{
  ...lesson,                    // Original lesson fields
  courseName: '...',           // From course data
  subject: '...',              // From course data
  level: '...',                // From course data
  unitTitle: '...',            // From unit data
  unitNumber: X,               // Unit number
  courseId: '...'              // Course identifier
}
```

**Error Handling:**
- Missing unit: Throws `Unit X not found in course`
- Missing lesson: Throws `Lesson X not found in Unit Y`
- Ungenerated lesson: Throws `Lesson content not yet generated`

**Example:**
```javascript
try {
  const lesson = await loadLesson('algebra_1', 2, 3);
  console.log(lesson.lessonTitle);  // "Solving Linear Equations"
} catch (error) {
  console.error('Lesson unavailable:', error.message);
}
```

---

## Navigation Features

### Breadcrumb Trail

**Purpose:** Show current location in hierarchy with clickable history

**Location:** Top of navigation panel

**Format:** `📚 Workbook › Course › Unit X › Lesson Y`

**Functionality:**
- Each level is clickable (except current level)
- Clicking a level navigates back to that view
- Dynamically updates as user navigates

**Implementation:**
```javascript
updateBreadcrumb([
  { label: '📚 Workbook', action: () => showCourseView() },
  { label: course.name, action: () => showUnitView(courseId) },
  { label: `Unit ${unitNumber}`, action: null }  // Current level
]);
```

---

### Previous/Next Lesson Navigation

**Purpose:** Quick navigation between sequential lessons

**Location:** Bottom of lesson content panel

**Button States:**
- **Enabled:** Next lesson exists and has content
- **Disabled:** No next lesson OR next lesson not generated yet

**Smart Navigation:**
```javascript
// Find next available lesson
let nextLesson = null;
if (currentLesson < unit.lessons.length) {
  const next = unit.lessons.find(l => l.lessonNumber === currentLesson + 1);
  if (next && next.status !== 'outline' && next.explanation) {
    nextLesson = currentLesson + 1;
  }
}
```

**UI Behavior:**
- Disabled buttons are grayed out
- Clicking loads adjacent lesson
- Preserves breadcrumb context

---

### URL Parameters

**Purpose:** Enable deep linking and bookmarking

**Format:** `workbook.html?course=algebra_1&unit=2&lesson=3`

**Parameters:**
- `course` - Course ID (e.g., `algebra_1`)
- `unit` - Unit number (1-6)
- `lesson` - Lesson number (1-5+)

**Initialization:**
```javascript
const urlParams = new URLSearchParams(window.location.search);
const courseId = urlParams.get('course');
const unitNumber = urlParams.get('unit');
const lessonNumber = urlParams.get('lesson');

if (courseId && unitNumber && lessonNumber) {
  await selectCourse(courseId);
  await selectUnit(parseInt(unitNumber));
  await selectLesson(parseInt(lessonNumber));
}
```

**URL Update:** When lesson selected, URL is updated automatically:
```javascript
const url = new URL(window.location);
url.searchParams.set('course', AppState.selectedCourse);
url.searchParams.set('unit', AppState.selectedUnit);
url.searchParams.set('lesson', lessonNumber);
window.history.pushState({}, '', url);
```

**Benefits:**
- Bookmarkable lesson URLs
- Share specific lessons with students
- Browser back/forward button support
- Persistent navigation state

---

## Error Handling

### Missing Course Files

**Scenario:** Course JSON file doesn't exist

**Error Display:**
```
⚠️ Failed to load course units
Course file not found: course_geometry.json
[← Back to Courses button]
```

**Code:**
```javascript
try {
  const response = await fetch(`api/course/courses/course_${courseId}.json`);
  if (!response.ok) throw new Error(`Course file not found`);
} catch (error) {
  showError('Failed to load course units', error.message);
}
```

---

### Ungenerated Lessons

**Scenario:** Lesson exists in course JSON but has no content

**Detection:**
```javascript
lesson.status === 'outline' || !lesson.explanation || lesson.explanation === ''
```

**UI Display:**
- 🔒 icon on lesson card
- "Coming Soon" label
- Card is disabled (not clickable)

**Navigation Impact:**
- Previous/Next buttons skip ungenerated lessons
- Lesson counts show "X of Y lessons available"

---

### Invalid Unit/Lesson Numbers

**Scenario:** URL parameters reference non-existent unit/lesson

**Error Handling:**
```javascript
const unit = courseData.units.find(u => u.unitNumber === unitNumber);
if (!unit) {
  throw new Error(`Unit ${unitNumber} not found in course`);
}
```

**Fallback:** Error message with "Back to Courses" button

---

## Chat Integration

### Lesson Context

**Purpose:** Provide AI assistant with current lesson information

**Implementation:**
```javascript
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
fetch('api/agent/chat.php', {
  method: 'POST',
  body: JSON.stringify({
    userId: AppState.studentId,
    agentId: 1,  // Professor Hawkeinstein
    message: context ? `${context}\n\nStudent question: ${message}` : message
  })
});
```

**Chat Features:**
- Shows current lesson in chat when loaded
- Includes lesson context with every question
- Preserves chat history in AppState
- Auto-scrolls to latest message

**Message Types:**
- `'agent'` - Professor Hawkeinstein responses
- `'user'` - Student questions
- `'system'` - Navigation notifications

---

## Adding New Courses

### Step 1: Create Course JSON File

**Location:** `api/course/courses/`

**Filename:** `course_{courseId}.json`

**Structure:**
```json
{
  "courseName": "Geometry",
  "subject": "Mathematics",
  "level": "Grade 10",
  "units": [
    {
      "unitNumber": 1,
      "unitTitle": "Points, Lines, and Planes",
      "lessons": [
        {
          "lessonNumber": 1,
          "lessonTitle": "Basic Geometric Concepts",
          "status": "complete",
          "objectives": [...],
          "explanation": "...",
          "guidedExamples": [...],
          "practiceProblems": [...],
          "quiz": [...],
          "vocabulary": [...],
          "summary": "...",
          "duration": "45 minutes",
          "video": ""
        }
      ]
    }
  ]
}
```

---

### Step 2: Add Course to scanAvailableCourses()

**File:** `workbook_app.js`

**Function:** `scanAvailableCourses()`

**Add course object:**
```javascript
{
  id: 'geometry',               // Must match filename (course_geometry.json)
  name: 'Geometry',
  subject: 'Mathematics',
  level: 'Grade 10',
  icon: '📏',
  description: 'Shapes, angles, proofs, and spatial reasoning',
  disabled: false               // Set true if not ready
}
```

---

### Step 3: Test Course Navigation

**Test Checklist:**
1. ✅ Course appears in course grid
2. ✅ Clicking course loads units
3. ✅ Unit cards show correct lesson counts
4. ✅ Clicking unit loads lessons
5. ✅ Generated lessons clickable
6. ✅ Ungenerated lessons show as "Coming Soon"
7. ✅ Clicking lesson loads content
8. ✅ All lesson fields render correctly
9. ✅ Previous/Next buttons work
10. ✅ URL parameters update
11. ✅ Chat context includes lesson info

---

## Lesson Schema Requirements

### Required Fields (9)

All lessons MUST include these fields for proper rendering:

1. **lessonNumber** (integer) - Lesson sequence number (1-5+)
2. **lessonTitle** (string) - Descriptive title
3. **objectives** (array of strings) - Learning objectives
4. **explanation** (string) - Main lesson content
5. **practiceProblems** (array of objects) - Practice exercises
6. **quiz** (array of objects) - Assessment questions
7. **summary** (string) - Key takeaways
8. **vocabulary** (array of objects) - Terms and definitions
9. **duration** (string) - Estimated time (e.g., "45 minutes")

### Optional Fields

- **guidedExamples** (array of objects) - Step-by-step examples
- **video** (string) - Video URL or identifier
- **status** (string) - "complete" or "outline"

### Object Structures

**Practice Problem:**
```json
{
  "problem": "Solve: 2x + 3 = 7",
  "answer": "x = 2",
  "explanation": "Subtract 3, then divide by 2"
}
```

**Quiz Question:**
```json
{
  "question": "What is the slope of y = 2x + 3?",
  "options": ["1", "2", "3", "0.5"],
  "answer": "2",
  "explanation": "Slope is the coefficient of x"
}
```

**Vocabulary:**
```json
{
  "term": "Slope",
  "definition": "The rate of change of a linear function"
}
```

**Guided Example:**
```json
{
  "problem": "Factor: x² + 5x + 6",
  "solution": "(x + 2)(x + 3)",
  "explanation": "Find two numbers that multiply to 6 and add to 5"
}
```

---

## Troubleshooting

### Issue: Course not appearing in grid

**Causes:**
1. Not added to `scanAvailableCourses()` array
2. Course object has `disabled: true`

**Fix:**
```javascript
// In workbook_app.js, scanAvailableCourses()
{
  id: 'my_course',
  name: 'My Course',
  subject: 'Mathematics',
  level: 'Grade 9',
  icon: '📚',
  description: '...',
  disabled: false  // Make sure this is false
}
```

---

### Issue: Units not loading

**Causes:**
1. Course JSON file missing
2. Incorrect filename (should be `course_{id}.json`)
3. Malformed JSON

**Fix:**
1. Verify file exists: `ls api/course/courses/course_algebra_1.json`
2. Validate JSON: `jsonlint course_algebra_1.json`
3. Check browser console for fetch errors

---

### Issue: Lessons show as "Coming Soon"

**Causes:**
1. Lesson has `status: "outline"`
2. Lesson missing `explanation` field
3. Lesson `explanation` is empty string

**Fix:**
```json
{
  "lessonNumber": 1,
  "lessonTitle": "My Lesson",
  "status": "complete",          // NOT "outline"
  "explanation": "Actual content here...",  // NOT empty
  // ... other required fields
}
```

---

### Issue: Previous/Next buttons not working

**Causes:**
1. Adjacent lesson not generated
2. JavaScript error in `updateLessonNavigation()`

**Debug:**
```javascript
console.log('Current lesson:', AppState.selectedLesson);
const unit = AppState.currentCourseData.units.find(u => u.unitNumber === AppState.selectedUnit);
console.log('Unit lessons:', unit.lessons);
```

**Expected Behavior:**
- Buttons disabled if adjacent lesson unavailable
- Buttons enabled only for generated lessons

---

### Issue: URL parameters not working

**Causes:**
1. Invalid course ID
2. Unit/lesson numbers out of range
3. JavaScript error during initialization

**Debug:**
```javascript
// In browser console
const urlParams = new URLSearchParams(window.location.search);
console.log('Course:', urlParams.get('course'));
console.log('Unit:', urlParams.get('unit'));
console.log('Lesson:', urlParams.get('lesson'));
```

**Valid URL Examples:**
- `workbook.html?course=algebra_1&unit=1&lesson=1`
- `workbook.html?course=algebra_1&unit=2&lesson=3`

---

### Issue: Chat not responding

**Causes:**
1. Agent service not running (port 8080)
2. Student ID not set
3. Network error

**Debug:**
```bash
# Check agent service
curl http://localhost:8080/health

# Check student ID
console.log('Student ID:', AppState.studentId);
```

**Fix:**
```bash
# Start agent service
cd /home/steve/Professor_Hawkeinstein
./start_services.sh
```

---

## Performance Optimization

### Caching Course Data

**Current:** Course data loaded once per course selection

**Optimization:** Cache loaded courses in AppState
```javascript
const courseCache = {};

async function loadCourse(courseId) {
  if (courseCache[courseId]) {
    return courseCache[courseId];
  }
  const data = await fetch(...);
  courseCache[courseId] = data;
  return data;
}
```

---

### Lazy Loading Lessons

**Current:** Full course JSON loaded at unit selection

**Optimization:** Load individual lessons on-demand
```javascript
// Instead of: course_algebra_1.json (entire course)
// Use: course_algebra_1/unit_2/lesson_3.json (individual lesson)
```

---

### Image Preloading

**Future Enhancement:** Preload lesson images/diagrams

```javascript
function preloadLessonImages(lesson) {
  const images = extractImages(lesson.explanation);
  images.forEach(src => {
    const img = new Image();
    img.src = src;
  });
}
```

---

## Future Enhancements

### Dynamic Course Discovery

**Goal:** Automatically scan `api/course/courses/` directory

**Implementation:**
```php
// api/course/list_courses.php
$courseDir = __DIR__ . '/courses/';
$files = glob($courseDir . 'course_*.json');
$courses = array_map(function($file) {
  $data = json_decode(file_get_contents($file), true);
  return [
    'id' => basename($file, '.json'),
    'name' => $data['courseName'],
    'subject' => $data['subject'],
    'level' => $data['level']
  ];
}, $files);
echo json_encode($courses);
```

---

### Lesson Search

**Goal:** Search lessons by keyword or topic

**UI:** Search bar in navigation panel

**Implementation:**
```javascript
function searchLessons(query) {
  const results = [];
  AppState.currentCourseData.units.forEach(unit => {
    unit.lessons.forEach(lesson => {
      if (lesson.lessonTitle.toLowerCase().includes(query.toLowerCase())) {
        results.push({ unit, lesson });
      }
    });
  });
  return results;
}
```

---

### Lesson Bookmarks

**Goal:** Allow students to bookmark favorite lessons

**Storage:** `localStorage` or database table

**Implementation:**
```javascript
function bookmarkLesson(courseId, unitNumber, lessonNumber) {
  const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
  bookmarks.push({ courseId, unitNumber, lessonNumber, timestamp: Date.now() });
  localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
}
```

---

### Progress Tracking

**Goal:** Track which lessons student has completed

**Storage:** Database table `lesson_progress`

**Schema:**
```sql
CREATE TABLE lesson_progress (
  student_id INT,
  course_id VARCHAR(50),
  unit_number INT,
  lesson_number INT,
  completed BOOLEAN,
  score INT,
  time_spent INT,
  completed_at TIMESTAMP,
  PRIMARY KEY (student_id, course_id, unit_number, lesson_number)
);
```

---

## Maintenance

### Adding Lessons to Existing Course

1. Edit course JSON file: `api/course/courses/course_{id}.json`
2. Add lesson object to unit's `lessons` array
3. Ensure all 9 required fields present
4. Set `status: "complete"`
5. Refresh workbook page
6. Verify lesson appears in lesson grid
7. Test lesson rendering

---

### Removing Courses

1. Remove course object from `scanAvailableCourses()`
2. Optionally delete course JSON file
3. Or set `disabled: true` to keep course but hide it

---

### Updating Lesson Content

1. Edit lesson object in course JSON file
2. Refresh workbook page (or clear cache)
3. Verify changes render correctly

---

## API Reference

### Frontend Functions

**Navigation:**
- `showCourseView()` - Display course selection grid
- `showUnitView(courseId)` - Display unit selection grid
- `showLessonView(unitNumber)` - Display lesson selection grid
- `selectCourse(courseId)` - Navigate to course units
- `selectUnit(unitNumber)` - Navigate to unit lessons
- `selectLesson(lessonNumber)` - Load and render lesson

**Data Loading:**
- `scanAvailableCourses()` - Get list of available courses
- `loadCourse(courseId)` - Load course JSON data
- `loadLesson(courseId, unitNumber, lessonNumber)` - Load specific lesson

**Rendering:**
- `renderLesson(lesson)` - Render lesson content
- `updateBreadcrumb(items)` - Update breadcrumb trail
- `updateLessonNavigation()` - Update Prev/Next buttons

**Utilities:**
- `showError(title, message)` - Display error message
- `addChatMessage(type, message)` - Add message to chat
- `sendChatMessage()` - Send message to AI agent

---

### Backend Endpoints

**Course Data:**
- `GET api/course/courses/course_{courseId}.json` - Course data

**Chat:**
- `POST api/agent/chat.php` - Send message to AI agent
  ```json
  {
    "userId": 1,
    "agentId": 1,
    "message": "Currently studying: Lesson Title\n\nStudent question: ..."
  }
  ```

---

## Related Documentation

- **ASSESSMENT_GENERATION_API.md** - Assessment generation system
- **COURSE_GENERATION_API.md** - Course content generation
- **ADVISOR_INSTANCE_API.md** - Student advisor system
- **PROJECT_OVERVIEW.md** - Overall architecture

---

## Support

For issues or questions:
1. Check browser console for JavaScript errors
2. Verify course JSON files are valid
3. Check agent service is running: `curl http://localhost:8080/health`
4. Review `/tmp/agent_service_full.log` for backend errors
5. Consult troubleshooting section above

---

**Last Updated:** 2025-11-28  
**Version:** 1.0.0  
**Commit:** 7421d64
