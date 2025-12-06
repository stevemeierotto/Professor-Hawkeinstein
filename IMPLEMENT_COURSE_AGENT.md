# Course Creation Agent - Implementation Plan

## Overview

This document outlines the 7-step automated course creation workflow for Professor Hawkeinstein's Educational Platform. The "Course Agent" will execute API calls in sequence with human checkpoints for approval.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     COURSE CREATION WORKFLOW                        │
├─────────────────────────────────────────────────────────────────────┤
│  Step 1: Create Draft                                               │
│     └─> course_drafts table                                         │
│                                                                     │
│  Step 2: Get Standards (CSP API)                                    │
│     └─> approved_standards table                                    │
│                                                                     │
│  Step 3: Generate Outline (LLM)                                     │
│     └─> course_outlines table                                       │
│     └─> ⏸️ HUMAN APPROVAL CHECKPOINT                                │
│                                                                     │
│  Step 4: Scrape Content (Wikipedia/CK-12)                           │
│     └─> scraped_content table                                       │
│     └─> draft_scraped_content (linking table)                       │
│                                                                     │
│  Step 5: Generate Lessons (LLM from scraped content)                │
│     └─> draft_lessons table                                         │
│                                                                     │
│  Step 6: Generate Quiz Question Bags (per lesson)                   │
│     └─> draft_questions table (scope=lesson)                        │
│                                                                     │
│  Step 7: Generate Unit Test Bags (per unit)                         │
│     └─> draft_questions table (scope=unit)                          │
│     └─> ⏸️ HUMAN APPROVAL CHECKPOINT                                │
│                                                                     │
│  Step 8: Publish Course                                             │
│     └─> courses, units, lessons, quiz_questions tables              │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Implementation

### Step 1: Create New Course (Draft)

**Status:** ✅ Implemented

**Endpoint:** `POST /api/admin/create_course_draft.php`

**Input:**
```json
{
  "courseName": "5th Grade Math - Fractions",
  "subject": "mathematics",
  "grade": "grade_5",
  "source": "CSP",
  "complexity": "intermediate"
}
```

**Output:**
```json
{
  "success": true,
  "draftId": 5
}
```

**Database:** `course_drafts` table

**UI:** `admin_course_create_step1.html`

---

### Step 2: Fetch & Approve Standards

**Status:** ✅ Implemented

**Endpoints:**
1. `POST /api/admin/fetch_and_normalize_standards.php` - Fetches from CSP API
2. `POST /api/admin/approve_standards.php` - Saves selection

**Input (fetch):**
```json
{
  "jurisdictionId": "0DCD3CBE12314408BDBDB97FAF45EEE8",
  "grade": "5",
  "subject": "Math"
}
```

**Input (approve):**
```json
{
  "draftId": 5,
  "standards": [
    {"standard_id": "xxx", "standard_code": "5.NF.A.1", "description": "Add fractions..."}
  ]
}
```

**Database:** `approved_standards` table

**UI:** `admin_content_review.html`

---

### Step 3: Generate Course Outline

**Status:** ✅ Implemented

**Endpoint:** `POST /api/admin/generate_draft_outline.php`

**Input:**
```json
{
  "draftId": 5
}
```

**Output:**
```json
{
  "success": true,
  "outline": [
    {
      "title": "Unit 1: Introduction to Fractions",
      "lessons": [
        {"title": "What is a Fraction?", "description": "..."},
        {"title": "Parts of a Whole", "description": "..."}
      ]
    }
  ]
}
```

**Database:** `course_outlines` table

**UI:** `admin_course_outline.html`

**⏸️ HUMAN CHECKPOINT:** Admin reviews and approves outline before proceeding.

---

### Step 4: Scrape Content for Lessons

**Status:** ❌ NOT IMPLEMENTED

**Purpose:** For each lesson in the outline, scrape educational content from Wikipedia, CK-12, or other sources to use as source material for lesson generation.

**New Endpoint Needed:** `POST /api/admin/scrape_lesson_content.php`

**Input:**
```json
{
  "draftId": 5,
  "unitIndex": 0,
  "lessonIndex": 2,
  "lessonTitle": "Adding Fractions with Unlike Denominators",
  "sources": ["wikipedia", "ck12"]
}
```

**Process:**
1. Generate search URL from lesson title
   - Wikipedia: `https://en.wikipedia.org/wiki/Fraction_(mathematics)`
   - CK-12: `https://www.ck12.org/search?q=adding+fractions`
2. Call existing `scraper.php` logic to fetch HTML
3. Extract instructional content using `summarize_content.php` AI extraction
4. Store in `scraped_content` table
5. Link to draft via `draft_scraped_content` table

