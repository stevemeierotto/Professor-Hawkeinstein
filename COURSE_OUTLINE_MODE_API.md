# Course Outline Mode Documentation

## Overview

The **COURSE_OUTLINE_MODE** generates a complete course structure with unit titles, lesson titles, and standards mapping. It does NOT generate full lesson content - only the outline for admin review and approval before bulk content generation.

## Purpose

Create a course skeleton that admins can:
1. **Review** - Check unit/lesson progression
2. **Edit** - Modify titles, descriptions, standards
3. **Approve** - Confirm structure before generating content
4. **Generate** - Use as foundation for bulk lesson generation

## Endpoints

### 1. Generate Outline

```
POST /api/admin/generate_course_outline.php
```

**Authentication:** Requires admin JWT token  
**Timeout:** 2 minutes (120 seconds)

### 2. Save Outline

```
POST /api/admin/save_course_outline.php
```

**Authentication:** Requires admin JWT token

## Workflow

```
1. Generate Outline → 2. Admin Reviews → 3. Admin Edits (optional) → 4. Save Outline → 5. Generate Content
```

## Generate Outline API

### Input Parameters

#### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `subject` | string | Subject area (e.g., "Algebra I", "Biology", "World History") |
| `level` | string | Grade/difficulty level (e.g., "High School", "Middle School") |

#### Optional Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `unitCount` | integer | 6 | Number of units (3-12) |
| `lessonsPerUnit` | integer | 5 | Lessons per unit (3-8) |
| `standardsSet` | string | null | Standards framework (e.g., "Common Core", "NGSS") |

### Response Format

```json
{
  "success": true,
  "courseName": "Algebra I - High School",
  "subject": "Algebra I",
  "level": "High School",
  "description": "Comprehensive algebra course covering...",
  "units": [
    {
      "unitNumber": 1,
      "unitTitle": "Foundations of Algebra",
      "description": "Introduction to variables, expressions, and equations",
      "standards": [
        {
          "code": "CCSS.MATH.HSA.SSE.A.1",
          "description": "Interpret expressions representing quantities"
        },
        {
          "code": "CCSS.MATH.HSA.REI.A.1",
          "description": "Explain each step in solving equations"
        }
      ],
      "lessons": [
        {
          "lessonNumber": 1,
          "lessonTitle": "Variables and Constants",
          "description": "Understanding algebraic notation and terminology"
        },
        {
          "lessonNumber": 2,
          "lessonTitle": "Order of Operations",
          "description": "PEMDAS and evaluating expressions"
        },
        {
          "lessonNumber": 3,
          "lessonTitle": "Simplifying Expressions",
          "description": "Combining like terms and using properties"
        },
        {
          "lessonNumber": 4,
          "lessonTitle": "Writing Expressions",
          "description": "Translating word problems into algebra"
        },
        {
          "lessonNumber": 5,
          "lessonTitle": "Introduction to Equations",
          "description": "Understanding equality and solving one-step equations"
        }
      ]
    },
    {
      "unitNumber": 2,
      "unitTitle": "Linear Equations and Inequalities",
      "description": "Solving and graphing linear relationships",
      "standards": [...],
      "lessons": [...]
    }
  ],
  "totalUnits": 6,
  "totalLessons": 30,
  "generationTime": 15.8,
  "message": "Course outline generated successfully"
}
```

## Save Outline API

### Input Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `courseId` | string | Yes | Course identifier (letters, numbers, hyphens, underscores only) |
| `outline` | object | Yes | Complete outline object from generate endpoint |
| `overwrite` | boolean | No (default: false) | Allow overwriting existing course file |

### Response Format

```json
{
  "success": true,
  "courseId": "algebra_1_2025",
  "courseFile": "/path/to/course_algebra_1_2025.json",
  "courseName": "Algebra I - High School",
  "totalUnits": 6,
  "totalLessons": 30,
  "message": "Course outline saved successfully. Ready for lesson generation."
}
```

