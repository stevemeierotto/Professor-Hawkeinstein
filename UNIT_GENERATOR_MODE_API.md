# Unit Generator Mode Documentation

## Overview

The **UNIT_GENERATOR_MODE** generates a complete unit with multiple lessons (typically 5) based on educational standards. Each lesson follows the lesson schema and is automatically saved to the course metadata structure.

## Endpoint

```
POST /api/admin/generate_unit.php
```

**Authentication:** Requires admin JWT token  
**Timeout:** 5 minutes (300 seconds)

## Use Case

Generate an entire unit of lessons at once instead of generating lessons one-by-one. This is ideal for:
- Quickly scaffolding a new unit
- Creating consistent lesson sequences
- Bulk content generation aligned to standards
- Building curriculum from standards-based frameworks

## Input Parameters

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `courseId` | string | Course identifier or filename |
| `subject` | string | Subject area (e.g., "Algebra", "Biology") |
| `level` | string | Grade/difficulty level (e.g., "High School") |
| `unitNumber` | integer | Target unit number (must exist in course) |
| `unitTitle` | string | Title for the unit |
| `standards` | array | Educational standards (array of objects or strings) |

### Optional Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `lessonCount` | integer | 5 | Number of lessons to generate (3-10) |
| `difficulty` | string | "intermediate" | Difficulty level for all lessons |
| `createBackup` | boolean | true | Create backup before saving |

## Standards Format

Standards can be provided as objects:
```json
{
  "code": "CCSS.MATH.HSA.SSE.A.1",
  "description": "Interpret expressions representing quantities"
}
```

Or as simple strings:
```json
"CCSS.MATH.HSA.SSE.A.1: Interpret expressions"
```

## Response Format

### Success Response (200 OK)

```json
{
  "success": true,
  "unitNumber": 1,
  "unitTitle": "Foundations of Algebra",
  "lessonsRequested": 5,
  "lessonsGenerated": 5,
  "lessonsSaved": 5,
  "lessons": [
    { /* Lesson 1 object */ },
    { /* Lesson 2 object */ },
    { /* Lesson 3 object */ },
    { /* Lesson 4 object */ },
    { /* Lesson 5 object */ }
  ],
  "savedLessons": [
    { "lessonNumber": 1, "action": "inserted", "title": "Variables and Constants" },
    { "lessonNumber": 2, "action": "inserted", "title": "Order of Operations" },
    { "lessonNumber": 3, "action": "inserted", "title": "Simplifying Expressions" },
    { "lessonNumber": 4, "action": "inserted", "title": "Combining Like Terms" },
    { "lessonNumber": 5, "action": "inserted", "title": "Distributive Property" }
  ],
  "failures": [],
  "generationTime": 245.67,
  "message": "Successfully generated and saved all 5 lessons for unit 1",
  "backupPath": "/path/to/backup_2025-11-28_16-30-00.json"
}
```

### Partial Success Response (200 OK)

When some lessons fail but at least 60% succeed:

```json
{
  "success": true,
  "unitNumber": 1,
  "unitTitle": "Foundations of Algebra",
  "lessonsRequested": 5,
  "lessonsGenerated": 4,
  "lessonsSaved": 4,
  "lessons": [ /* 4 lesson objects */ ],
  "savedLessons": [
    { "lessonNumber": 1, "action": "inserted", "title": "..." },
    { "lessonNumber": 2, "action": "inserted", "title": "..." },
    { "lessonNumber": 3, "action": "inserted", "title": "..." },
    { "lessonNumber": 5, "action": "inserted", "title": "..." }
  ],
  "failures": [
    {
      "lessonNumber": 4,
      "error": "Failed to parse lesson JSON from agent response",
      "stage": "parsing"
    }
  ],
  "generationTime": 198.45,
  "message": "Generated 4 of 5 lessons. Some failures occurred."
}
```

### Error Responses

#### 400 Bad Request
```json
{
  "success": false,
  "error": "Missing required field: unitTitle"
}
```

#### 404 Not Found
```json
{
  "success": false,
  "error": "Unit 3 not found in course",
  "availableUnits": [1, 2, 4, 5]
}
```

## Generation Process

1. **Validation**: Validates all inputs and checks course/unit exist
2. **Backup**: Creates backup of course file (if requested)
3. **Sequential Generation**: Generates lessons 1 through N:
   - Builds context-aware prompt for each lesson
   - Calls Course Design Agent via agent service
   - Parses JSON response
   - Validates against lesson schema
   - Saves to course metadata in-memory
4. **File Save**: Writes all lessons to course file at once
5. **Activity Logging**: Logs the unit generation event

## Lesson Context

Each lesson is generated with awareness of:
- Its position in the unit (lesson X of N)
- The unit title and standards
- The need to build on previous lessons
- The need to prepare for upcoming lessons

This creates a coherent progression through the unit.

## Validation

Each generated lesson is validated for:
- Required fields present
- Correct data types
- Non-empty arrays where required
- Proper numeric values

Lessons failing validation are:
- Logged in `failures` array
- Skipped if missing critical fields
- Saved anyway if only minor issues (with warnings)

## Example Usage

