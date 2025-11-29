# Complete Course Generation Pipeline - Quick Start Guide

## 🎯 Overview

This guide shows the complete workflow for generating a full course from scratch using the Professor Hawkeinstein platform's four generation modes.

**Total Time:** 15-35 minutes for a 30-lesson course

---

## 📋 Prerequisites

1. **Admin JWT Token** - Login to admin panel and get token from sessionStorage
2. **Agent Services Running** - Both llama-server (8090) and agent_service (8080) must be active
3. **Course Requirements** - Subject, level, standards (optional)

### Check Services

```bash
# Check agent service
curl http://localhost:8080/health
# Expected: {"status":"ok"}

# Check llama-server  
curl http://localhost:8090/health
# Expected: {"status":"ok"}
```

---

## 🚀 Complete Workflow

### Mode 1: Course Outline Generation (10-20 seconds)

**Purpose:** Generate course structure (units, lessons, titles, standards) WITHOUT content

**Endpoint:** `POST /api/admin/generate_course_outline.php`

```bash
TOKEN="your_jwt_token_here"

curl -X POST "http://localhost/basic_educational/api/admin/generate_course_outline.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Algebra I",
    "level": "High School",
    "numUnits": 6,
    "lessonsPerUnit": 5,
    "standards": ["CCSS.MATH.HSA"]
  }' | jq '.' > outline_response.json
```

**Output:** JSON outline with:
- 6 units, each with title and description
- 5 lessons per unit (30 total)
- Each lesson has: title, description, standards, prerequisites
- All lesson content fields are EMPTY (status: "outline")

**Review:** Check `outline_response.json` and verify:
- Unit titles and progression make sense
- Lesson titles are appropriate
- Prerequisites flow logically
- Standards are correctly mapped

---

### Mode 2: Save Approved Outline (instant)

**Purpose:** Create course metadata file with outline structure

**Endpoint:** `POST /api/admin/save_course_outline.php`

```bash
# Extract outline from response
OUTLINE=$(cat outline_response.json | jq '.outline')

# Save to course file
curl -X POST "http://localhost/basic_educational/api/admin/save_course_outline.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"courseId\": \"algebra_1_v1\",
    \"outline\": $OUTLINE,
    \"overwrite\": false
  }" | jq '.'
```

**Output:**
```json
{
  "success": true,
  "message": "Course outline saved successfully",
  "courseFile": "course_algebra_1_v1.json",
  "totalUnits": 6,
  "totalLessons": 30
}
```

**Verify:** Course file created at `api/course/courses/course_algebra_1_v1.json`

---

### Mode 3: Full Course Generation (15-30 minutes)

**Purpose:** Generate ALL lesson content from approved outline

**Endpoint:** `POST /api/admin/generate_full_course.php`

```bash
# Start generation (this will take 15-30 minutes)
curl -X POST "http://localhost/basic_educational/api/admin/generate_full_course.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_1_v1",
    "pauseOnFailure": true,
    "createBackup": true
  }' | jq '.' > generation_results.json

# Monitor progress in real-time (separate terminal)
tail -f /tmp/agent_service_full.log
```

**Progress Tracking:**
The API returns detailed progress for each lesson:

```json
{
  "success": true,
  "courseName": "Algebra I - High School",
  "totalLessons": 30,
  "processedLessons": 30,
  "successfulLessons": 28,
  "failedLessons": 2,
  "completed": true,
  "progress": [
    {
      "unit": 1,
      "lesson": 1,
      "title": "Variables and Constants",
      "status": "success",
      "time": 45.2
    },
    {
      "unit": 3,
      "lesson": 4,
      "title": "Problem Solving",
      "status": "failed",
      "error": "Validation failed: missing explanation",
      "time": 12.3
    }
  ],
  "generationTime": 1567.8,
  "averageTimePerLesson": 52.3
}
```

**What Happens:**
1. Creates backup: `api/course/backups/course_algebra_1_v1_backup_20240115_143022.json`
2. Loops through all 6 units
3. For each unit, loops through all 5 lessons
4. For each lesson:
   - Builds detailed prompt with context
   - Calls agent service (120s timeout)
   - Extracts and validates JSON
   - Saves to course metadata
   - Tracks status and time
