# Full Course Generator Mode API Documentation

## Overview

The Full Course Generator Mode automatically generates all lesson content for an entire course from an approved course outline. This is the most comprehensive generation mode, capable of producing complete courses with dozens of lessons in a single operation.

**Endpoint:** `POST /api/admin/generate_full_course.php`

**Authentication:** Requires admin JWT token in `Authorization: Bearer <token>` header

**Typical Duration:** 10-30 minutes for a 30-lesson course

**Use Case:** When you have an approved course outline and want to generate all lesson content automatically with robust error handling and progress tracking.

---

## Generation Workflow

```
1. Load approved course outline from file
   └─> Verify file exists and contains valid metadata

2. For each unit in course:
   └─> For each lesson in unit:
       ├─> Skip if already generated (not "outline" status)
       ├─> Skip if before startUnit/startLesson (for resume)
       ├─> Build detailed prompt with context
       ├─> Call Course Design Agent (120s timeout)
       ├─> Extract and parse JSON response
       ├─> Validate against lesson schema
       ├─> Save to course metadata file
       ├─> Track progress with detailed status
       ├─> Periodic save every 5 lessons
       └─> Break if validation fails and pauseOnFailure=true

3. Final save of complete course metadata

4. Return comprehensive results with:
   - Success/failure status
   - Total/processed/successful/failed counts
   - Progress array with details per lesson
   - Generation time and averages
   - Backup path
```

---

## Request Parameters

### Required

- **`courseId`** (string): Course file identifier (without .json extension)
  - Example: `"algebra_1"`, `"biology_101"`
  - Must correspond to an existing course file in `api/course/courses/`

### Optional

- **`startUnit`** (integer, default: `1`): Unit number to start/resume from
  - Use for resuming after interruption
  - Will skip all units before this number
  - Example: `3` (resume from Unit 3)

- **`startLesson`** (integer, default: `1`): Lesson number to start from within startUnit
  - Only applies to the startUnit
  - Example: `{"startUnit": 3, "startLesson": 2}` resumes at Unit 3, Lesson 2

- **`pauseOnFailure`** (boolean, default: `true`): Stop generation on first failure
  - `true`: Stop immediately when any lesson fails validation/generation
  - `false`: Continue generating remaining lessons, track all failures
  - Recommended: `true` for initial run, `false` for bulk generation

- **`createBackup`** (boolean, default: `true`): Create backup before generation
  - `true`: Saves backup to `api/course/backups/` before starting
  - `false`: No backup created (not recommended)
  - Backup filename: `course_{courseId}_backup_{timestamp}.json`

- **`difficulty`** (string, optional): Override lesson difficulty
  - Values: `"easy"`, `"medium"`, `"hard"`
  - If not specified, uses difficulty from outline or course metadata
  - Applies to all generated lessons

---

## Example Requests

### Basic: Generate Full Course

```bash
curl -X POST "http://localhost/basic_educational/api/admin/generate_full_course.php" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_1"
  }'
```

### Resume from Specific Point

```bash
curl -X POST "http://localhost/basic_educational/api/admin/generate_full_course.php" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_1",
    "startUnit": 3,
    "startLesson": 4,
    "pauseOnFailure": false
  }'
```

### Continue Through Failures

```bash
curl -X POST "http://localhost/basic_educational/api/admin/generate_full_course.php" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_1",
    "pauseOnFailure": false,
    "createBackup": false
  }'
```

---

## Response Format

### Success Response

```json
{
  "success": true,
  "courseId": "algebra_1",
  "courseName": "Algebra I - High School",
  "totalUnits": 6,
  "totalLessons": 30,
  "processedLessons": 30,
  "successfulLessons": 28,
  "failedLessons": 2,
  "completed": true,
  "stoppedEarly": false,
  "progress": [
    {
      "unit": 1,
      "lesson": 1,
      "title": "Variables and Constants",
      "status": "success",
      "action": "inserted",
      "time": 45.2
    },
    {
      "unit": 1,
      "lesson": 2,
      "title": "Evaluating Expressions",
      "status": "success",
      "action": "inserted",
      "time": 52.8
    },
    {
      "unit": 3,
      "lesson": 2,
      "title": "Problem Solving Strategies",
      "status": "failed",
      "error": "Validation failed: missing explanation field",
      "time": 12.3
    }
  ],
  "failures": [
    {
      "unit": 3,
      "lesson": 2,
      "title": "Problem Solving Strategies",
      "error": "Validation failed: missing explanation field",
      "time": 12.3
    },
    {
      "unit": 5,
      "lesson": 4,
      "title": "Advanced Factoring",
      "error": "Agent timeout after 120 seconds",
      "time": 120.0
    }
  ],
  "generationTime": 1567.8,
  "averageTimePerLesson": 52.3,
  "backupPath": "/var/www/html/basic_educational/api/course/backups/course_algebra_1_backup_20240115_143022.json",
  "message": "Successfully generated 28 out of 30 lessons for Algebra I - High School"
}
```

