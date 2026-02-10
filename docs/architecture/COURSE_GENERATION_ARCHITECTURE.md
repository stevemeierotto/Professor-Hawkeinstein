# Course Generation Architecture

## Overview

**Major Change (December 2, 2025):** Course content is now generated entirely by the local LLM instead of web scraping. This provides:
- Consistent, age-appropriate content
- No external API dependencies for content
- Content directly aligned to standards
- Faster generation (no network delays)

We still scrape **educational standards** from CSP (Common Standards Project), but all **lesson content** is LLM-generated.

---

## 5-Agent Pipeline Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    COURSE GENERATION PIPELINE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   [Standards from CSP]                                                      │
│          │                                                                  │
│          ▼                                                                  │
│   ┌──────────────────────────────────────┐                                  │
│   │  AGENT 1: Standards-to-Outline       │                                  │
│   │  Input: Selected standards           │                                  │
│   │  Output: Course outline JSON         │                                  │
│   │  (units + lessons structure)         │                                  │
│   └──────────────────────────────────────┘                                  │
│          │                                                                  │
│          ▼  ⏸️ HUMAN APPROVAL CHECKPOINT                                    │
│          │                                                                  │
│          ▼                                                                  │
│   ┌──────────────────────────────────────┐                                  │
│   │  AGENT 2: Lesson Builder             │  ← Runs for EACH lesson          │
│   │  Input: 1 outline lesson + standard  │                                  │
│   │  Output: Full lesson content JSON    │                                  │
│   │  (intro, body, activities, summary)  │                                  │
│   └──────────────────────────────────────┘                                  │
│          │                                                                  │
│          ▼                                                                  │
│   ┌──────────────────────────────────────┐                                  │
│   │  AGENT 3: Question Bank Generator    │  ← Runs for EACH lesson          │
│   │  Input: Single lesson content        │                                  │
│   │  Output: Question bank JSON          │                                  │
│   │  (5-10 quiz questions per lesson)    │                                  │
│   └──────────────────────────────────────┘                                  │
│          │                                                                  │
│          ▼                                                                  │
│   ┌──────────────────────────────────────┐                                  │
│   │  AGENT 4: Unit Test Generator        │  ← Runs for EACH unit            │
│   │  Input: All lesson question banks    │                                  │
│   │  Output: Unit test question bank     │                                  │
│   │  (20-30 questions from all lessons)  │                                  │
│   └──────────────────────────────────────┘                                  │
│          │                                                                  │
│          ▼                                                                  │
│   ┌──────────────────────────────────────┐                                  │
│   │  AGENT 5: Summary/Validator          │                                  │
│   │  Checks:                             │                                  │
│   │  - Content aligned to standards?     │                                  │
│   │  - Any hallucinations?               │                                  │
│   │  - Missing steps/concepts?           │                                  │
│   │  - Age-appropriate vocabulary?       │                                  │
│   │  Output: Validation report JSON      │                                  │
│   └──────────────────────────────────────┘                                  │
│          │                                                                  │
│          ▼  ⏸️ HUMAN APPROVAL CHECKPOINT                                    │
│          │                                                                  │
│          ▼                                                                  │
│   [PUBLISH COURSE]                                                          │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Agent Specifications

### Agent 1: Standards-to-Outline Agent

**Purpose:** Convert approved educational standards into a structured course outline.

**Input:**
```json
{
  "draftId": 3,
  "courseName": "Grade 2 Science",
  "grade": "grade_2",
  "subject": "science",
  "standards": [
    {"code": "G.", "description": "History and Nature of Science"},
    {"code": "1)", "description": "develop an understanding that historical perspectives..."},
    ...
  ]
}
```

**Output:**
```json
{
  "units": [
    {
      "title": "G. History and Nature of Science",
      "description": "A student should understand the history and nature of science.",
      "lessons": [
        {
          "title": "Historical Perspectives of Science",
          "description": "develop an understanding that historical perspectives...",
          "standard_code": "1)",
          "estimated_duration": "30 minutes"
        }
      ]
    }
  ]
}
```