5. Saves to disk every 5 lessons
6. Returns comprehensive results

---

### Mode 3.5: Resume if Interrupted (optional)

**Purpose:** Continue generation from last successful lesson if interrupted

```bash
# Resume from Unit 3, Lesson 5
curl -X POST "http://localhost/basic_educational/api/admin/generate_full_course.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_1_v1",
    "startUnit": 3,
    "startLesson": 5,
    "pauseOnFailure": false,
    "createBackup": false
  }' | jq '.'
```

**Skip Logic:**
- Skips all units before Unit 3
- Skips Lessons 1-4 in Unit 3
- Starts fresh from Unit 3, Lesson 5
- Automatically skips already-generated lessons

---

### Mode 4: Regenerate Failed Lessons (as needed)

**Purpose:** Fix specific failed lessons individually

**Endpoint:** `POST /api/admin/generate_lesson.php`

```bash
# Regenerate Unit 3, Lesson 4 that failed
curl -X POST "http://localhost/basic_educational/api/admin/generate_lesson.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Algebra I",
    "level": "High School",
    "topic": "Problem Solving Strategies",
    "standards": ["CCSS.MATH.HSA.REI.A.1"],
    "unit": 3,
    "lesson": 4,
    "difficulty": "medium"
  }' | jq '.' > lesson_3_4.json

# Save the regenerated lesson
curl -X POST "http://localhost/basic_educational/api/admin/save_lesson.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"courseId\": \"algebra_1_v1\",
    \"unitNumber\": 3,
    \"lesson\": $(cat lesson_3_4.json | jq '.lesson')
  }" | jq '.'
```

---

## 📊 Complete Example Session

```bash
#!/bin/bash
# Complete course generation workflow

TOKEN="your_jwt_token_here"
BASE_URL="http://localhost/basic_educational/api/admin"

echo "=== Step 1: Generate Course Outline ==="
curl -s -X POST "$BASE_URL/generate_course_outline.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Algebra I",
    "level": "High School",
    "numUnits": 6,
    "lessonsPerUnit": 5
  }' > outline.json

echo "Outline generated. Review outline.json..."
cat outline.json | jq '.outline.units[].unitTitle'
read -p "Press enter to continue..."

echo ""
echo "=== Step 2: Save Approved Outline ==="
OUTLINE=$(cat outline.json | jq '.outline')
curl -s -X POST "$BASE_URL/save_course_outline.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"courseId\": \"algebra_1_demo\",
    \"outline\": $OUTLINE
  }" | jq '.'

echo ""
echo "=== Step 3: Generate Full Course (15-30 min) ==="
echo "Starting generation... Monitor logs: tail -f /tmp/agent_service_full.log"
curl -s -X POST "$BASE_URL/generate_full_course.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_1_demo",
    "pauseOnFailure": false
  }' > results.json

echo ""
echo "=== Generation Complete ==="
cat results.json | jq '{
  success: .success,
  courseName: .courseName,
  totalLessons: .totalLessons,
  successful: .successfulLessons,
  failed: .failedLessons,
  time: .generationTime
}'

echo ""
echo "Failed lessons:"
cat results.json | jq '.failures[]'

echo ""
echo "Course file: api/course/courses/course_algebra_1_demo.json"
```

---

## 🎓 Generation Mode Comparison

| Mode | Endpoint | Time | Output | Use When |
|------|----------|------|--------|----------|
| **Outline** | generate_course_outline.php | 10-20s | Structure only | Planning, approval |
| **Single Lesson** | generate_lesson.php | 30-60s | 1 lesson | Fixing failures |
| **Unit** | generate_unit.php | 2-5min | 3-10 lessons | One unit at a time |
| **Full Course** | generate_full_course.php | 15-30min | All lessons | Automated bulk |

---

## ✅ Verification Steps

### 1. Check Course File

```bash
# View all lessons with status
cat api/course/courses/course_algebra_1_v1.json | \
  jq '.units[].lessons[] | {unit, lesson, title, status}'

# Count lessons by status
cat api/course/courses/course_algebra_1_v1.json | \
  jq '[.units[].lessons[].status] | group_by(.) | map({status: .[0], count: length})'
```

### 2. Verify Lesson Content

