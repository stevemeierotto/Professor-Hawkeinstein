# Lesson Generator Mode Documentation

## Overview

The **LESSON_GENERATOR_MODE** is a specialized mode for the Course Design Agent that automatically generates complete, structured lesson content following the lesson schema.

## Endpoint

```
POST /api/admin/generate_lesson.php
```

**Authentication:** Requires admin JWT token

## Input Parameters

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `subject` | string | Subject area (e.g., "Algebra", "Biology") |
| `level` | string | Grade/difficulty level (e.g., "High School", "College") |
| `unitNumber` | integer | Unit number (1-based) |
| `lessonNumber` | integer | Lesson number within unit (1-based) |
| `standards` | array | Educational standards (array of objects or strings) |

### Optional Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `lessonTitle` | string | null | Suggested lesson title |
| `unitTitle` | string | null | Title of the unit this lesson belongs to |
| `prerequisites` | array | [] | Array of prerequisite topics |
| `estimatedDuration` | integer | 45 | Estimated minutes to complete |
| `difficulty` | string | "intermediate" | Difficulty level |

## Standards Format

Standards can be provided as objects:
```json
{
  "code": "CCSS.MATH.6.EE.A.1",
  "description": "Write and evaluate numerical expressions"
}
```

Or as simple strings:
```json
"CCSS.MATH.6.EE.A.1: Write and evaluate numerical expressions"
```

## Response Format

### Success Response (200 OK)

```json
{
  "success": true,
  "lesson": {
    "lessonNumber": 2,
    "lessonTitle": "Order of Operations",
    "objectives": ["...", "..."],
    "explanation": "...",
    "guidedExamples": [...],
    "practiceProblems": [...],
    "quizQuestions": [...],
    "videoPlaceholder": "...",
    "summary": "...",
    "vocabulary": [...],
    "estimatedDuration": 50,
    "difficulty": "beginner"
  },
  "validation": {
    "valid": true,
    "errors": []
  },
  "message": "Lesson generated successfully",
  "metadata": {
    "subject": "Algebra",
    "level": "High School",
    "unitNumber": 1,
    "lessonNumber": 2,
    "generatedAt": "2025-11-28T13:45:00Z"
  }
}
```

### Validation Warning Response (200 OK)

If the generated lesson has minor validation issues, it still returns successfully but includes warnings:

```json
{
  "success": true,
  "lesson": {...},
  "validation": {
    "valid": false,
    "warnings": ["Missing field: vocabulary", "practiceProblems array is empty"]
  },
  "message": "Lesson generated with validation warnings"
}
```

### Error Response (400 Bad Request)

```json
{
  "error": "Missing required field: standards"
}
```

### Error Response (500 Internal Server Error)

```json
{
  "success": false,
  "error": "Failed to generate lesson: Agent service timeout"
}
```

## Example Usage

### JavaScript/Fetch

```javascript
const generateLesson = async () => {
  const token = sessionStorage.getItem('admin_token');
  
  const response = await fetch('/api/admin/generate_lesson.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      subject: 'Algebra',
      level: 'High School',
      unitNumber: 1,
      lessonNumber: 2,
      lessonTitle: 'Order of Operations',
      standards: [
        {
          code: 'CCSS.MATH.6.EE.A.1',
          description: 'Write and evaluate numerical expressions'
        }
      ],
      prerequisites: ['Basic arithmetic'],
      estimatedDuration: 50,
      difficulty: 'beginner'
    })
  });
  
  const data = await response.json();
  
  if (data.success) {
    console.log('Lesson generated:', data.lesson);
    
    if (!data.validation.valid) {
      console.warn('Validation warnings:', data.validation.warnings);
    }
    
    // Display lesson for admin approval
    displayLessonForApproval(data.lesson);
  } else {
    console.error('Error:', data.error);
  }
};
```

### cURL

