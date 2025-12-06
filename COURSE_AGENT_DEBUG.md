# Course Agent Debug Log

## Purpose
Track all work done on the Course Creation Agent implementation. Each session should add entries here.

---

## Session: December 1, 2025

### What We Did

#### 1. Rebuilt Course Management UI (admin_courses.html)
- **Problem:** Old interface didn't match new wizard workflow
- **Solution:** Created new 3-card layout:
  - Card 1: Create New Course → links to `admin_course_create_step1.html`
  - Card 2: Continue Drafts → lists from `course_drafts` table
  - Card 3: Published Courses → lists from `courses` table
- **Files Changed:**
  - `admin_courses.html` - Complete rewrite
  - `api/admin/list_course_drafts.php` - New endpoint
  - `api/admin/list_published_courses.php` - New endpoint

#### 2. Fixed Step 1: Course Metadata Form (admin_course_create_step1.html)
- **Problem:** Next button invisible (CSS variables not defined), form submission failing
- **Root Cause 1:** Button used `var(--primary)` but CSS variables weren't loaded
- **Root Cause 2:** `getAdminId()` function missing from deployed `config/database.php`
- **Solution:** 
  - Changed button CSS to use explicit hex colors (`#4f46e5`)
  - Copied updated `config/database.php` with `getAdminId()` function to web directory
- **Files Changed:**
  - `admin_course_create_step1.html` - Complete rewrite with proper structure
  - `config/database.php` - Added `getAdminId()` helper function

#### 3. Created Step 2: Standards Review (admin_content_review.html)
- **Rebuilt as wizard Step 2**
- **Features:**
  - Wizard step indicator (1-2-3-4)
  - Load draft info by draftId
  - Fetch Standards from CSP button
  - Load Pending Scraped Standards button
  - Standards selection with checkboxes
  - Approve & Continue button
- **Files Changed:**
  - `admin_content_review.html` - Complete rewrite
  - `api/admin/get_course_draft.php` - New endpoint
  - `api/admin/get_approved_standards.php` - New endpoint

#### 4. Created Step 3: Outline Generation (admin_course_outline.html)
- **New page created**
- **Features:**
  - Wizard step indicator
  - Display approved standards summary
  - Generate Outline button (calls LLM)
  - Outline preview (units + lessons)
  - Publish Course button
- **Files Changed:**
  - `admin_course_outline.html` - New file
  - `api/admin/get_draft_outline.php` - New endpoint
  - `api/admin/publish_course.php` - Updated to match `courses` table schema

#### 5. Created Database Migration
- **File:** `migrations/004_course_drafts.sql`
- **Tables Created:**
  - `course_drafts` - Draft course metadata
  - `approved_standards` - Standards approved for draft
  - `course_outlines` - Generated outlines

#### 6. Created Implementation Plan
- **File:** `IMPLEMENT_COURSE_AGENT.md`
- **Contents:** Full 7-step workflow with API specs, database schema, agent pseudo-code

---

### Current Bugs/Issues

| Issue | Status | Notes |
|-------|--------|-------|
| Step 1 button invisible | ✅ Fixed | Changed to hex colors |
| `getAdminId()` undefined | ✅ Fixed | Synced config/database.php |
| Step 4-7 not implemented | 🔲 TODO | Need new endpoints and UI |

---

### Files Synced to Web Directory

```
/var/www/html/basic_educational/
├── admin_courses.html ✅
├── admin_course_create_step1.html ✅
├── admin_content_review.html ✅
├── admin_course_outline.html ✅
├── config/database.php ✅
└── api/admin/
    ├── create_course_draft.php ✅
    ├── list_course_drafts.php ✅
    ├── list_published_courses.php ✅
    ├── get_course_draft.php ✅
    ├── get_approved_standards.php ✅
    ├── get_draft_outline.php ✅
    ├── approve_standards.php ✅
    ├── generate_draft_outline.php ✅
    ├── delete_course.php ✅
    └── publish_course.php ✅
```

---

### Next Steps (Priority Order)