## Generated Course File Structure

The saved course file contains lesson placeholders with `status: "outline"`:

```json
{
  "courseName": "Algebra I - High School",
  "subject": "Algebra I",
  "level": "High School",
  "description": "...",
  "units": [
    {
      "unitNumber": 1,
      "unitTitle": "Foundations of Algebra",
      "description": "...",
      "standards": [...],
      "lessons": [
        {
          "lessonNumber": 1,
          "lessonTitle": "Variables and Constants",
          "description": "Understanding algebraic notation",
          "objectives": [],
          "explanation": "",
          "guidedExamples": [],
          "practiceProblems": [],
          "quizQuestions": [],
          "videoPlaceholder": "",
          "summary": "",
          "estimatedDuration": 45,
          "difficulty": "intermediate",
          "status": "outline"
        }
      ]
    }
  ],
  "version": "1.0.0",
  "createdAt": "2025-11-28T16:00:00Z",
  "updatedAt": "2025-11-28T16:00:00Z"
}
```

Empty content fields will be populated during lesson generation.

## Example Usage

### Complete Workflow (JavaScript)

```javascript
// Step 1: Generate outline
async function generateAndSaveCourse() {
  const token = sessionStorage.getItem('admin_token');
  
  // Generate outline
  const generateResponse = await fetch('/api/admin/generate_course_outline.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      subject: 'Algebra I',
      level: 'High School',
      unitCount: 6,
      lessonsPerUnit: 5,
      standardsSet: 'Common Core State Standards'
    })
  });
  
  const outline = await generateResponse.json();
  
  if (!outline.success) {
    throw new Error('Outline generation failed: ' + outline.error);
  }
  
  console.log(`Generated outline: ${outline.totalUnits} units, ${outline.totalLessons} lessons`);
  
  // Step 2: Show to admin for review
  const approved = await showOutlineReviewDialog(outline);
  
  if (!approved) {
    return { cancelled: true };
  }
  
  // Step 3: Save approved outline
  const saveResponse = await fetch('/api/admin/save_course_outline.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      courseId: 'algebra_1_2025',
      outline: outline,
      overwrite: false
    })
  });
  
  const saved = await saveResponse.json();
  
  if (saved.success) {
    console.log(`Course saved: ${saved.courseFile}`);
    return saved;
  } else {
    throw new Error('Save failed: ' + saved.error);
  }
}

// Display outline for review
function showOutlineReviewDialog(outline) {
  console.log('Course Outline:');
  console.log(`${outline.courseName} - ${outline.totalUnits} units`);
  
  outline.units.forEach(unit => {
    console.log(`\nUnit ${unit.unitNumber}: ${unit.unitTitle}`);
    unit.lessons.forEach(lesson => {
      console.log(`  ${lesson.lessonNumber}. ${lesson.lessonTitle}`);
    });
  });
  
  return confirm('Approve this course outline?');
}
```

### Edit Before Saving

```javascript
// Generate outline
const outline = await generateOutline({...});

// Edit titles
outline.units[0].unitTitle = "Fundamentals of Algebra"; // Changed
outline.units[0].lessons[0].lessonTitle = "Introduction to Variables"; // Changed

// Add custom standards
outline.units[1].standards.push({
  code: "CUSTOM.STD.1",
  description: "Custom learning objective"
});

// Save edited outline
await saveOutline('algebra_1_custom', outline);
```

### Generate Content After Saving

```javascript
// After saving outline, generate content for each unit
const courseId = 'algebra_1_2025';

for (let unitNum = 1; unitNum <= 6; unitNum++) {
  console.log(`Generating unit ${unitNum}...`);
  
  const response = await fetch('/api/admin/generate_unit.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      courseId: courseId,
      unitNumber: unitNum,
      // Other parameters from outline
    })
  });
  
  const result = await response.json();
  console.log(`Unit ${unitNum}: ${result.lessonsSaved} lessons generated`);
}
```

## Use Cases

