# Course Generation API Guide

## Overview

This guide documents the complete course generation system in Professor Hawkeinstein's Educational Platform. The system uses a 5-agent pipeline to generate standards-based educational content.

**Architecture:** Standards → Outline → Lessons → Questions → Validation

---

## Quick Start Workflow

### Complete Course Creation (15-35 minutes)

1. **Create Draft** - Define course metadata
2. **Generate Standards** - AI creates educational standards
3. **Generate Outline** - Organize standards into units/lessons
4. **Generate Content** - AI creates lesson content for each lesson
5. **Generate Questions** - Create assessment questions
6. **Publish** - Make course available to students

---

## Prerequisites

```bash
# Check services are running
curl http://localhost:8080/health  # Agent service
curl http://localhost:8090/health  # LLM server
```

Get admin JWT token from browser sessionStorage after login.

---

## API Endpoints

### 1. Create Course Draft

**Endpoint:** `POST /api/admin/create_course_draft.php`

```bash
curl -X POST "http://localhost:8081/api/admin/create_course_draft.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseName": "2nd Grade Science",
    "grade": "2nd Grade",
    "subject": "Science"
  }'
```

**Response:**
```json
{
  "success": true,
  "draftId": 10,
  "message": "Draft created"
}
```

---

### 2. Generate Standards

**Endpoint:** `POST /api/admin/generate_standards.php`

Uses the **Standards Analyzer Agent** to generate age-appropriate educational standards.

```bash
curl -X POST "http://localhost:8081/api/admin/generate_standards.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "grade": "2nd Grade",
    "subject": "Science"
  }'
```

**Response:**
```json
{
  "success": true,
  "standards": [
    {
      "id": "S1",
      "statement": "Students will understand the properties of matter...",
      "skills": ["Identify states of matter", "Compare properties"]
    }
  ],
  "count": 8,
  "metadata": {
    "subject": "Science",
    "grade": "2nd Grade",
    "generated_at": "2025-12-06 10:00:00"
  }
}
```

---

### 3. Approve Standards

**Endpoint:** `POST /api/admin/approve_standards.php`

```bash
curl -X POST "http://localhost:8081/api/admin/approve_standards.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "draftId": 10,
    "standards": [
      {"code": "S1", "description": "Students will understand..."}
    ]
  }'
```

---

### 4. Generate Outline

**Endpoint:** `POST /api/admin/generate_draft_outline.php`

Uses the **Outline Generator Agent** to organize standards into units and lessons.

```bash
curl -X POST "http://localhost:8081/api/admin/generate_draft_outline.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"draftId": 10}'
```

**Response:**
```json
{
  "success": true,
  "outline": [
    {
      "title": "Unit 1: Matter and Materials",
      "lessons": [
        {"title": "States of Matter", "description": "..."}
      ]
    }
  ]
}
```

---

### 5. Generate Lesson Content

**Endpoint:** `POST /api/admin/generate_lesson_content.php`

Uses the **Content Creator Agent** to generate age-appropriate lesson content.

```bash
curl -X POST "http://localhost:8081/api/admin/generate_lesson_content.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "draftId": 10,
    "unitIndex": 0,
    "lessonIndex": 0,
    "lessonTitle": "States of Matter",
    "query": "States of matter for 2nd grade students",
    "source": "generate"
  }'
```

**Response:**
```json
{
  "success": true,
  "content": "# States of Matter\n\nMatter is everything around us...",
  "source": "ai_generated"
}
```

---

### 6. Generate Questions

**Endpoint:** `POST /api/admin/generate_lesson_questions.php`

Uses the **Question Generator Agent** to create assessment questions.

```bash
curl -X POST "http://localhost:8081/api/admin/generate_lesson_questions.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "draftId": 10,
    "unitIndex": 0,
    "lessonIndex": 0,
    "lessonContent": "States of matter content...",
    "questionType": "multiple_choice",
    "count": 5
  }'
```

---

### 7. Publish Course

**Endpoint:** `POST /api/admin/publish_course.php`

```bash
curl -X POST "http://localhost:8081/api/admin/publish_course.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"draftId": 10}'
```

---

## System Agents

The course generation pipeline uses these specialized AI agents:

| Agent | Purpose | Agent ID |
|-------|---------|----------|
| Standards Analyzer | Generate educational standards | 15 |
| Outline Generator | Create course structure | 16 |
| Content Creator | Generate lesson content | 17 |
| Question Generator | Create assessment questions | 18 |
| Content Validator | QA validation | 21 |

Configure agents at: **Admin Dashboard → System Agents**

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `course_drafts` | Course metadata and status |
| `approved_standards` | Standards for each draft |
| `course_outlines` | Unit/lesson structure |
| `draft_lessons` | Generated lesson content |
| `question_banks` | Generated questions |

---

## Error Handling

Common errors and solutions:

| Error | Cause | Solution |
|-------|-------|----------|
| "Agent did not respond" | LLM service down | Check `curl http://localhost:8090/health` |
| "Could not parse standards" | LLM output format | Retry - small LLMs sometimes fail JSON |
| "Draft not found" | Invalid draftId | Check draft exists in database |
| "Authentication required" | Missing/expired token | Re-login to get new JWT |

---

## See Also

- [COURSE_GENERATION_ARCHITECTURE.md](COURSE_GENERATION_ARCHITECTURE.md) - Detailed 5-agent architecture
- [System Agents Admin UI](../admin_system_agents.html) - Configure agent prompts
- [Course Management UI](../admin_courses.html) - Visual course builder

---

*Last updated: December 6, 2025*