**Output:**
```json
{
  "success": true,
  "scraped": [
    {"source": "wikipedia", "url": "...", "content_id": 123, "chars": 5000},
    {"source": "ck12", "url": "...", "content_id": 124, "chars": 3500}
  ]
}
```

**New Database Table:**
```sql
CREATE TABLE draft_scraped_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    content_id INT NOT NULL,
    source_type VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id),
    FOREIGN KEY (content_id) REFERENCES scraped_content(content_id)
);
```

**UI Needed:** `admin_scrape_lessons.html` (or integrate into existing page)

---

### Step 5: Generate Lessons

**Status:** ⚠️ Backend exists (`generate_lesson.php`), needs wiring to draft system

**New Endpoint Needed:** `POST /api/admin/generate_lesson_for_draft.php`

**Input:**
```json
{
  "draftId": 5,
  "unitIndex": 0,
  "lessonIndex": 2
}
```

**Process:**
1. Load lesson title/objectives from `course_outlines`
2. Load scraped content from `draft_scraped_content` + `scraped_content`
3. Load relevant standards from `approved_standards`
4. Call LLM with combined prompt
5. Store result in `draft_lessons` table

**LLM Prompt Template:**
```
Based on the following educational source material:
[scraped content from Wikipedia/CK-12]

Create a complete lesson for: "{lessonTitle}"
Grade Level: {gradeLevel}
Standards: {standardCode} - {standardDescription}

Include:
1. Introduction/Hook (engage student interest)
2. Explanation with examples (clear, step-by-step)
3. Worked examples (2-3 fully solved problems)
4. Practice problems (3-5 for student to try)
5. Summary/Key takeaways
```

**Output:**
```json
{
  "success": true,
  "lessonId": 45,
  "content": {
    "introduction": "...",
    "explanation": "...",
    "examples": [...],
    "practice": [...],
    "summary": "..."
  }
}
```

**New Database Table:**
```sql
CREATE TABLE draft_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    lesson_title VARCHAR(255),
    content_json LONGTEXT,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id)
);
```

**UI Needed:** `admin_generate_lessons.html`

---

### Step 6: Generate Quiz Question Bags (Per Lesson)

**Status:** ⚠️ Backend exists (`generate_assessment.php`), needs adaptation

**New Endpoint Needed:** `POST /api/admin/generate_lesson_questions.php`

**Input:**
```json
{
  "draftId": 5,
  "unitIndex": 0,
  "lessonIndex": 2,
  "questionCount": 10,
  "questionTypes": ["multiple_choice", "true_false", "fill_blank"]
}
```

**Process:**
1. Load lesson content from `draft_lessons`
2. Call LLM to generate N questions covering lesson material
3. Each quiz pulls random subset from this bag
4. Store in `draft_questions` table with `scope=lesson`

**Output:**
```json
{
  "success": true,
  "questions": [
    {
      "type": "multiple_choice",
      "text": "What is 1/4 + 1/2?",
      "options": ["1/6", "2/6", "3/4", "1/4"],
      "correct_answer": "3/4",
      "explanation": "Convert to common denominator: 1/4 + 2/4 = 3/4"
    }
  ]
}
```

**New Database Table:**
```sql
CREATE TABLE draft_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    scope ENUM('lesson', 'unit', 'final') NOT NULL,
    unit_index INT,
    lesson_index INT,
    question_type VARCHAR(50),
    question_text TEXT,
    options_json TEXT,
    correct_answer TEXT,
    explanation TEXT,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id)
);
```

**UI Needed:** `admin_generate_assessments.html`

---

### Step 7: Generate Unit Test Question Bags

**Status:** ❌ NOT IMPLEMENTED

**New Endpoint Needed:** `POST /api/admin/generate_unit_questions.php`

**Input:**
```json
{
  "draftId": 5,
  "unitIndex": 0,
  "questionCount": 20
}
```

**Process:**
1. Load all lesson content for the unit from `draft_lessons`
2. Call LLM to generate N questions covering entire unit
3. Questions should span all lessons in unit
4. Store in `draft_questions` table with `scope=unit`

**⏸️ HUMAN CHECKPOINT:** Admin reviews full course content before publish.

---

### Step 8: Publish Course

**Status:** ⚠️ Basic publish exists, needs enhancement

**Endpoint:** `POST /api/admin/publish_course.php` (enhance existing)

**Process:**
1. Load all draft data (outline, lessons, questions)
2. Insert into production tables:
   - `courses` (main course record)
   - `units` (one per unit in outline)
   - `lessons` (one per lesson in draft_lessons)
   - `quiz_questions` (all from draft_questions)
   - `quiz_configurations` (default settings)
3. Update `course_drafts.status = 'published'`
4. Return course ID

---

## New Files Needed