1. ✅ **Test Steps 1-3 end-to-end** - Created draft, approved standards, generated outline
2. 🔲 **Create migration 005** - Add `draft_lessons`, `draft_questions`, `draft_scraped_content` tables
3. 🔲 **Implement Step 4** - Content scraping (Wikipedia, CK-12 API integration)
4. 🔲 **Implement Step 5** - `generate_lesson_for_draft.php`
5. 🔲 **Implement Steps 6-7** - Quiz/test question generation
6. ✅ **Create unified wizard UI** - `admin_course_wizard.html`
7. ✅ **Build course agent API** - `api/admin/course_agent.php`

---

## Session: December 1, 2025 (Continued)

### What We Did (Part 2)

#### 7. Removed Source/Complexity Fields
- **Problem:** User requested simpler metadata (just name, subject, grade)
- **Solution:** Removed `source` and `complexity` from:
  - `admin_course_create_step1.html` - Form fields removed
  - `api/admin/create_course_draft.php` - Validation and INSERT updated
  - `migrations/004_course_drafts.sql` - Schema updated
  - Database table - Columns dropped via ALTER TABLE
- **Files Changed:**
  - `admin_course_create_step1.html`
  - `api/admin/create_course_draft.php`
  - `migrations/004_course_drafts.sql`

#### 8. Created Course Agent API (`api/admin/course_agent.php`)
- **Purpose:** Agent that monitors draft progress and can auto-execute steps
- **Endpoints:**
  - `GET ?action=status&draftId=X` - Returns workflow status, current step, what's next
  - `GET ?action=list` - Lists all drafts with their status
  - `POST ?action=next&draftId=X` - Auto-executes the next step
- **Features:**
  - Reads from same tables as manual UI (no duplication)
  - Auto-approve standards from scraped_content metadata
  - Auto-generate outline from approved standards
  - Returns structured JSON with step status and next actions
- **Files Created:**
  - `api/admin/course_agent.php`

#### 9. Fixed "Error Loading Drafts" Bug
- **Problem:** `list_course_drafts.php` referenced removed `source` column
- **Solution:** Updated SELECT query to remove `source` and `complexity`
- **Files Changed:**
  - `api/admin/list_course_drafts.php`

#### 10. Created Unified Course Wizard (`admin_course_wizard.html`)
- **Problem:** Multi-page wizard broke when user left to scrape content
- **Solution:** Single-page wizard with all 4 steps:
  - Step 1: Metadata form
  - Step 2: Standards (load from scraped_content, select, approve)
  - Step 3: Outline (generate, preview units/lessons)
  - Step 4: Review & Publish
- **Features:**
  - Agent panel on right side showing status
  - "Auto-execute" button for automated steps
  - Resume from any point via `?draftId=X`
  - Step indicators show completed/active/pending
  - All data visible inline (no page jumping)
- **Files Created:**
  - `admin_course_wizard.html`

#### 11. Updated Course Management Hub
- **Changed:** "Start New Course" now links to unified wizard
- **Changed:** "Continue" button goes to unified wizard with draftId
- **Removed:** References to `source` field in draft list display
- **Files Changed:**
  - `admin_courses.html`

---

### Test Results: Draft 2 (Science 2A)

| Step | Status | Data |
|------|--------|------|
| 1. Create Draft | ✅ Complete | draft_id=2, "Science 2A", grade_2, science |
| 2. Scrape Standards | ✅ Complete | 56 Alaska Grade 2 Science standards |
| 3. Approve Standards | ✅ Complete | 56 standards in approved_standards table |
| 4. Generate Outline | ✅ Complete | 10 units, 56 lessons |
| 5. Scrape Content | 🔲 Not implemented | Next to build |
| 6. Generate Lessons | 🔲 Not implemented | |
| 7. Generate Questions | 🔲 Not implemented | |

**Agent Test:**
```bash
# Get status
curl "http://localhost/basic_educational/api/admin/course_agent.php?action=status&draftId=2"
# Response: currentStep=5, "Scrape Lesson Content" (not implemented)

# Auto-approve worked
curl -X POST "...?action=next&draftId=2"  
# Response: "Auto-approved 56 standards"

# Auto-generate outline worked
curl -X POST "...?action=next&draftId=2"
# Response: "Generated outline with 10 units"
```

---

### Current Bugs/Issues