**Implementation:** `api/admin/generate_draft_outline.php` (existing, uses pattern recognition + LLM fallback)

**Status:** ✅ Implemented

---

### Agent 2: Lesson Builder Agent

**Purpose:** Generate complete, age-appropriate lesson content from a single standard.

**Input:**
```json
{
  "draftId": 3,
  "unitIndex": 0,
  "lessonIndex": 0,
  "lessonTitle": "Historical Perspectives of Science",
  "standardDescription": "develop an understanding that historical perspectives of scientific explanations demonstrate that scientific knowledge changes over time",
  "grade": "grade_2",
  "subject": "science"
}
```

**Output:**
```json
{
  "title": "How Science Changes Over Time",
  "introduction": "Have you ever wondered why scientists keep learning new things?...",
  "content": [
    {
      "section": "Main Concept",
      "text": "Long ago, people thought the Earth was flat..."
    },
    {
      "section": "Fun Facts",
      "text": "Did you know that scientists once thought tomatoes were poisonous?"
    }
  ],
  "activities": [
    {
      "type": "discussion",
      "prompt": "What is something you believed was true that you later learned was different?"
    }
  ],
  "summary": "Science is always changing as we learn new things...",
  "vocabulary": ["discover", "experiment", "observe"],
  "thinkingQuestion": "Why do you think scientists keep asking questions?"
}
```

**Implementation:** `api/admin/generate_lesson_content.php` (pure LLM generation, no scraping)

**Status:** ✅ Basic implementation complete. Needs enhancement for structured JSON output.

---

### Agent 3: Question Bank Generator Agent

**Purpose:** Generate three separate question bags for each lesson, with different question types.

**Updated Requirements (December 2, 2025):**
- **3 Question Bags per lesson:**
  1. **Fill-in-the-Blank Bag** - 20 questions
  2. **Multiple Choice Bag** - 20 questions  
  3. **Short Essay Bag** - 20 questions
- **Total: 60 questions per lesson**
- Questions should cover all key concepts from the lesson
- Age-appropriate vocabulary for the grade level
- Range from simple recall to higher-order thinking

**Input:**
```json
{
  "draftId": 3,
  "unitIndex": 0,
  "lessonIndex": 0,
  "lessonTitle": "Historical Perspectives of Science",
  "lessonContent": "... full lesson text ...",
  "grade": "grade_2",
  "subject": "science"
}
```

**Output:**
```json
{
  "lessonId": "u0_l0",
  "lessonTitle": "Historical Perspectives of Science",
  "questionBags": {
    "fill_in_blank": {
      "type": "fill_in_blank",
      "count": 20,
      "questions": [
        {
          "id": "fib_1",
          "question": "Science is always _____ as we learn new things.",
          "correct_answer": "changing",
          "hint": "Think about how we discover new ideas"
        },
        {
          "id": "fib_2", 
          "question": "Long ago, people thought the Earth was _____.",
          "correct_answer": "flat",
          "hint": "They couldn't see the curve"
        }
        // ... 18 more fill-in-blank questions
      ]
    },
    "multiple_choice": {
      "type": "multiple_choice",
      "count": 20,
      "questions": [
        {
          "id": "mc_1",
          "question": "What did people long ago think about the shape of Earth?",
          "options": ["Round", "Flat", "Square", "Triangle"],
          "correct_answer": "Flat",
          "explanation": "Long ago, people believed Earth was flat because they couldn't see the curve."
        },
        {
          "id": "mc_2",
          "question": "Why do scientists keep asking questions?",
          "options": [
            "Because they are bored",
            "To learn new things and improve our understanding",
            "Because they forgot the answers",
            "To confuse people"
          ],
          "correct_answer": "To learn new things and improve our understanding",
          "explanation": "Scientists are curious and always want to discover more!"
        }
        // ... 18 more multiple choice questions
      ]
    },
    "short_essay": {
      "type": "short_essay",
      "count": 20,
      "questions": [
        {
          "id": "essay_1",
          "question": "Why do you think scientists keep asking questions even after they find an answer?",
          "suggested_answer": "Scientists keep asking questions because they want to learn more and check if their answers are correct. New discoveries can change what we know.",
          "rubric": {
            "full_credit": "Explains that science evolves and scientists verify findings",
            "partial_credit": "Mentions curiosity or learning",
            "keywords": ["learn", "discover", "change", "new", "check"]
          }
        },
        {
          "id": "essay_2",
          "question": "Describe something you once thought was true but later learned was different.",
          "suggested_answer": "Student shares a personal example of changing their understanding based on new information.",
          "rubric": {
            "full_credit": "Provides specific example and explains the change in understanding",
            "partial_credit": "Gives example but limited explanation",
            "keywords": ["thought", "learned", "changed", "different"]
          }
        }
        // ... 18 more short essay questions
      ]
    }
  },
  "metadata": {
    "total_questions": 60,
    "by_type": {
      "fill_in_blank": 20,
      "multiple_choice": 20,
      "short_essay": 20
    },
    "grade_level": "grade_2",
    "subject": "science",
    "generated_at": "2025-12-02T14:30:00Z"
  }
}
```