### Response Fields

- **`success`** (boolean): Overall operation success
- **`courseId`** (string): Course identifier
- **`courseName`** (string): Full course name from metadata
- **`totalUnits`** (integer): Number of units in course
- **`totalLessons`** (integer): Total lessons across all units
- **`processedLessons`** (integer): Lessons attempted (not skipped)
- **`successfulLessons`** (integer): Lessons generated and saved successfully
- **`failedLessons`** (integer): Lessons that failed generation/validation
- **`completed`** (boolean): `true` if all lessons processed
- **`stoppedEarly`** (boolean): `true` if stopped due to failure with pauseOnFailure=true
- **`progress`** (array): Detailed status for each processed lesson
  - `unit` (integer): Unit number
  - `lesson` (integer): Lesson number
  - `title` (string): Lesson title
  - `status` (string): `"success"`, `"failed"`, or `"skipped"`
  - `action` (string): `"inserted"` or `"updated"` (for success)
  - `error` (string): Error message (for failed)
  - `time` (float): Processing time in seconds
  - `validationWarnings` (array, optional): Non-critical validation warnings
- **`failures`** (array): Array of failed lessons with details
- **`generationTime`** (float): Total operation time in seconds
- **`averageTimePerLesson`** (float): Average generation time per lesson
- **`backupPath`** (string): Path to backup file (if createBackup=true)
- **`message`** (string): Human-readable result summary

### Error Response

```json
{
  "success": false,
  "error": "Course file not found: course_algebra_1.json"
}
```

---

## Lesson Generation Process

### Per-Lesson Workflow

1. **Context Building**
   - Course subject and level
   - Unit number, title, and standards
   - Lesson number, title, and description
   - Prerequisites from previous lessons
   - Specified difficulty level

2. **Prompt Construction**
   - Comprehensive prompt with all context
   - Standards alignment requirements
   - Schema compliance instructions
   - Examples and quality guidelines

3. **Agent Call**
   - POST to agent service at localhost:8080
   - 120-second timeout per lesson
   - Response parsing and JSON extraction

4. **Validation**
   - Check all 9 required fields:
     - objectives (array, min 2 items)
     - explanation (string, min 100 chars)
     - guidedExamples (array, min 1 item)
     - practiceProblems (array, min 3 items)
     - quiz (array, min 3 items)
     - summary (string, min 50 chars)
     - vocabularyTerms (array)
     - estimatedDuration (string)
     - videoUrl (string, can be empty)
   - Validate structure of nested objects
   - Continue on warnings if critical fields present
   - Fail on missing critical fields (objectives, explanation)

5. **Saving**
   - Insert into course metadata via `insertLesson()`
   - Automatic ordering within unit
   - Update or insert based on existing lesson
   - Periodic disk writes every 5 lessons

6. **Progress Tracking**
   - Add entry to progress array
   - Track time, status, action
   - Log validation warnings if present
   - Count successes/failures

---

## Resume Capability

### When to Resume

- **Power failure**: Server/system interrupted during generation
- **Timeout**: Network issues causing long delays
- **Validation failure**: Stopped early with pauseOnFailure=true
- **Manual stop**: Intentionally stopped to review progress

### How to Resume

**Scenario 1: Resume from last completed lesson**

```json
{
  "courseId": "algebra_1",
  "startUnit": 3,
  "startLesson": 5
}
```

This will skip Units 1-2 entirely, and skip Lessons 1-4 in Unit 3, starting fresh from Unit 3 Lesson 5.

**Scenario 2: Resume and continue through failures**

```json
{
  "courseId": "algebra_1",
  "startUnit": 2,
  "startLesson": 1,
  "pauseOnFailure": false
}
```

This will process all remaining lessons, tracking failures but not stopping.

### Skip Logic