| Issue | Status | Notes |
|-------|--------|-------|
| Step 1 button invisible | ✅ Fixed | Changed to hex colors |
| `getAdminId()` undefined | ✅ Fixed | Synced config/database.php |
| `source` column errors | ✅ Fixed | Removed from all queries |
| Multi-page wizard breaks | ✅ Fixed | Created unified wizard |
| Steps 5-7 not implemented | 🔲 TODO | Content scraping next |

---

### Files Synced to Web Directory

```
/var/www/html/basic_educational/
├── admin_courses.html ✅ (updated)
├── admin_course_wizard.html ✅ (NEW)
├── admin_course_create_step1.html ✅
├── admin_content_review.html ✅
├── admin_course_outline.html ✅
├── config/database.php ✅
└── api/admin/
    ├── course_agent.php ✅ (NEW)
    ├── create_course_draft.php ✅ (updated)
    ├── list_course_drafts.php ✅ (updated)
    ├── list_published_courses.php ✅
    ├── get_course_draft.php ✅
    ├── get_approved_standards.php ✅
    ├── get_draft_outline.php ✅
    ├── approve_standards.php ✅
    ├── generate_draft_outline.php ✅
    ├── delete_course.php ✅
    └── publish_course.php ✅
```

---

### Next Steps (Updated Priority)

1. 🔲 **Implement Step 5: Content Scraping**
   - Create `api/admin/scrape_lesson_content.php`
   - Integrate Wikipedia API
   - Integrate CK-12 (if API available)
   - Store in `draft_scraped_content` table
   - Add UI to wizard Step 3 (after outline)

2. 🔲 **Create migration 005** - New tables:
   - `draft_lessons` - Generated lesson content
   - `draft_questions` - Generated quiz/test questions
   - `draft_scraped_content` - Links scraped content to lessons

3. 🔲 **Implement Step 6: Generate Lessons**
   - LLM takes scraped content + standard → lesson content

4. 🔲 **Implement Step 7: Generate Questions**
   - LLM generates quiz bags per lesson
   - LLM generates unit test bags

5. 🔲 **Expand wizard UI** - Add Steps 5-7 to unified wizard

---

### Commands Used

```bash
# Sync to web
cp /home/steve/Professor_Hawkeinstein/admin_courses.html /var/www/html/basic_educational/
cp /home/steve/Professor_Hawkeinstein/admin_course_wizard.html /var/www/html/basic_educational/
cp /home/steve/Professor_Hawkeinstein/api/admin/course_agent.php /var/www/html/basic_educational/api/admin/

# Drop columns from existing table
mysql -u professorhawkeinstein_user -pBT1716lit professorhawkeinstein_platform \
  -e "ALTER TABLE course_drafts DROP COLUMN source, DROP COLUMN complexity;"

# Test agent
TOKEN=$(curl -s -X POST http://localhost/basic_educational/api/auth/login.php \
  -H 'Content-Type: application/json' -d '{"username":"root","password":"Root1234"}' | jq -r '.token')
curl -s "http://localhost/basic_educational/api/admin/course_agent.php?action=status&draftId=2" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

---

### Error Log

#### Error 1: Call to undefined function getAdminId()
- **When:** Submitting Step 1 form
- **Location:** `/var/www/html/basic_educational/api/admin/create_course_draft.php:27`
- **Cause:** `config/database.php` in web directory didn't have the function
- **Fix:** `cp config/database.php /var/www/html/basic_educational/config/database.php`

#### Error 2: Unknown column 'source' in field list
- **When:** Loading drafts list
- **Location:** `api/admin/list_course_drafts.php:11`
- **Cause:** Column was dropped but query still referenced it
- **Fix:** Updated SELECT to remove `source` and `complexity`

#### Error 3: Auto-approve returned 0 standards
- **When:** Agent action=next for step 3
- **Cause:** Standards were in `metadata.standards` array, not top-level
- **Fix:** Added `$standards = $metadata['standards'] ?? $metadata;` in course_agent.php

---

*Log updated: December 1, 2025*

---

## Session: December 1-2, 2025 (Content Scraping Implementation)

### What We Did

#### 12. Created Migration 005: Draft Lesson Tables
- **File:** `migrations/005_draft_lessons.sql`
- **Tables Created:**
  - `draft_lessons` - Stores generated lesson content (FK to course_drafts, outline)
  - `draft_questions` - Stores quiz/test questions per lesson or unit
  - `draft_lesson_content` - Links scraped_content to specific lessons (by unit_index, lesson_index)
- **Purpose:** Track content scraped for each lesson separately

#### 13. Created `api/admin/scrape_lesson_content.php`
- **Purpose:** Scrape Wikipedia/CK-12/custom URL content and link it to a specific lesson
- **Input:** `{ draftId, unitIndex, lessonIndex, source: "wikipedia"|"ck12"|"custom", topic?, url? }`
- **Process:**
  1. For Wikipedia: Build URL from topic, fetch content via cURL
  2. For custom: Direct URL fetch
  3. Store in `scraped_content` table
  4. Link to lesson via `draft_lesson_content` table
- **Output:** `{ success, contentId, title, url, contentLength, source }`

#### 14. Created `api/admin/get_lesson_content.php`
- **Purpose:** Get all scraped content for lessons in a draft
- **Returns:** Map of `{ "u{X}_l{Y}": { contentCount, titles } }` per lesson

#### 15. Updated `admin_course_wizard.html` to 5 Steps
- **Change:** Expanded from 4 to 5 steps:
  1. Metadata
  2. Standards
  3. Outline
  4. Content Scraping (NEW)
  5. Review & Publish
- **Added Functions:**
  - `loadContentScraping()` - Loads outline and existing content
  - `renderContentGrid()` - Displays grid of lessons with content status
  - `scrapeForLesson(unitIndex, lessonIndex)` - Opens modal to select source
  - Source modal with Wikipedia/CK-12/Custom URL options

#### 16. Updated `course_agent.php` for Step 5
- **Changes:**
  - Now queries `draft_lesson_content` to count lessons with content
  - Step 5 shows: `{ lessons_with_content, total_lessons, progress_percent }`
  - `determineCurrentStep()` returns step 5 when lessons need content
  - Fixed JSON body parsing (was only reading GET/POST form data)
- **Agent now sees:** "1/56 lessons have content (2%)"

---

### Test Results

#### Wikipedia Scraping Test
```bash
curl -X POST "http://localhost/basic_educational/api/admin/scrape_lesson_content.php" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"draftId":2,"unitIndex":0,"lessonIndex":0,"source":"wikipedia","topic":"Properties of water"}'

