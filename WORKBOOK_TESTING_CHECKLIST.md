# Workbook Navigation System - Testing Checklist

**Date:** 2025-11-28  
**Version:** 1.0.0  
**Status:** ✅ Deployed to Production

---

## Pre-Testing Setup

- [ ] Apache/PHP service running
- [ ] Database accessible
- [ ] Agent service running (port 8080)
- [ ] Browser opened to student dashboard
- [ ] Student logged in with valid credentials

---

## 1. Dashboard Integration

### Test: Workbook Button Visibility
- [ ] Open `student_dashboard.html`
- [ ] Verify sidebar contains "📖 Workbook" link
- [ ] Link appears between "My Courses" and "AI Agents"
- [ ] Link has proper styling (matches other sidebar items)

### Test: Navigation to Workbook
- [ ] Click "📖 Workbook" link
- [ ] Verify redirects to `workbook.html`
- [ ] Page loads without errors
- [ ] No console errors in browser dev tools

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 2. Course Selection View

### Test: Initial Page Load
- [ ] Workbook opens to course selection grid by default
- [ ] Breadcrumb shows: "📚 Workbook"
- [ ] View mode selector shows only "Courses" button (active)
- [ ] No loading errors in console

### Test: Course Cards Display
- [ ] Algebra I card visible with:
  - [ ] Icon: 📐
  - [ ] Name: "Algebra I"
  - [ ] Metadata: "Mathematics • Grade 9"
  - [ ] Description text
  - [ ] No "Coming Soon" badge
- [ ] Geometry card visible with:
  - [ ] Icon: 📏
  - [ ] Name: "Geometry"
  - [ ] "🔒 Coming Soon" badge
  - [ ] Grayed out / disabled appearance
- [ ] Algebra II card visible with similar disabled styling

### Test: Course Selection
- [ ] Click Algebra I card
- [ ] Transitions to unit view
- [ ] Breadcrumb updates to: "📚 Workbook › Algebra I"
- [ ] View selector shows "Courses" (inactive) and "Units" (active)
- [ ] No errors in console

### Test: Disabled Courses
- [ ] Click Geometry card (disabled)
- [ ] No navigation occurs
- [ ] Cursor shows "not-allowed"
- [ ] Card doesn't highlight on hover

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 3. Unit Selection View

### Test: Unit Grid Display
- [ ] All 6 units displayed as cards
- [ ] Each unit shows:
  - [ ] Icon: 📖
  - [ ] Number and title (e.g., "Unit 1", "Foundations of Algebra")
  - [ ] Lesson count (e.g., "3 of 5 lessons available")
- [ ] Lesson counts accurate (check against course JSON)

### Test: Unit Selection
- [ ] Click "Unit 2" card
- [ ] Transitions to lesson view
- [ ] Breadcrumb updates: "📚 Workbook › Algebra I › Unit 2"
- [ ] View selector shows "Units" (inactive), "Lessons" (active)
- [ ] Unit title displayed above lesson grid

### Test: Breadcrumb Navigation from Units
- [ ] Click "📚 Workbook" in breadcrumb
- [ ] Returns to course selection view
- [ ] Breadcrumb resets to: "📚 Workbook"
- [ ] Click "Algebra I" (navigate to units again)
- [ ] Returns to unit view

### Test: View Mode Navigation
- [ ] Click "Courses" button in view selector
- [ ] Returns to course selection view
- [ ] Click Algebra I again → back to units

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 4. Lesson Selection View

### Test: Lesson Grid Display
- [ ] Lessons displayed as cards (1-5)
- [ ] Generated lessons show:
  - [ ] Icon: 📄
  - [ ] Number and title
  - [ ] Clickable (pointer cursor)
  - [ ] Hover effect (border highlight)
- [ ] Ungenerated lessons show:
  - [ ] Icon: 🔒
  - [ ] Number and title
  - [ ] "Coming Soon" badge
  - [ ] Not clickable (not-allowed cursor)
  - [ ] Grayed out appearance