- **Already-generated lessons**: Skipped automatically if status is NOT "outline"
- **Before startUnit**: All units with `unitNumber < startUnit` skipped
- **Before startLesson**: In startUnit, all lessons with `lessonNumber < startLesson` skipped

---

## Safety Features

### 1. Backup Creation

- Automatic backup before generation starts (unless `createBackup: false`)
- Backup includes complete course metadata with all existing content
- Stored in `api/course/backups/` with timestamp
- Allows rollback if generation produces poor results

### 2. Validation Gates

- **Schema validation**: 9 required fields checked before save
- **Critical field check**: Must have objectives and explanation
- **Type validation**: Arrays, strings, proper structure
- **Warning tolerance**: Continues with warnings if critical fields present
- **Failure stop**: Throws exception on missing critical fields

### 3. Periodic Saves

- Course file saved to disk every 5 lessons
- Prevents data loss on timeout/crash
- Progress preserved even if operation interrupted
- Resume-friendly (already-generated lessons skipped)

### 4. Skip Already-Generated

- Checks lesson status before generation
- Skips if status is not "outline" (has content)
- Prevents overwriting manually-edited lessons
- Safe for re-running on same course

### 5. Error Isolation

- Each lesson wrapped in try/catch
- Failure in one lesson doesn't stop others (if pauseOnFailure=false)
- Detailed error messages per failure
- Comprehensive failure tracking

### 6. Timeout Management

- 30-minute overall timeout (PHP execution limit)
- 120-second timeout per lesson (agent call)
- Prevents hung processes
- Returns partial results on timeout

---

## Performance Expectations

### Timing

- **Per lesson**: 30-60 seconds average
- **30-lesson course**: 15-30 minutes total
- **Factors affecting speed**:
  - Model size and GPU availability
  - Lesson complexity and standards
  - Server load and concurrent requests
  - Network latency to agent service

### Resource Usage

- **Memory**: ~500MB for course metadata + agent overhead
- **CPU**: Delegated to agent service (llama-server)
- **Disk**: Minimal (periodic JSON writes)
- **Network**: HTTP requests to localhost:8080

### Scaling Considerations

- **Single-threaded**: Processes lessons sequentially
- **Not parallelizable**: Agent service processes one request at a time
- **For multiple courses**: Run separate requests (different courseIds)
- **Production**: Consider queue system for multiple concurrent requests

---

## Comparison to Other Generation Modes

### Single Lesson Mode

**Endpoint:** `generate_lesson.php`

**Use Case:** Generate one lesson at a time with manual review

**Speed:** 30-60 seconds per lesson

**When to use:**
- Manual quality control needed
- Specific lesson regeneration
- Testing new prompts
- Low lesson count (1-3)

### Unit Generator Mode

**Endpoint:** `generate_unit.php`

**Use Case:** Generate 3-10 lessons for a single unit

**Speed:** 2-5 minutes for 5 lessons

**When to use:**
- One unit at a time approach
- Higher manual oversight
- Testing before full generation
- Incremental content development

### Course Outline Mode

**Endpoint:** `generate_course_outline.php`

**Use Case:** Generate course structure without content

**Speed:** 10-20 seconds

**When to use:**
- **ALWAYS before full course generation**
- Validate course structure
- Get approval on units/lessons
- Avoid wasted generation time

### Full Course Generator Mode (This API)

**Endpoint:** `generate_full_course.php`

**Use Case:** Generate all lessons from approved outline

**Speed:** 10-30 minutes for full course

**When to use:**
- Outline already approved
- Need complete course quickly
- Acceptable to review content after generation
- Robust error handling needed

---

## Recommended Workflow

### Complete Course Creation Process

```
Step 1: Generate Course Outline (10-20 seconds)
├─> POST /api/admin/generate_course_outline.php
├─> Review unit/lesson structure
└─> Approve or regenerate

Step 2: Save Approved Outline (instant)
├─> POST /api/admin/save_course_outline.php
└─> Creates course file with "outline" status lessons

Step 3: Generate Full Course (10-30 minutes)
├─> POST /api/admin/generate_full_course.php
├─> Monitor progress (optional polling endpoint)
└─> Review completion results

Step 4: Review and Refine (manual)
├─> Check failed lessons in response
├─> Review generated content quality
├─> Regenerate specific lessons if needed (generate_lesson.php)
└─> Make manual edits as needed

Step 5: Publish (manual)
└─> Set course status to "active" when ready
```

