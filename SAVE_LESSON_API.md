# Save Lesson API Documentation

## Overview

The Save Lesson API provides a safe way to insert or update generated lessons into course metadata JSON files. It handles positioning lessons by number, creates backups, and ensures data integrity.

## Endpoint

```
POST /api/admin/save_lesson.php
```

**Authentication:** Requires admin JWT token

## Use Case

After generating a lesson using the Lesson Generator API (`generate_lesson.php`), use this endpoint to save the lesson into the appropriate course → unit → lesson slot.

## Request Format

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `courseId` | string | Course identifier or filename (e.g., "algebra_fundamentals" or "course_algebra.json") |
| `unitNumber` | integer | Target unit number (must exist in course) |
| `lesson` | object | Complete lesson object with `lessonNumber` field |

### Optional Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `createBackup` | boolean | true | Whether to create a backup before saving |

### Lesson Object Requirements

The `lesson` object **must** include:
- `lessonNumber` (integer): Position within the unit
- All other fields from the lesson schema (see `lesson_schema.json`)

## Behavior

### Insert vs Update

The API automatically determines whether to insert or update:

1. **Insert**: If no lesson with the same `lessonNumber` exists in the unit
   - Lessons are inserted in numerical order
   - Example: Adding lesson 3 will place it between lessons 2 and 4

2. **Update**: If a lesson with the same `lessonNumber` already exists
   - Existing lesson is completely replaced with new data
   - Other lessons in the unit remain unchanged

### Backup Creation

By default, a backup is created before any modification:
- Location: `api/course/backups/`
- Format: `course_name_backup_YYYY-MM-DD_HH-MM-SS.json`
- Backup path is returned in response

### Safety Features

- ✅ **Non-destructive**: Only affects the target lesson
- ✅ **Atomic operations**: Course file is only written if all validations pass
- ✅ **Backup before save**: Original state is preserved
- ✅ **Validation**: Checks unit exists, lesson has required fields
- ✅ **Activity logging**: All saves are logged for auditing

## Response Format

### Success Response (200 OK)

```json
{
  "success": true,
  "action": "inserted",
  "message": "Lesson 2 added to Unit 1 successfully",
  "unitNumber": 1,
  "lessonNumber": 2,
  "backupPath": "/path/to/backup_2025-11-28_15-30-00.json"
}
```

**Action values:**
- `"inserted"`: New lesson was added
- `"updated"`: Existing lesson was replaced

### Error Responses

#### 400 Bad Request - Missing Field
```json
{
  "success": false,
  "error": "Missing required field: unitNumber"
}
```

#### 400 Bad Request - Invalid Lesson
```json
{
  "success": false,
  "error": "Lesson object must include a lessonNumber field"
}
```

#### 404 Not Found - Course Not Found
```json
{
  "success": false,
  "error": "Course file not found: /path/to/course.json",
  "courseId": "algebra_fundamentals"
}
```

#### 404 Not Found - Unit Not Found
```json
{
  "success": false,
  "error": "Unit 5 not found in course",
  "availableUnits": [1, 2, 3, 4]
}
```

#### 500 Internal Server Error
```json
{
  "success": false,
  "error": "Failed to save course metadata file",
  "courseFile": "/path/to/course.json"
}
```

## Example Usage

### JavaScript/Fetch

```javascript
async function saveGeneratedLesson(courseId, unitNumber, lessonObject) {
  const token = sessionStorage.getItem('admin_token');
  
  const response = await fetch('/api/admin/save_lesson.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      courseId: courseId,
      unitNumber: unitNumber,
      lesson: lessonObject,
      createBackup: true
    })
  });
  
  const result = await response.json();
  
  if (result.success) {
    console.log(`${result.action === 'inserted' ? 'Added' : 'Updated'} lesson ${result.lessonNumber}`);
    console.log('Backup created at:', result.backupPath);
  } else {
    console.error('Save failed:', result.error);
  }
  
  return result;
}

// Example: Save a generated lesson
const generatedLesson = {
  lessonNumber: 3,
  lessonTitle: "Quadratic Formula",
  objectives: ["Master the quadratic formula", "Solve complex equations"],
  // ... rest of lesson schema fields
};

await saveGeneratedLesson('algebra_fundamentals', 2, generatedLesson);
```

### cURL

```bash
curl -X POST http://localhost/basic_educational/api/admin/save_lesson.php \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "courseId": "algebra_fundamentals",
    "unitNumber": 1,
    "lesson": {
      "lessonNumber": 2,
      "lessonTitle": "Order of Operations",
      "objectives": ["Apply PEMDAS correctly"],
      "explanation": "...",
      "guidedExamples": [],
      "practiceProblems": [],
      "quizQuestions": [],
      "videoPlaceholder": "Order of Operations Video",
      "summary": "Always follow PEMDAS...",
      "estimatedDuration": 30,
      "difficulty": "beginner"
    },
    "createBackup": true
  }'
```