### Database Migration
- `migrations/005_draft_content.sql`

### Backend Endpoints
- `api/admin/scrape_lesson_content.php` (Step 4)
- `api/admin/generate_lesson_for_draft.php` (Step 5)
- `api/admin/generate_lesson_questions.php` (Step 6)
- `api/admin/generate_unit_questions.php` (Step 7)
- `api/admin/get_draft_lessons.php` (helper)
- `api/admin/get_draft_questions.php` (helper)

### UI Pages
- `admin_scrape_lessons.html` (Step 4)
- `admin_generate_lessons.html` (Step 5)
- `admin_generate_assessments.html` (Steps 6-7)
- `admin_finalize_course.html` (Step 8 review)

---

## Agent Pseudo-Code

```python
def create_course(metadata):
    # Step 1
    draft_id = call_api("create_course_draft", metadata)
    
    # Step 2
    standards = call_api("fetch_and_normalize_standards", {
        "jurisdictionId": get_jurisdiction(metadata.state),
        "grade": metadata.grade,
        "subject": metadata.subject
    })
    call_api("approve_standards", {"draftId": draft_id, "standards": standards})
    
    # Step 3
    outline = call_api("generate_draft_outline", {"draftId": draft_id})
    
    # ⏸️ PAUSE FOR HUMAN APPROVAL
    wait_for_approval("outline", draft_id)
    
    # Step 4: Scrape content for each lesson
    for unit_idx, unit in enumerate(outline):
        for lesson_idx, lesson in enumerate(unit.lessons):
            call_api("scrape_lesson_content", {
                "draftId": draft_id,
                "unitIndex": unit_idx,
                "lessonIndex": lesson_idx,
                "lessonTitle": lesson.title,
                "sources": ["wikipedia", "ck12"]
            })
            sleep(2)  # Rate limiting
    
    # Step 5: Generate lessons from scraped content
    for unit_idx, unit in enumerate(outline):
        for lesson_idx, lesson in enumerate(unit.lessons):
            call_api("generate_lesson_for_draft", {
                "draftId": draft_id,
                "unitIndex": unit_idx,
                "lessonIndex": lesson_idx
            })
    
    # Step 6: Generate quiz questions for each lesson
    for unit_idx, unit in enumerate(outline):
        for lesson_idx, lesson in enumerate(unit.lessons):
            call_api("generate_lesson_questions", {
                "draftId": draft_id,
                "unitIndex": unit_idx,
                "lessonIndex": lesson_idx,
                "questionCount": 10
            })
    
    # Step 7: Generate unit test questions
    for unit_idx, unit in enumerate(outline):
        call_api("generate_unit_questions", {
            "draftId": draft_id,
            "unitIndex": unit_idx,
            "questionCount": 20
        })
    
    # ⏸️ PAUSE FOR HUMAN APPROVAL
    wait_for_approval("full_course", draft_id)
    
    # Step 8: Publish
    course_id = call_api("publish_course", {"draftId": draft_id})
    return course_id
```

---

## Configuration

### Default Course Structure
- **Units per course:** 6
- **Lessons per unit:** 5
- **Quiz questions per lesson:** 10
- **Unit test questions:** 20
- **Quiz format:** Random 5 questions from lesson bag
- **Unit test format:** Random 10 questions from unit bag

### Content Sources
- **Primary:** Wikipedia (comprehensive, free)
- **Secondary:** CK-12 (grade-appropriate, free)
- **Fallback:** Manual URL entry by admin

### Rate Limiting
- **Scraping delay:** 2 seconds between requests
- **LLM delay:** 1 second between generations
- **Max retries:** 3

---

## Status Tracking

Use `course_drafts.status` field:
- `draft` - Step 1 complete
- `standards_review` - Step 2 complete, awaiting approval
- `outline_review` - Step 3 complete, awaiting approval
- `scraping` - Step 4 in progress
- `generating_lessons` - Step 5 in progress
- `generating_quizzes` - Steps 6-7 in progress
- `final_review` - All steps complete, awaiting publish
- `published` - Step 8 complete

---

## Implementation Order

1. ✅ Fix existing Step 1-3 bugs (button visibility, getAdminId)
2. 🔲 Create database migration (005_draft_content.sql)
3. 🔲 Implement Step 4 (scrape_lesson_content.php)
4. 🔲 Implement Step 5 (generate_lesson_for_draft.php)
5. 🔲 Implement Step 6 (generate_lesson_questions.php)
6. 🔲 Implement Step 7 (generate_unit_questions.php)
7. 🔲 Enhance Step 8 (publish_course.php)
8. 🔲 Create UI pages for Steps 4-7
9. 🔲 Test full workflow manually
10. 🔲 Build automation agent

---

*Last Updated: December 1, 2025*