# Response:
{
  "success": true,
  "contentId": "5",
  "title": "Properties of water (Wikipedia)",
  "url": "https://en.wikipedia.org/wiki/Properties_of_water",
  "contentLength": 1380,
  "source": "wikipedia"
}
```

#### Lesson Content Check
```bash
curl "http://localhost/basic_educational/api/admin/get_lesson_content.php?draftId=2"

# Response:
{
  "success": true,
  "draftId": 2,
  "lessonContent": {
    "u0_l0": { "unitIndex": 0, "lessonIndex": 0, "contentCount": 1, "titles": ["Properties of water (Wikipedia)"] }
  },
  "totalLessonsWithContent": 1
}
```

#### Agent Status (Step 5)
```bash
curl -X POST "http://localhost/basic_educational/api/admin/course_agent.php" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"action":"status","draftId":2}'

# Response (relevant parts):
{
  "workflow": {
    "currentStep": 5,
    "currentStepName": "Scrape Lesson Content",
    "nextAction": "Scrape content for lessons (1/56 done). Go to wizard Step 4.",
    "canAutoExecute": true
  },
  "steps": [
    { "step": 5, "name": "Scrape Lesson Content", "status": "in_progress",
      "data": { "lessons_with_content": 1, "total_lessons": 56, "progress_percent": 2 }
    }
  ]
}
```

---

### Files Created/Changed

```
/home/steve/Professor_Hawkeinstein/
├── migrations/005_draft_lessons.sql ✅ (NEW)
├── api/admin/
│   ├── scrape_lesson_content.php ✅ (NEW)
│   ├── get_lesson_content.php ✅ (NEW)
│   └── course_agent.php ✅ (UPDATED - step 5 detection)
└── admin_course_wizard.html ✅ (UPDATED - 5 steps)

/var/www/html/basic_educational/
├── admin_course_wizard.html ✅ (synced)
└── api/admin/
    ├── scrape_lesson_content.php ✅ (synced)
    ├── get_lesson_content.php ✅ (synced)
    └── course_agent.php ✅ (synced)
