# Course Metadata Structure Guide

## Overview

The course metadata system provides a flexible, JSON-based structure for storing and managing course information. The design emphasizes extensibility - new fields can be added at any level without breaking existing code.

## Files Created

1. **`api/course/course_metadata_schema.json`** - JSON Schema definition for validation
2. **`api/course/example_course_metadata.json`** - Working example of a complete course
3. **`api/course/CourseMetadata.php`** - PHP class for handling course metadata

## Core Structure

### Required Fields

```json
{
  "courseName": "string",
  "subject": "string", 
  "level": "string",
  "units": [...]
}
```

### Unit Structure

```json
{
  "unitNumber": 1,
  "unitTitle": "Unit Title",
  "lessons": [...]
}
```

### Lesson Structure

```json
{
  "lessonNumber": 1,
  "lessonTitle": "Lesson Title"
}
```

## Optional Fields (Extensible)

The schema supports `additionalProperties: true` at all levels, allowing you to add:

### Course Level
- `description` - Course overview
- `instructor` - Instructor name
- `duration` - Total hours
- `prerequisites` - Array of prerequisite courses
- `tags` - Searchable tags
- `version` - Version tracking
- `createdAt` / `updatedAt` - Timestamps
- **Any custom fields you need**

### Unit Level
- `description` - Unit overview
- `estimatedHours` - Time to complete
- **Any custom fields you need**

### Lesson Level
- `description` - Lesson overview
- `duration` - Duration in minutes
- `mediaUrl` - Video/content URL
- `contentType` - video, text, interactive, quiz, mixed
- **Any custom fields you need**

## PHP Usage Examples

### Loading Course Metadata

```php
require_once 'api/course/CourseMetadata.php';

// From file
$course = new CourseMetadata('api/course/example_course_metadata.json');

// From JSON string
$json = '{"courseName":"Test","subject":"Math","level":"K-12","units":[]}';
$course = new CourseMetadata($json);

// From array
$data = ['courseName' => 'Test', 'subject' => 'Math', 'level' => 'K-12', 'units' => []];
$course = new CourseMetadata($data);

// Create empty
$course = new CourseMetadata();
```

### Validating Metadata

```php
$errors = $course->validate();
if (empty($errors)) {
    echo "Course metadata is valid!";
} else {
    foreach ($errors as $error) {
        echo "Error: $error\n";
    }
}
```

### Accessing Data

```php
// Get course properties
$name = $course->get('courseName');
$subject = $course->get('subject');

// Get all units
$units = $course->getUnits();

// Get specific unit
$unit1 = $course->getUnit(1);

// Get lessons from unit
$lessons = $course->getLessons(1);

// Get specific lesson
$lesson = $course->getLesson(1, 2); // Unit 1, Lesson 2

// Get statistics
$stats = $course->getStatistics();
echo "Total lessons: " . $stats['totalLessons'];
echo "Total duration: " . $stats['totalDuration'] . " minutes";
```

### Modifying Data

```php
// Set course properties
$course->set('instructor', 'Professor Hawkeinstein');
$course->set('customField', 'Custom value'); // Add any field!

// Add a unit
$course->addUnit([
    'unitNumber' => 4,
    'unitTitle' => 'New Unit',
    'lessons' => [],
    'customUnitField' => 'Custom value' // Extensible!
]);

// Add a lesson to a unit
$course->addLesson(1, [
    'lessonNumber' => 4,
    'lessonTitle' => 'New Lesson',
    'duration' => 45,
    'customLessonField' => 'Custom value' // Extensible!
]);
```

### Searching

```php
// Search lessons by title or description
$results = $course->searchLessons('equations');
foreach ($results as $result) {
    echo "Found in Unit {$result['unitNumber']}: {$result['lesson']['lessonTitle']}\n";
}
```

### Saving

```php
// Save to file (pretty printed)
$course->saveToFile('courses/my_course.json');

// Get as JSON string
$json = $course->toJSON(true); // true = pretty print

// Get as array
$array = $course->toArray();
```