---

## Error Handling

### Common Errors

**1. Course file not found**
```json
{
  "success": false,
  "error": "Course file not found: course_algebra_1.json"
}
```
**Solution:** Verify courseId is correct and course outline has been saved.

**2. No outline lessons found**
```json
{
  "success": false,
  "error": "No lessons found with 'outline' status in course"
}
```
**Solution:** Course already fully generated, or outline not properly saved.

**3. Agent service timeout**
```json
{
  "progress": [{
    "status": "failed",
    "error": "Agent call timed out after 120 seconds"
  }]
}
```
**Solution:** Check agent service health, increase timeout, or retry specific lesson.

**4. Validation failure**
```json
{
  "progress": [{
    "status": "failed",
    "error": "Validation failed: missing objectives field"
  }]
}
```
**Solution:** Review agent prompts, check agent service logs, regenerate failed lesson.

**5. PHP timeout (30 minutes)**
```json
{
  "success": false,
  "error": "Maximum execution time exceeded"
}
```
**Solution:** Resume from last successful lesson using startUnit/startLesson parameters.

### Debugging

**Check agent service logs:**
```bash
tail -f /tmp/agent_service_full.log
```

**Check llama-server logs:**
```bash
tail -f /tmp/llama_server.log
```

**Verify agent service health:**
```bash
curl http://localhost:8080/health
```

**Review course metadata file:**
```bash
cat api/course/courses/course_algebra_1.json | jq '.units[].lessons[] | {unit, lesson, title, status}'
```

---

## Best Practices

### Before Generation

1. **Generate and approve outline first** - Always use course outline mode before full generation
2. **Verify agent service running** - Check health endpoint before starting
3. **Ensure sufficient time** - Don't start if server will restart/shutdown soon
4. **Create backup** - Leave `createBackup: true` for production courses

### During Generation

1. **Monitor progress** - Check agent service logs for issues
2. **Don't interrupt** - Let it complete or fail naturally for proper state
3. **Check available space** - Ensure sufficient disk space for course file

### After Generation

1. **Review progress array** - Check all lesson statuses
2. **Inspect failures** - Read error messages for patterns
3. **Regenerate failures** - Use single lesson mode for failed lessons
4. **Quality check content** - Review sample lessons for quality
5. **Verify course file** - Ensure JSON is valid and complete

### Production Deployment

1. **Run as background job** - Use nohup or process manager
2. **Implement progress polling** - Create status endpoint for long operations
3. **Add notifications** - Email/Slack when generation completes
4. **Queue multiple requests** - Don't run concurrent full course generations
5. **Monitor resources** - Watch memory/CPU during generation

---

## Technical Details

### File Locations

- **Course files:** `/var/www/html/basic_educational/api/course/courses/course_{courseId}.json`
- **Backups:** `/var/www/html/basic_educational/api/course/backups/`
- **API endpoint:** `/var/www/html/basic_educational/api/admin/generate_full_course.php`

### Dependencies

- **CourseMetadata.php:** Course file management class
- **LessonSchema.js:** Validation schema (9 required fields)
- **Agent service:** localhost:8080 (C++ microservice)
- **llama-server:** localhost:8090 (LLM inference)
- **PHP extensions:** json, curl

### Agent Service Integration

**Endpoint:** `http://localhost:8080/agent/chat`

**Request:**
```json
{
  "userId": 1,
  "agentId": 2,
  "message": "{detailed prompt with lesson context}"
}
```

**Response:**
```json
{
  "success": true,
  "response": "{full lesson JSON or markdown-wrapped JSON}"
}
```

**Timeout:** 120 seconds per request

---

## Support

### Related Documentation

- `ADVISOR_INSTANCE_API.md` - Advisor system architecture
- `AGENT_FACTORY_GUIDE.md` - Creating custom agents
- `CONTENT_EXTRACTION_GUIDE.md` - Scraping external content
- `MEMORY_POLICY_QUICKREF.md` - Agent memory management

### Troubleshooting

See `AGENT_TROUBLESHOOTING_LOG.md` for common issues and solutions.

### Testing

Run the test script to verify functionality:
```bash
cd tests
chmod +x test_full_course_generator.sh
./test_full_course_generator.sh
```

---

## Version History

- **v1.0** (2024-01-15): Initial implementation with full course generation, resume capability, progress tracking, and robust error handling