### Test: Lesson Selection
- [ ] Click a generated lesson (e.g., Lesson 1)
- [ ] Lesson content loads
- [ ] Navigation panel hides
- [ ] Lesson panel displays
- [ ] Lesson title shows at top
- [ ] Course metadata shows: "Algebra I • Unit 2 • Lesson 1"

### Test: Breadcrumb Navigation from Lessons
- [ ] Before selecting lesson, click "Unit 2" in breadcrumb
- [ ] Should show lesson view (current view, not clickable)
- [ ] Click "Algebra I" in breadcrumb → returns to unit view
- [ ] Navigate back to lessons
- [ ] Click "📚 Workbook" → returns to courses

### Test: View Mode Navigation from Lessons
- [ ] Click "Units" button in view selector
- [ ] Returns to unit view for current course
- [ ] Navigate back to lessons
- [ ] Click "Courses" button → returns to course view

### Test: Ungenerated Lesson Behavior
- [ ] Click ungenerated lesson (with 🔒 icon)
- [ ] No action occurs
- [ ] No errors in console
- [ ] No navigation

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 5. Lesson Content Display

### Test: Content Sections
Verify all sections render correctly:
- [ ] **Lesson Title:** Displays at top
- [ ] **Course Metadata:** Shows course, unit, lesson info
- [ ] **Academic Integrity Alert:** Yellow banner at top
- [ ] **Learning Objectives:** Bulleted list
- [ ] **Explanation:** Main text content (formatted properly)
- [ ] **Guided Examples:** (if present)
  - [ ] Example boxes with problem/solution/explanation
  - [ ] Proper numbering (Example 1, Example 2, ...)
- [ ] **Practice Problems:** (if present)
  - [ ] Problem text
  - [ ] Collapsible answer (click "Show Answer")
  - [ ] Answer revealed on click
- [ ] **Quiz Questions:** (if present)
  - [ ] Question text
  - [ ] Multiple choice options (if applicable)
  - [ ] Collapsible answer with explanation
- [ ] **Vocabulary:** (if present)
  - [ ] Term in bold
  - [ ] Definition text

### Test: Scrolling
- [ ] Content area scrollable if exceeds viewport
- [ ] Smooth scrolling behavior
- [ ] No layout breakage

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 6. Media Tabs

### Test: Tab Switching
- [ ] Three tabs visible: "📹 Video Lesson", "📝 Notes", "✏️ Practice"
- [ ] Video tab active by default
- [ ] Click Notes tab:
  - [ ] Notes tab becomes active
  - [ ] Notes panel displays
  - [ ] Lesson summary shows
- [ ] Click Practice tab:
  - [ ] Practice tab becomes active
  - [ ] Practice panel displays
  - [ ] Quiz questions show
- [ ] Click Video tab again:
  - [ ] Returns to video panel

### Test: Video Tab Content
- [ ] Video placeholder displays
- [ ] Shows play icon: ▶️
- [ ] Shows lesson title
- [ ] Shows duration (if available)
- [ ] No actual video (expected, placeholder only)

### Test: Notes Tab Content
- [ ] Lesson summary displays
- [ ] Formatted properly
- [ ] If no summary, shows: "No summary available"

### Test: Practice Tab Content
- [ ] Quiz questions display (if available)
- [ ] Each question in box with number
- [ ] Options listed (if multiple choice)
- [ ] If no quiz, shows: "No practice questions available"

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 7. Lesson Navigation Buttons

### Test: Button Visibility
- [ ] Three buttons at bottom of lesson panel:
  - [ ] "← Previous Lesson" (left)
  - [ ] "📚 Back to Lessons" (center)
  - [ ] "Next Lesson →" (right)

### Test: Back to Lessons Button
- [ ] Click "📚 Back to Lessons"
- [ ] Returns to lesson grid
- [ ] Lesson panel hides
- [ ] Navigation panel shows
- [ ] Breadcrumb still shows: "... › Unit X"

### Test: Previous Lesson Button
Case 1: First lesson
- [ ] On Lesson 1, Previous button is disabled
- [ ] Button grayed out, not clickable