```bash
# Check a specific lesson has all required fields
cat api/course/courses/course_algebra_1_v1.json | \
  jq '.units[0].lessons[0] | {
    hasObjectives: (.objectives | length > 0),
    hasExplanation: (.explanation | length > 100),
    hasExamples: (.guidedExamples | length > 0),
    hasProblems: (.practiceProblems | length > 0),
    hasQuiz: (.quiz | length > 0)
  }'
```

### 3. Review in Workbook

1. Open `workbook.html` in browser
2. Load course file: `api/course/courses/course_algebra_1_v1.json`
3. Navigate through lessons
4. Verify content renders correctly

---

## 🐛 Troubleshooting

### Issue: Outline generation fails

**Check:**
```bash
# Agent service healthy?
curl http://localhost:8080/health

# View logs
tail -f /tmp/agent_service_full.log
```

**Solution:** Restart agent service
```bash
cd /home/steve/Professor_Hawkeinstein
./start_services.sh
```

---

### Issue: Full course generation times out

**Symptom:** PHP execution time exceeded after 30 minutes

**Solution:** Resume from last completed lesson
```bash
# Check last successful lesson in results
cat generation_results.json | jq '.progress[-1]'

# Resume from next lesson
curl -X POST "$BASE_URL/generate_full_course.php" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "courseId": "algebra_1_v1",
    "startUnit": 4,
    "startLesson": 3
  }'
```

---

### Issue: Multiple lessons fail validation

**Symptom:** Many lessons show "Validation failed: missing explanation"

**Causes:**
1. Agent service running out of memory
2. Model too small for complex prompts
3. Prompt engineering issues

**Solutions:**
```bash
# 1. Restart services
./start_services.sh

# 2. Check available memory
free -h

# 3. Regenerate failed lessons individually
# Use generate_lesson.php for better control
```

---

### Issue: Lessons have poor quality content

**Solution:** Regenerate specific lessons with better prompts

```bash
# Edit the prompt in generate_lesson.php
# Add more examples, constraints, quality requirements

# Regenerate specific lesson
curl -X POST "$BASE_URL/generate_lesson.php" \
  -d '{
    "topic": "Quadratic Equations",
    "standards": ["CCSS.MATH.HSA.REI.B.4"],
    "difficulty": "hard",
    "prerequisites": ["Factoring", "Completing the Square"]
  }'
```

---

## 📚 Related Documentation

- **FULL_COURSE_GENERATOR_MODE_API.md** - Complete API reference (21 pages)
- **FULL_COURSE_GENERATOR_SUMMARY.md** - Implementation summary
- **test_full_course_generator.sh** - Automated test script

---

## 🎯 Best Practices

### Before Starting

1. ✅ **Generate outline first** - Always validate structure before content
2. ✅ **Review and approve** - Check unit/lesson progression
3. ✅ **Verify services** - Agent and llama-server healthy
4. ✅ **Allocate time** - 30+ minutes for full course

### During Generation

1. ✅ **Monitor logs** - Watch for errors in real-time
2. ✅ **Don't interrupt** - Let it complete naturally
3. ✅ **Check disk space** - Ensure room for course file

### After Generation

1. ✅ **Review results** - Check success/failure counts
2. ✅ **Regenerate failures** - Fix any failed lessons
3. ✅ **Quality check** - Review sample lessons
4. ✅ **Test in workbook** - Verify rendering

---

## 🚀 Quick Commands

```bash
# One-liner to check everything
curl -s http://localhost:8080/health && \
curl -s http://localhost:8090/health && \
echo "✅ Services ready!"

# Quick outline generation
curl -X POST http://localhost/basic_educational/api/admin/generate_course_outline.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"subject":"Algebra I","level":"High School"}' | jq '.'

# Quick full course generation (after outline saved)
curl -X POST http://localhost/basic_educational/api/admin/generate_full_course.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"courseId":"algebra_1"}' | jq '.'

# Check generation progress (while running)
tail -f /tmp/agent_service_full.log | grep "Lesson Generation"
```

---

**Status:** ✅ Complete pipeline implemented and tested

**Version:** 1.0 (2024-01-15)

**Total Time:** Structure planning (20s) + Full generation (15-30min) = ~30 minutes for complete 30-lesson course