### PHP

```php
<?php
require_once 'config/database.php';

$lessonData = [
    'courseId' => 'algebra_fundamentals',
    'unitNumber' => 1,
    'createBackup' => true,
    'lesson' => [
        'lessonNumber' => 2,
        'lessonTitle' => 'Order of Operations',
        'objectives' => ['Apply PEMDAS correctly'],
        'explanation' => '...',
        'guidedExamples' => [],
        'practiceProblems' => [],
        'quizQuestions' => [],
        'videoPlaceholder' => 'Order of Operations Video',
        'summary' => 'Always follow PEMDAS...',
        'estimatedDuration' => 30,
        'difficulty' => 'beginner'
    ]
];

$ch = curl_init('http://localhost/basic_educational/api/admin/save_lesson.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lessonData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $_SESSION['admin_token'],
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Lesson saved: {$result['action']}\n";
    echo "Backup: {$result['backupPath']}\n";
}
?>
```

## Workflow Integration

### Typical Admin Workflow

1. **Generate lesson** using `generate_lesson.php`
2. **Admin reviews** the generated content
3. **Admin approves** and saves using `save_lesson.php`
4. **Lesson is inserted** into course metadata
5. **Backup is created** automatically
6. **Students can access** the lesson via workbook

### Combined Generation + Save

```javascript
// Generate and save workflow
async function generateAndSaveLesson(params) {
  // Step 1: Generate lesson
  const generateResponse = await fetch('/api/admin/generate_lesson.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      subject: params.subject,
      level: params.level,
      unitNumber: params.unitNumber,
      lessonNumber: params.lessonNumber,
      standards: params.standards
    })
  });
  
  const generated = await generateResponse.json();
  
  if (!generated.success) {
    throw new Error('Generation failed: ' + generated.error);
  }
  
  // Step 2: Show to admin for approval (in UI)
  const approved = await showApprovalDialog(generated.lesson);
  
  if (!approved) {
    return { cancelled: true };
  }
  
  // Step 3: Save approved lesson
  const saveResponse = await fetch('/api/admin/save_lesson.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      courseId: params.courseId,
      unitNumber: params.unitNumber,
      lesson: generated.lesson
    })
  });
  
  return await saveResponse.json();
}
```

## File Structure

### Course Directory
```
api/course/courses/
├── course_algebra_fundamentals.json
├── course_geometry_basics.json
└── course_calculus_intro.json
```

### Backup Directory
```
api/course/backups/
├── course_algebra_fundamentals_backup_2025-11-28_14-30-00.json
├── course_algebra_fundamentals_backup_2025-11-28_15-45-00.json
└── course_geometry_basics_backup_2025-11-28_16-00-00.json
```

## Error Handling

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Course not found | Invalid courseId or file doesn't exist | Check courseId, ensure file exists in `api/course/courses/` |
| Unit not found | Invalid unitNumber | Check available units in error response |
| Missing lessonNumber | Lesson object incomplete | Ensure lesson has `lessonNumber` field |
| Write permission error | Directory not writable | Check permissions on `courses/` and `backups/` directories |

### Debugging

Check server error log for detailed error messages:
```bash
tail -f /var/log/apache2/error.log | grep "Save Lesson"
```

## Testing

Run the test script:
```bash
cd tests
chmod +x test_save_lesson.sh
# Edit script to add your JWT token
./test_save_lesson.sh
```

The test script verifies:
1. ✅ Insert new lesson
2. ✅ Update existing lesson
3. ✅ Insert lesson in middle of sequence
4. ✅ Error handling for invalid unit

## Security

- ✅ **Admin authentication required**: Only admins can save lessons
- ✅ **Activity logging**: All saves are logged with admin ID
- ✅ **Backup creation**: Original state preserved before changes
- ✅ **Path traversal protection**: `basename()` used to prevent directory traversal
- ✅ **Validation**: All inputs validated before processing

## Performance

- **Typical response time**: 50-200ms
- **File size considerations**: Course files typically 50-500KB
- **Concurrent access**: Not recommended - use transaction locks if needed
- **Backup storage**: Monitor backup directory size, implement cleanup policy

## Future Enhancements

Planned improvements:
- Bulk lesson insertion
- Lesson reordering within units
- Lesson duplication
- Rollback to specific backup
- Conflict resolution for concurrent edits
- Version control integration