**Implementation:** `api/admin/generate_lesson_questions.php` (TO BE CREATED)

**Database Schema:**
```sql
CREATE TABLE lesson_question_banks (
  bank_id INT AUTO_INCREMENT PRIMARY KEY,
  draft_id INT NOT NULL,
  unit_index INT NOT NULL,
  lesson_index INT NOT NULL,
  question_type ENUM('fill_in_blank', 'multiple_choice', 'short_essay') NOT NULL,
  questions JSON NOT NULL,
  question_count INT DEFAULT 20,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
  UNIQUE KEY unique_lesson_type (draft_id, unit_index, lesson_index, question_type)
);
```

**Status:** 🔲 Not yet implemented

---

### Agent 4: Unit Test Generator Agent

**Purpose:** Compile and curate questions from all lessons in a unit into a comprehensive test.

**Input:**
```json
{
  "draftId": 3,
  "unitIndex": 0,
  "unitTitle": "G. History and Nature of Science",
  "lessonQuestionBanks": [
    { "lessonId": "u0_l0", "questions": [...] },
    { "lessonId": "u0_l1", "questions": [...] },
    ...
  ],
  "targetQuestionCount": 25
}
```

**Output:**
```json
{
  "unitId": "unit_0",
  "testTitle": "Unit 1 Test: History and Nature of Science",
  "questions": [
    { ... },  // Selected and possibly modified questions
    { ... }
  ],
  "metadata": {
    "total_questions": 25,
    "by_difficulty": { "easy": 10, "medium": 10, "hard": 5 },
    "by_lesson": { "u0_l0": 6, "u0_l1": 6, "u0_l2": 7, "u0_l3": 6 }
  }
}
```

**Implementation:** `api/admin/generate_unit_test.php` (TO BE CREATED)

**Status:** 🔲 Not yet implemented

---

### Agent 5: Summary/Validator Agent

**Purpose:** Quality assurance check on generated content.

**Input:**
```json
{
  "draftId": 3,
  "outline": { ... },
  "lessons": [ ... ],
  "questionBanks": [ ... ],
  "standards": [ ... ]
}
```

**Output:**
```json
{
  "validation_passed": true,
  "score": 0.92,
  "checks": {
    "standards_coverage": {
      "passed": true,
      "coverage_percent": 100,
      "missing_standards": []
    },
    "vocabulary_level": {
      "passed": true,
      "grade_appropriate": true,
      "flagged_words": []
    },
    "content_accuracy": {
      "passed": true,
      "potential_issues": []
    },
    "completeness": {
      "passed": true,
      "missing_elements": []
    }
  },
  "recommendations": [
    "Consider adding more visual activity suggestions to Lesson 3"
  ]
}
```