Case 2: Middle lesson
- [ ] On Lesson 2 (or higher), Previous button enabled
- [ ] Click Previous
- [ ] Loads previous lesson (e.g., Lesson 1)
- [ ] Content updates
- [ ] Button states update

Case 3: Previous lesson ungenerated
- [ ] If Lesson 1 generated, Lesson 2 ungenerated, on Lesson 3
- [ ] Previous button should be disabled (can't skip to Lesson 1)
- [ ] OR: Should load Lesson 1 (skipping Lesson 2)
- [ ] Verify expected behavior matches implementation

### Test: Next Lesson Button
Case 1: Last lesson
- [ ] On last lesson of unit, Next button disabled
- [ ] Button grayed out, not clickable

Case 2: Middle lesson
- [ ] On Lesson 1 (or earlier), Next button enabled
- [ ] Click Next
- [ ] Loads next lesson (e.g., Lesson 2)
- [ ] Content updates
- [ ] Button states update

Case 3: Next lesson ungenerated
- [ ] If Lesson 2 ungenerated, on Lesson 1
- [ ] Next button should be disabled
- [ ] OR: Should skip to Lesson 3 if generated
- [ ] Verify expected behavior matches implementation

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 8. URL Parameters

### Test: Manual URL Entry
- [ ] Open new tab
- [ ] Enter: `http://localhost/basic_educational/workbook.html?course=algebra_1&unit=2&lesson=1`
- [ ] Page loads directly to Lesson 1 of Unit 2
- [ ] Lesson content displays
- [ ] Breadcrumb shows: "... › Algebra I › Unit 2"

### Test: URL Update on Navigation
- [ ] Start at course selection
- [ ] URL: `workbook.html` (no params)
- [ ] Navigate: Algebra I → Unit 2 → Lesson 3
- [ ] URL updates to: `workbook.html?course=algebra_1&unit=2&lesson=3`
- [ ] Click Previous Lesson
- [ ] URL updates to: `workbook.html?course=algebra_1&unit=2&lesson=2`

### Test: Browser Back/Forward
- [ ] Navigate through: Courses → Algebra I → Unit 2 → Lesson 1
- [ ] Click browser Back button
- [ ] Returns to lesson grid
- [ ] Click Back again → unit grid
- [ ] Click Back again → course grid
- [ ] Click Forward → unit grid
- [ ] Continue Forward → returns to lesson

### Test: Bookmarking
- [ ] Navigate to specific lesson
- [ ] Bookmark page (Ctrl+D or Cmd+D)
- [ ] Close tab
- [ ] Open bookmark
- [ ] Loads directly to bookmarked lesson

### Test: Invalid URL Parameters
- [ ] Enter: `workbook.html?course=invalid&unit=99&lesson=99`
- [ ] Should show error message
- [ ] "Back to Courses" button available
- [ ] Clicking returns to course view

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 9. AI Chat Integration

### Test: Chat Panel Visibility
- [ ] Chat panel visible on left side (1/3 width)
- [ ] Professor Hawkeinstein header shows
- [ ] Welcome message displays
- [ ] Chat input field at bottom
- [ ] Send button present

### Test: Sending Messages (No Lesson)
- [ ] At course selection view, type: "Hello"
- [ ] Click Send (or press Enter)
- [ ] Message appears in chat as user message
- [ ] Agent response appears after ~2-5 seconds
- [ ] Response relevant to query

### Test: Sending Messages (With Lesson)
- [ ] Navigate to specific lesson (e.g., Algebra I, Unit 2, Lesson 1)
- [ ] System message appears: "Loaded: {Lesson Title} (Unit X, Lesson Y)"
- [ ] Type question: "Can you explain this concept?"
- [ ] Click Send
- [ ] User message appears
- [ ] Agent response includes lesson context
- [ ] Response relevant to current lesson

### Test: Chat Scrolling
- [ ] Send multiple messages (5+)
- [ ] Chat auto-scrolls to latest message
- [ ] Can manually scroll up to see history
- [ ] New messages keep auto-scrolling to bottom

### Test: Chat Persistence
- [ ] Send message on Lesson 1
- [ ] Navigate to Lesson 2
- [ ] Chat history still visible
- [ ] Send another message
- [ ] Both messages in history

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 10. Error Handling

### Test: Missing Course File
- [ ] Modify `scanAvailableCourses()` to include course with non-existent file
- [ ] Click that course
- [ ] Error message displays: "Failed to load course units"
- [ ] Shows: "Course file not found: course_xyz.json"
- [ ] "Back to Courses" button present
- [ ] Clicking returns to course view
- [ ] No console errors

### Test: Malformed Course JSON
- [ ] Create course JSON with invalid syntax (missing bracket, etc.)
- [ ] Click that course
- [ ] Error message displays
- [ ] User can return to courses
- [ ] No infinite loops or crashes

### Test: Missing Unit
- [ ] Enter URL: `workbook.html?course=algebra_1&unit=99&lesson=1`
- [ ] Error displays: "Unit 99 not found in course"
- [ ] User can navigate away

### Test: Missing Lesson
- [ ] Enter URL: `workbook.html?course=algebra_1&unit=2&lesson=99`
- [ ] Error displays: "Lesson 99 not found in Unit 2"
- [ ] User can navigate away

### Test: Ungenerated Lesson Direct Link
- [ ] Find ungenerated lesson in course JSON (status: "outline")
- [ ] Enter URL pointing to that lesson
- [ ] Error displays: "Lesson content not yet generated"
- [ ] User can navigate away

### Test: Network Error (Agent Chat)
- [ ] Stop agent service: `pkill agent_service`
- [ ] Send chat message
- [ ] Error message displays in chat: "Could not connect to server"
- [ ] User can continue using navigation

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 11. Responsive Design

### Test: Desktop (1920x1080)
- [ ] Two-column layout displays correctly
- [ ] Chat panel: 1/3 width
- [ ] Content panel: 2/3 width
- [ ] Navigation grids display in multiple columns
- [ ] All text readable

### Test: Laptop (1366x768)
- [ ] Layout still two-column
- [ ] Proportions maintained
- [ ] Navigation grids adjust to smaller width
- [ ] No horizontal scrolling

### Test: Tablet (768x1024)
- [ ] Layout switches to single column (if responsive CSS present)
- [ ] OR: Two-column maintained with smaller proportions
- [ ] All functionality accessible
- [ ] Touch interactions work

### Test: Mobile (375x667)
- [ ] Layout switches to single column
- [ ] Chat panel hidden or collapsible
- [ ] Navigation cards stack vertically
- [ ] All buttons accessible
- [ ] Text readable without zoom

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 12. Browser Compatibility

### Test: Firefox
- [ ] All navigation features work
- [ ] Media tabs switch correctly
- [ ] Chat sends/receives messages
- [ ] URL parameters work
- [ ] No console errors

### Test: Chrome
- [ ] All navigation features work
- [ ] Rendering identical to Firefox
- [ ] No console warnings
- [ ] Performance acceptable

### Test: Safari
- [ ] All navigation features work
- [ ] CSS styles render correctly
- [ ] JavaScript functions properly
- [ ] No compatibility issues

### Test: Edge
- [ ] All navigation features work
- [ ] Consistent with Chrome behavior
- [ ] No rendering issues

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 13. Performance

### Test: Load Time
- [ ] Initial page load: < 1 second
- [ ] Course view load: < 100ms
- [ ] Unit view load: < 300ms
- [ ] Lesson view load: < 100ms
- [ ] Lesson content load: < 500ms
- [ ] Total (course → lesson): < 1 second

### Test: Navigation Speed
- [ ] Click course card → units appear instantly
- [ ] Click unit card → lessons appear instantly
- [ ] Click lesson card → content appears in < 500ms
- [ ] Breadcrumb clicks respond instantly

### Test: Chat Response Time
- [ ] Message sent → response in 2-10 seconds (depending on LLM)
- [ ] No UI freezing during response wait
- [ ] Loading indicator would be nice (future enhancement)

### Test: Memory Usage
- [ ] Open workbook, navigate for 5 minutes
- [ ] Check browser task manager
- [ ] Memory usage reasonable (< 200MB)
- [ ] No memory leaks observed

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 14. Accessibility

### Test: Keyboard Navigation
- [ ] Tab key moves through clickable elements
- [ ] Enter/Space activates buttons and links
- [ ] Can navigate entire site without mouse
- [ ] Focus indicators visible

### Test: Screen Reader (Optional)
- [ ] Course cards announced properly
- [ ] Lesson content readable
- [ ] Button labels clear
- [ ] ARIA labels present (if implemented)

### Test: Contrast
- [ ] Text readable against backgrounds
- [ ] Meets WCAG AA standards (4.5:1 ratio)
- [ ] Disabled elements clearly indicated

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 15. Edge Cases

### Test: Empty Course
- [ ] Create course JSON with 0 units
- [ ] Select course
- [ ] Error handled gracefully
- [ ] OR: "No units available" message

### Test: Empty Unit
- [ ] Create unit with 0 lessons
- [ ] Select unit
- [ ] "No lessons available" message
- [ ] Can navigate back

### Test: Rapid Clicking
- [ ] Rapidly click course/unit/lesson cards
- [ ] No duplicate loads
- [ ] No race conditions
- [ ] Last click wins

### Test: Long Lesson Content
- [ ] Load lesson with very long explanation (10,000+ words)
- [ ] Content renders without layout break
- [ ] Scrolling smooth
- [ ] No performance issues

### Test: Special Characters
- [ ] Lesson with special chars in title: "Algebra: 2x² + 3y³ = 5"
- [ ] Renders correctly
- [ ] No encoding issues
- [ ] No XSS vulnerabilities

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## 16. Integration Testing

### Test: Course Generation Integration
- [ ] Generate new course using `generate_full_course.php`
- [ ] Add course to `scanAvailableCourses()`
- [ ] Verify course appears in workbook
- [ ] All units and lessons accessible
- [ ] Content displays correctly

### Test: Assessment Integration
- [ ] Generate assessment for course
- [ ] Verify assessment accessible (future feature)
- [ ] Links to relevant lessons work

### Test: Student Progress Integration (Future)
- [ ] Mark lesson as completed
- [ ] Progress indicator shows
- [ ] Completed lessons marked in grid

**Status:** ⬜ Not Tested | ⚠️ Issues Found | ✅ Passed

---

## Post-Testing Actions

### If All Tests Pass (✅)
- [x] Mark workbook navigation as production-ready
- [x] Update status in project documentation
- [x] Notify stakeholders of completion
- [ ] Schedule demo for users
- [ ] Gather initial user feedback

### If Issues Found (⚠️)
- [ ] Document issues in GitHub Issues
- [ ] Prioritize by severity (Critical, High, Medium, Low)
- [ ] Fix critical issues immediately
- [ ] Schedule fixes for non-critical issues
- [ ] Re-test after fixes

---

## Test Results Summary

**Total Test Categories:** 16  
**Completed:** _____ / 16  
**Passed:** _____ / 16  
**Issues Found:** _____ 

**Critical Issues:** _____ (blocks functionality)  
**High Priority Issues:** _____ (impacts UX significantly)  
**Medium Priority Issues:** _____ (minor UX impact)  
**Low Priority Issues:** _____ (cosmetic, future enhancement)

---

## Sign-Off

**Tested By:** ________________  
**Date:** ________________  
**Overall Status:** ⬜ Pass | ⬜ Pass with Minor Issues | ⬜ Fail  
**Ready for Production:** ⬜ Yes | ⬜ No | ⬜ With Caveats  

**Notes:**
_____________________________________________________________
_____________________________________________________________
_____________________________________________________________

---

**Version:** 1.0.0  
**Last Updated:** 2025-11-28  
**Related Docs:** WORKBOOK_NAVIGATION_GUIDE.md, WORKBOOK_REVAMP_SUMMARY.md