## Database Integration

To store course metadata in the database, add a `metadata` column to your `courses` table:

```sql
ALTER TABLE courses 
ADD COLUMN metadata LONGTEXT 
COMMENT 'JSON course metadata';
```

Then store the JSON:

```php
$db = getDB();
$stmt = $db->prepare("UPDATE courses SET metadata = ? WHERE course_id = ?");
$stmt->execute([$course->toJSON(), $courseId]);
```

Load from database:

```php
$stmt = $db->prepare("SELECT metadata FROM courses WHERE course_id = ?");
$stmt->execute([$courseId]);
$row = $stmt->fetch();
$course = new CourseMetadata($row['metadata']);
```

## API Endpoint Example

```php
<?php
// api/course/get_metadata.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/CourseMetadata.php';

header('Content-Type: application/json');

$courseId = $_GET['courseId'] ?? null;

if (!$courseId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing courseId']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT metadata FROM courses WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $row = $stmt->fetch();
    
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Course not found']);
        exit;
    }
    
    $course = new CourseMetadata($row['metadata']);
    
    echo json_encode([
        'success' => true,
        'course' => $course->toArray(),
        'statistics' => $course->getStatistics()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
```

## Extensibility Examples

### Adding Custom Fields

```php
// Add course-level custom fields
$course->set('difficulty', 'intermediate');
$course->set('certification', true);
$course->set('partnerId', 12345);

// Add unit-level custom fields
$units = $course->getUnits();
$units[0]['customField'] = 'value';
$course->set('units', $units);

// Add lesson-level custom fields (when creating)
$course->addLesson(1, [
    'lessonNumber' => 5,
    'lessonTitle' => 'Advanced Topic',
    'duration' => 60,
    'requiresLab' => true,
    'equipmentNeeded' => ['calculator', 'notebook']
]);
```

### Version Migration Example

```php
function migrateToV2($course) {
    // Add new fields for v2.0.0
    $course->set('version', '2.0.0');
    $course->set('language', 'en');
    $course->set('accessibility', ['closedCaptions' => true]);
    
    // Update all lessons with new fields
    $units = $course->getUnits();
    foreach ($units as &$unit) {
        foreach ($unit['lessons'] as &$lesson) {
            $lesson['accessibility'] = ['transcriptAvailable' => true];
        }
    }
    $course->set('units', $units);
    
    return $course;
}
```

## Best Practices

1. **Always validate** before saving: `$errors = $course->validate();`
2. **Use version numbers** to track metadata schema changes
3. **Update timestamps** when modifying (CourseMetadata does this automatically)
4. **Add custom fields freely** - the schema supports it
5. **Document custom fields** in your application's documentation
6. **Use pretty printing** when saving to files for readability
7. **Store metadata as JSON** in database LONGTEXT columns

## Integration with Existing System

The course metadata structure integrates with your existing database:

```php
// When creating a course
$course = new CourseMetadata();
$course->set('courseName', 'Introduction to Physics');
$course->set('subject', 'Science');
$course->set('level', 'High School');
$course->set('instructor', 'Professor Hawkeinstein');

$db = getDB();
$stmt = $db->prepare("INSERT INTO courses (title, metadata) VALUES (?, ?)");
$stmt->execute([
    $course->get('courseName'),
    $course->toJSON()
]);
```

## Future Extensions

The system easily supports future additions:

- **Adaptive Learning**: Add `adaptivePath` fields to lessons
- **Gamification**: Add `points`, `badges` to units/lessons
- **Collaboration**: Add `groupWork`, `peerReview` flags
- **Analytics**: Add `completionRate`, `avgScore` fields
- **Multimedia**: Add `vrSupport`, `interactiveSimulation` fields
- **Accessibility**: Add `readingLevel`, `audioDescription` fields

All additions are backward compatible - existing code continues to work!