**Implementation:** `api/admin/validate_course.php` (TO BE CREATED)

**Status:** 🔲 Not yet implemented

---

## Database Schema

### Existing Tables (No Changes)
- `course_drafts` - Draft metadata
- `approved_standards` - Standards approved for draft
- `course_outlines` - Generated outlines (JSON)
- `scraped_content` - Now stores LLM-generated content too

### Tables to Update/Create

```sql
-- draft_lessons: Store generated lesson content
CREATE TABLE IF NOT EXISTS draft_lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    unit_index INT NOT NULL,
    lesson_index INT NOT NULL,
    lesson_json LONGTEXT,  -- Full structured lesson content
    standard_code VARCHAR(50),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson (draft_id, unit_index, lesson_index)
);

-- draft_questions: Store question banks
CREATE TABLE IF NOT EXISTS draft_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    scope ENUM('lesson', 'unit', 'course') DEFAULT 'lesson',
    unit_index INT NOT NULL,
    lesson_index INT NULL,  -- NULL for unit-level questions
    questions_json LONGTEXT,  -- Array of question objects
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE
);

-- draft_validation: Store validation results
CREATE TABLE IF NOT EXISTS draft_validation (
    validation_id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    validation_json LONGTEXT,
    score DECIMAL(3,2),
    validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (draft_id) REFERENCES course_drafts(draft_id) ON DELETE CASCADE
);
```

---

## Wizard Steps (Updated)

| Step | Name | Agent | Human Checkpoint |
|------|------|-------|------------------|
| 1 | Create Draft | - | - |
| 2 | Approve Standards | - | ✅ Select standards |
| 3 | Generate Outline | Agent 1 | ✅ Review/edit outline |
| 4 | Generate Lessons | Agent 2 | Optional preview |
| 5 | Generate Questions | Agent 3 + 4 | Optional preview |
| 6 | Validate & Review | Agent 5 | ✅ Final approval |
| 7 | Publish | - | ✅ Confirm publish |

---

## Implementation Priority

1. ✅ **Agent 1** - Standards-to-Outline (DONE)
2. ✅ **Agent 2** - Lesson Builder (Basic version DONE)
3. 🔲 **Agent 3** - Question Bank Generator (NEXT)
4. 🔲 **Agent 4** - Unit Test Generator
5. 🔲 **Agent 5** - Validator
6. 🔲 **Wizard UI** - Update Step 4+ to use new agents
7. 🔲 **course_agent.php** - Add auto-execution for agents 2-5

---

## LLM Prompt Templates

### Agent 2: Lesson Builder Prompt
```
Create a short educational lesson for Grade {grade} students (age {age}) about:
"{standardDescription}"

Requirements:
- Use simple vocabulary appropriate for Grade {grade}
- Keep it to 2-3 short paragraphs (about 150-200 words total)
- Include 1-2 fun facts that kids would find interesting
- End with a simple question for students to think about
- Make it engaging and easy to understand

Write only the lesson content, no title or headers.
```

### Agent 3: Question Generator Prompt
```
Create {count} quiz questions for Grade {grade} students based on this lesson:
"{lessonContent}"

Requirements:
- Mix of question types: multiple choice, true/false, fill in the blank
- Age-appropriate language
- Include correct answer and brief explanation
- Vary difficulty: {easy}% easy, {medium}% medium, {hard}% hard

Return as JSON array with format:
[{"type": "multiple_choice", "question": "...", "options": [...], "correct_answer": "...", "explanation": "...", "difficulty": "easy|medium|hard"}]
```

---

## Files Changed (December 2, 2025)

- `api/admin/generate_draft_outline.php` - Now uses pattern recognition (no LLM for structure)
- `api/admin/generate_lesson_content.php` - Pure LLM generation (replaces old scraping approach)
- `admin_course_wizard.html` - Added "🤖 Generate with AI" as default option
- `COURSE_GENERATION_ARCHITECTURE.md` - This file (NEW)

---

*Last Updated: December 2, 2025*