```

---

### Next Steps

1. 🔲 **Auto-scrape in Agent** - Implement `autoScrapeContent()` in course_agent.php
   - Auto-generate Wikipedia topic from lesson title
   - Batch scrape content for all lessons without content
   
2. 🔲 **CK-12 Integration** - Research CK-12 API or scraping approach

3. 🔲 **Implement Step 6: Generate Lessons**
   - Create `api/admin/generate_lesson.php`
   - LLM takes scraped content + standard → formatted lesson

4. 🔲 **Implement Step 7: Generate Questions**
   - Create `api/admin/generate_questions.php`
   - Quiz bags (5-10 questions per lesson)
   - Unit test bags (20-30 questions per unit)

---

*Log updated: December 2, 2025*

---

## Session: December 2, 2025 (LLM Generation Architecture)

### Major Architecture Change: Web Scraping → LLM Generation

**Decision:** Replace web scraping (Wikipedia, CK-12) with local LLM generation for all lesson content.

**Rationale:**
- Standards like "develop an understanding that historical perspectives..." don't map to Wikipedia articles
- LLM can generate age-appropriate content directly from standards
- No external API dependencies for content
- Faster, more consistent results
- Content directly aligned to learning objectives

### New 5-Agent Pipeline

| Agent | Purpose | Input | Output | Status |
|-------|---------|-------|--------|--------|
| 1. Standards-to-Outline | Structure course from standards | Selected standards | Outline JSON | ✅ Done |
| 2. Lesson Builder | Generate lesson content | 1 lesson + standard | Lesson content JSON | ✅ Basic done |
| 3. Question Bank Generator | Create quiz questions | Single lesson | Question bank JSON | 🔲 TODO |
| 4. Unit Test Generator | Compile unit test | All lesson question banks | Unit test JSON | 🔲 TODO |
| 5. Summary/Validator | QA check | Full course content | Validation report | 🔲 TODO |

### What We Did

#### 17. Added LLM Generation to scrape_lesson_content.php
- Added `source=generate` option alongside wikipedia/ck12/custom
- Created `generateWithLLM()` function with age-appropriate prompting
- LLM creates 2-3 paragraphs + fun facts + thinking question
- Content stored in `scraped_content` table with `llm://` URL scheme

#### 18. Updated Wizard UI Default
- "🤖 Generate with AI (Recommended)" is now default option
- Wikipedia, CK-12, Custom URL still available as alternatives
- Scrape Unit and Auto-Scrape All use selected source

#### 19. Created COURSE_GENERATION_ARCHITECTURE.md
- Documents the 5-agent pipeline
- Specifies input/output for each agent
- Includes LLM prompt templates
- Lists database schema changes needed
- Tracks implementation status

### Test Results

```bash
# LLM Generation Test
curl -X POST "http://localhost/basic_educational/api/admin/scrape_lesson_content.php" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"draftId":3,"unitIndex":0,"lessonIndex":0,"lessonTitle":"Historical perspectives of science","source":"generate"}'

# Response:
{
  "success": true,
  "contentId": "8",
  "title": "Lesson: Historical perspectives of science (AI Generated)",
  "contentLength": 1134,
  "source": "generate"
}
```

### Files Changed

```
/home/steve/Professor_Hawkeinstein/
├── api/admin/scrape_lesson_content.php  ✅ Added generateWithLLM()
├── admin_course_wizard.html             ✅ AI generation as default
└── COURSE_GENERATION_ARCHITECTURE.md    ✅ NEW - 5-agent architecture doc
```

### Next Steps (Priority Order)

1. 🔲 **Implement Agent 3** - Question Bank Generator
   - Create `api/admin/generate_lesson_questions.php`
   - LLM prompt for mixed question types
   - Store in `draft_questions` table

2. 🔲 **Implement Agent 4** - Unit Test Generator
   - Create `api/admin/generate_unit_test.php`
   - Compile questions from all unit lessons
   - Balance difficulty and coverage

3. 🔲 **Implement Agent 5** - Validator
   - Create `api/admin/validate_course.php`
   - Check standards coverage
   - Flag potential issues

4. 🔲 **Update Wizard** - Add Steps 5-7
   - Step 5: Generate questions (per lesson + per unit)
   - Step 6: Validation review
   - Step 7: Publish