```bash
curl -X POST http://localhost/basic_educational/api/admin/generate_lesson.php \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Algebra",
    "level": "High School",
    "unitNumber": 1,
    "lessonNumber": 2,
    "standards": [
      {
        "code": "CCSS.MATH.6.EE.A.1",
        "description": "Write and evaluate numerical expressions"
      }
    ],
    "prerequisites": ["Basic arithmetic"],
    "estimatedDuration": 50,
    "difficulty": "beginner"
  }'
```

### PHP

```php
<?php
require_once 'config/database.php';

$payload = [
    'subject' => 'Algebra',
    'level' => 'High School',
    'unitNumber' => 1,
    'lessonNumber' => 2,
    'standards' => [
        [
            'code' => 'CCSS.MATH.6.EE.A.1',
            'description' => 'Write and evaluate numerical expressions'
        ]
    ],
    'prerequisites' => ['Basic arithmetic'],
    'estimatedDuration' => 50,
    'difficulty' => 'beginner'
];

$ch = curl_init('http://localhost/basic_educational/api/admin/generate_lesson.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $_SESSION['admin_token'],
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['success']) {
    $lesson = $data['lesson'];
    // Present to admin for approval
}
?>
```

## Generated Lesson Structure

The generated lesson includes:

1. **Core Information**
   - `lessonNumber`, `lessonTitle`, `estimatedDuration`, `difficulty`

2. **Learning Content**
   - `objectives`: 3-5 specific learning objectives
   - `explanation`: Comprehensive markdown content
   - `vocabulary`: Key terms with definitions

3. **Teaching Components**
   - `guidedExamples`: 2-3 step-by-step worked examples
   - `practiceProblems`: 4-6 independent practice problems
   - `quizQuestions`: 4-6 assessment questions (multiple-choice, true-false, short-answer)

4. **Media & Summary**
   - `videoPlaceholder`: Suggested video topic
   - `summary`: Key takeaways

## Validation Rules

The endpoint validates:
- Required fields are present
- `unitNumber` and `lessonNumber` are positive integers
- `standards` is an array
- Arrays (`objectives`, `guidedExamples`, etc.) are valid arrays
- `objectives` is not empty

## Processing Time

Lesson generation typically takes **30-90 seconds** depending on:
- Complexity of the subject matter
- Number of standards provided
- LLM inference speed

The endpoint has a 2-minute timeout.

## Workflow Integration

### Typical Admin Workflow

1. **Admin inputs parameters** (subject, level, unit, lesson, standards)
2. **API generates lesson** via Course Design Agent
3. **Admin reviews generated lesson**
4. **Admin approves or requests regeneration**
5. **Admin saves approved lesson** to course metadata

### Future UI Integration

The lesson generator is designed to be integrated into:
- Course creation wizard
- Unit editing interface
- Bulk lesson generation tool
- Standards-based curriculum mapper

## Error Handling

Common errors and solutions:

| Error | Cause | Solution |
|-------|-------|----------|
| Missing required field | Input validation failed | Check all required fields are provided |
| Agent service timeout | LLM took too long | Retry or simplify standards |
| Invalid JSON response | Agent returned non-JSON | Check agent system prompt configuration |
| No suitable agent found | No Course Design Agent active | Create or activate an agent |

## Notes

- **No UI integration yet**: This mode only generates and returns data
- **Admin approval required**: Generated lessons should be reviewed before publication
- **Extensible**: Additional fields can be added to input/output without breaking compatibility
- **Validation warnings**: Lessons with minor issues are still returned but flagged
- **Logged activity**: All lesson generations are logged for auditing

## Testing

Use the provided test script:

```bash
cd tests
chmod +x test_lesson_generator.sh
# Edit script to add your JWT token
./test_lesson_generator.sh
```

This will:
1. Send a test request
2. Display the response
3. Save the generated lesson to a JSON file
4. Show lesson statistics

## Performance Tips

For faster generation:
- Provide clear, specific standards
- Use shorter prerequisite lists
- Request realistic duration estimates
- Use appropriate difficulty levels

## Future Enhancements

Planned improvements:
- Batch lesson generation
- Template-based generation
- Style/voice customization
- Multi-language support
- Adaptive difficulty adjustment