### 1. Rapid Course Prototyping
```javascript
// Quick outline for stakeholder review
const outline = await generateOutline({
  subject: 'Data Science',
  level: 'College',
  unitCount: 8,
  lessonsPerUnit: 4
});
```

### 2. Standards-Based Curriculum
```javascript
// Generate with specific standards framework
const outline = await generateOutline({
  subject: 'Biology',
  level: 'High School',
  standardsSet: 'Next Generation Science Standards (NGSS)'
});
```

### 3. Customizable Templates
```javascript
// Generate → Edit → Save
const outline = await generateOutline({...});
outline.units[2].lessons.push({
  lessonNumber: 6,
  lessonTitle: "Bonus: Advanced Topic",
  description: "Optional enrichment"
});
await saveOutline('biology_advanced', outline);
```

## Validation

### Outline Validation

The API validates:
- ✅ All units have unit numbers and titles
- ✅ All lessons have lesson numbers and titles
- ✅ Unit count matches expected
- ✅ Lessons per unit matches expected
- ✅ Standards are properly formatted (if present)

### Course ID Validation

Course IDs must:
- Contain only: letters, numbers, hyphens, underscores
- Be unique (no overwrite by default)
- Be lowercase recommended

## Performance

| Operation | Typical Time |
|-----------|-------------|
| Generate outline (6 units) | 10-20 seconds |
| Save outline | <1 second |
| Generate full content (1 unit) | 2-5 minutes |
| Generate full content (6 units) | 12-30 minutes |

**Recommendation:** Generate outline first, review/approve, then generate content unit-by-unit.

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| Course file already exists | courseId in use | Use different courseId or set overwrite=true |
| Invalid courseId | Special characters | Use only letters, numbers, hyphens, underscores |
| Unit count out of range | Not 3-12 | Adjust unitCount parameter |
| Lessons per unit out of range | Not 3-8 | Adjust lessonsPerUnit parameter |
| Validation failed | Missing required fields | Check outline structure |

## Comparison to Other Modes

| Mode | Input | Output | Time | Use Case |
|------|-------|--------|------|----------|
| **Course Outline** | Subject, level | Unit/lesson titles | 10-20s | Structure planning |
| **Lesson Generator** | Unit, lesson params | 1 complete lesson | 30-60s | Single lesson |
| **Unit Generator** | Unit params | 5 complete lessons | 2-5min | Bulk content |

## Best Practices

### 1. Review Before Generating Content
```javascript
// Generate outline first
const outline = await generateOutline({...});

// Review with team
await reviewWithStakeholders(outline);

// Save approved version
await saveOutline('approved_course', outline);

// Then generate content
await generateAllUnits('approved_course');
```

### 2. Iterative Refinement
```javascript
// Generate → Review → Edit → Regenerate
let outline = await generateOutline({...});

if (!satisfactory) {
  // Edit and regenerate
  outline = await generateOutline({
    ...previousParams,
    standardsSet: 'Different Framework'
  });
}
```

### 3. Save Multiple Versions
```javascript
// Save variations for comparison
await saveOutline('algebra_1_standard', outline1);
await saveOutline('algebra_1_honors', outline2);
await saveOutline('algebra_1_remedial', outline3);
```

## Testing

Run the test script:
```bash
cd tests
chmod +x test_course_outline.sh
# Edit script to add your JWT token
./test_course_outline.sh
```

Tests:
1. ✅ Generate full outline (6 units, 5 lessons)
2. ✅ Save outline to course file
3. ✅ Generate minimal outline (3 units, 3 lessons)

## Security

- ✅ **Admin authentication required**
- ✅ **Activity logging**
- ✅ **Path validation** (prevents directory traversal)
- ✅ **Course ID sanitization**
- ✅ **Overwrite protection** (by default)

## Future Enhancements

Planned improvements:
- Visual outline editor
- Import from existing curricula
- Export to different formats (PDF, CSV)
- Collaborative editing
- Version history
- Template library