5. 🔲 **Update course_agent.php** - Auto-execute pipeline
   - Chain agents 2-4 automatically
   - Human checkpoints at outline and final review

---

*Log updated: December 2, 2025*

---

## Session: December 2, 2025 (Continued - Question Bank Architecture)

### Wizard UI Overhaul Complete

#### 20. Removed Web Scraping Options from Wizard
- Removed source dropdown (Wikipedia, CK-12, Custom URL)
- All content now uses LLM generation exclusively
- Renamed "Scrape" terminology to "Generate" throughout
- Added Review modal with Approve/Regenerate workflow

#### 21. Fixed Review Modal
- Shows full lesson content (not truncated)
- Displays title and generation timestamp
- Buttons: Regenerate (closes modal, regenerates, reopens), Close, Approve
- Approved lessons show green background and "✅ Approved" status

#### 22. Fixed Regeneration (Delete-Before-Insert)
- Modified `scrape_lesson_content.php` to delete existing content before generating new
- Cleans up `draft_lesson_content` links and `scraped_content` rows
- Prevents duplicate content accumulation

#### 23. Fixed C++ Agent Service CURL Issues
- Changed from reusing CURL handle to creating fresh handle per request
- Increased timeout to 180 seconds for content generation
- Fixed "Failed initialization" errors from handle corruption

#### 24. Increased Token Limits for Lesson Content
- Chat responses: 256 tokens (fast)
- Lesson generation: 512 tokens with 180s timeout
- PHP callAgentService timeout: 180 seconds
- Longer prompts now request 4-5 paragraphs (400-500 words)

### Question Bank Requirements Update

**NEW SPECIFICATION (Per Lesson):**
- **3 Question Bags** per lesson:
  1. Fill-in-the-Blank: 20 questions
  2. Multiple Choice: 20 questions  
  3. Short Essay: 20 questions
- **Total: 60 questions per lesson**
- **For 4-lesson unit: 240 questions total**

**Question Type Details:**

| Type | Count | Format | Grading |
|------|-------|--------|---------|
| Fill-in-Blank | 20 | Single blank with hint | Auto-graded |
| Multiple Choice | 20 | 4 options with explanation | Auto-graded |
| Short Essay | 20 | Open response with rubric | Teacher-graded |

**Difficulty Distribution (per bag):**
- Easy: 8 questions (40%)
- Medium: 8 questions (40%)
- Hard: 4 questions (20%)

### Files Changed This Session

```
/home/steve/Professor_Hawkeinstein/
├── admin_course_wizard.html             ✅ LLM-only UI, review modal, approve flow
├── api/admin/scrape_lesson_content.php  ✅ Delete-before-regenerate
├── api/admin/get_lesson_content.php     ✅ Returns full content_text
├── config/database.php                  ✅ 180s timeout for callAgentService
├── cpp_agent/src/llamacpp_client.cpp    ✅ Fresh CURL handles, variable tokens
├── COURSE_GENERATION_ARCHITECTURE.md    ✅ Updated Agent 3 spec (60 questions)
└── COURSE_AGENT_DEBUG.md               ✅ This file
```

### Current Progress: Draft 3 (Science 2A)

| Step | Status | Data |
|------|--------|------|
| 1. Create Draft | ✅ Complete | draft_id=3, "Science 2A", grade_2, science |
| 2. Approve Standards | ✅ Complete | Alaska + NGSS standards |
| 3. Generate Outline | ✅ Complete | 11 units, 38 lessons |
| 4. Generate Lessons | 🔄 In Progress | Unit 1 (4 lessons) approved |
| 5. Generate Questions | 🔲 Next | 60 questions per lesson |
| 6. Generate Unit Tests | 🔲 TODO | |
| 7. Validate & Publish | 🔲 TODO | |

### Next: Implement Agent 3 (Question Bank Generator)

**Files to Create:**
1. `api/admin/generate_lesson_questions.php` - Main endpoint
2. `migrations/005_question_banks.sql` - Database schema

**Implementation Plan:**
1. Create database table `lesson_question_banks`
2. Create API endpoint with LLM prompts for each question type
3. Generate questions in 3 batches (one per type)
4. Store JSON in database
5. Add Step 5 to wizard UI

---

*Session log updated: December 2, 2025 14:45*