### JavaScript/Fetch

```javascript
async function generateUnit(courseId, unitNumber, unitTitle, standards) {
  const token = sessionStorage.getItem('admin_token');
  
  const response = await fetch('/api/admin/generate_unit.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      courseId: courseId,
      subject: 'Algebra',
      level: 'High School',
      unitNumber: unitNumber,
      unitTitle: unitTitle,
      standards: standards,
      lessonCount: 5,
      difficulty: 'beginner'
    })
  });
  
  const result = await response.json();
  
  if (result.success) {
    console.log(`Generated ${result.lessonsSaved} lessons in ${result.generationTime}s`);
    
    // Display lessons
    result.savedLessons.forEach(lesson => {
      console.log(`  ${lesson.lessonNumber}. ${lesson.title}`);
    });
    
    // Check for failures
    if (result.failures.length > 0) {
      console.warn('Some lessons failed:', result.failures);
    }
  } else {
    console.error('Generation failed:', result.error);
  }
  
  return result;
}

// Example usage
const standards = [
  {
    code: 'CCSS.MATH.HSA.SSE.A.1',
    description: 'Interpret expressions representing quantities'
  },
  {
    code: 'CCSS.MATH.HSA.REI.A.1',
    description: 'Explain each step in solving equations'
  }
];

await generateUnit('algebra_fundamentals', 1, 'Foundations of Algebra', standards);
```

### cURL

```bash
curl -X POST http://localhost/basic_educational/api/admin/generate_unit.php \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_fundamentals",
    "subject": "Algebra",
    "level": "High School",
    "unitNumber": 1,
    "unitTitle": "Foundations of Algebra",
    "standards": [
      {
        "code": "CCSS.MATH.HSA.SSE.A.1",
        "description": "Interpret expressions"
      }
    ],
    "lessonCount": 5,
    "difficulty": "beginner"
  }'
```

## Performance

### Timing
- **Per lesson**: 30-60 seconds (depends on LLM speed)
- **5 lessons**: 2.5-5 minutes typical
- **10 lessons**: 5-10 minutes maximum

### Timeout
- Endpoint timeout: 5 minutes (300 seconds)
- Per-lesson timeout: 2 minutes (120 seconds)
- Total time depends on: model speed, lesson complexity, network latency

### Resource Usage
- Keeps course metadata in memory during generation
- Single file write at the end
- Backup created before starting
- Activity logged once at completion

## Error Handling

### Failure Stages

Failures can occur at different stages:

1. **`generation`**: Agent call failed
2. **`parsing`**: JSON extraction failed
3. **`validation`**: Schema validation failed
4. **`saving`**: Course metadata insertion failed
5. **`exception`**: Unexpected error

### Failure Threshold

- **60%+ success**: Returns `success: true` with warnings
- **<60% success**: Returns `success: false`
- **0% success**: Returns error without creating backup

### Partial Success Handling

When some lessons fail:
- Successfully generated lessons are still saved
- Failures are reported in `failures` array
- Admin can regenerate failed lessons individually
- Course remains in consistent state

## Best Practices

### 1. Start Small
```javascript
// Test with 3 lessons first
lessonCount: 3
```

### 2. Check Failures
```javascript
if (result.failures.length > 0) {
  // Handle failed lessons
  result.failures.forEach(failure => {
    console.log(`Lesson ${failure.lessonNumber}: ${failure.error}`);
  });
}
```

### 3. Regenerate Failed Lessons
```javascript
// Use single lesson generator for failures
for (const failure of result.failures) {
  await generateLesson({
    subject: 'Algebra',
    level: 'High School',
    unitNumber: 1,
    lessonNumber: failure.lessonNumber,
    standards: standards
  });
}
```

### 4. Monitor Progress
```javascript
// Show progress to user
console.log('Generating unit... this may take 3-5 minutes');
// Use WebSocket or polling for real-time updates (future enhancement)
```

## Testing

Run the test script:
```bash
cd tests
chmod +x test_unit_generator.sh
# Edit script to add your JWT token
./test_unit_generator.sh
```

The test will:
1. Generate 5 lessons for "Foundations of Algebra"
2. Save individual lesson JSON files
3. Display generation statistics
4. Show any failures
5. Report total time

## Comparison to Single Lesson Generation

| Feature | Single Lesson | Unit Generator |
|---------|---------------|----------------|
| Input | 1 lesson params | Unit params + count |
| Output | 1 lesson | 3-10 lessons |
| Time | 30-60s | 2-10 min |
| Context | None | Lesson sequence aware |
| Cohesion | Individual | Progressive unit |
| Use Case | One-off, tweaking | Bulk generation |

## Security

- ✅ **Admin authentication required**
- ✅ **Activity logging with admin ID**
- ✅ **Automatic backup before changes**
- ✅ **Validation before saving**
- ✅ **Path traversal protection**
- ✅ **Timeout limits prevent runaway generation**

## Future Enhancements

Planned improvements:
- Progress streaming via WebSocket
- Parallel lesson generation (with rate limiting)
- Template-based generation for consistency
- Retry logic for failed lessons
- Custom lesson ordering/numbering
- Prerequisite chain validation
