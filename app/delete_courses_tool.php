<?php
/**
 * Quick CLI tool to delete published courses
 * Usage: php delete_courses_tool.php [course_id1] [course_id2] ...
 * 
 * Examples:
 *   php delete_courses_tool.php 1 2 3 4    # Delete specific courses
 *   php delete_courses_tool.php --list     # List all courses
 *   php delete_courses_tool.php --keep 9   # Delete all EXCEPT course_id 9
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

// List courses function
function listCourses($db) {
    $stmt = $db->query('SELECT course_id, course_name, subject_area, difficulty_level, created_at FROM courses WHERE is_active = 1 ORDER BY created_at DESC');
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nPublished Courses:\n";
    echo str_repeat("=", 80) . "\n";
    printf("%-5s %-30s %-20s %-15s %s\n", "ID", "Name", "Subject", "Difficulty", "Created");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($courses as $course) {
        printf("%-5d %-30s %-20s %-15s %s\n", 
            $course['course_id'],
            substr($course['course_name'], 0, 30),
            $course['subject_area'],
            $course['difficulty_level'],
            $course['created_at']
        );
    }
    echo str_repeat("=", 80) . "\n";
    echo "Total: " . count($courses) . " courses\n\n";
}

// Delete course function
function deleteCourse($db, $courseId) {
    // Get course info first
    $stmt = $db->prepare("SELECT course_id, course_name FROM courses WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        echo "❌ Course ID $courseId not found.\n";
        return false;
    }
    
    // Confirm deletion
    echo "🗑️  Deleting: [{$course['course_id']}] {$course['course_name']}... ";
    
    $stmt = $db->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->execute([$courseId]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ DELETED\n";
        return true;
    } else {
        echo "❌ FAILED\n";
        return false;
    }
}

// Main logic
if (count($argv) < 2) {
    echo "Usage:\n";
    echo "  php {$argv[0]} [course_id1] [course_id2] ...  # Delete specific courses\n";
    echo "  php {$argv[0]} --list                         # List all courses\n";
    echo "  php {$argv[0]} --keep [course_id]            # Delete all EXCEPT specified course\n\n";
    listCourses($db);
    exit(0);
}

// Handle --list flag
if ($argv[1] === '--list') {
    listCourses($db);
    exit(0);
}

// Handle --keep flag
if ($argv[1] === '--keep' && isset($argv[2])) {
    $keepId = intval($argv[2]);
    
    echo "⚠️  WARNING: This will delete ALL courses EXCEPT course ID $keepId\n";
    echo "Are you sure? Type 'yes' to confirm: ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtolower($line) !== 'yes') {
        echo "❌ Cancelled.\n";
        exit(0);
    }
    
    // Get all courses except the one to keep
    $stmt = $db->prepare("SELECT course_id FROM courses WHERE course_id != ? AND is_active = 1");
    $stmt->execute([$keepId]);
    $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n";
    foreach ($courseIds as $courseId) {
        deleteCourse($db, $courseId);
    }
    
    echo "\n✅ Deletion complete. Courses remaining:\n\n";
    listCourses($db);
    exit(0);
}

// Delete specific course IDs
echo "⚠️  About to delete " . (count($argv) - 1) . " course(s)\n";
echo "Are you sure? Type 'yes' to confirm: ";

$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));

if (strtolower($line) !== 'yes') {
    echo "❌ Cancelled.\n";
    exit(0);
}

echo "\n";
$deleted = 0;
for ($i = 1; $i < count($argv); $i++) {
    $courseId = intval($argv[$i]);
    if (deleteCourse($db, $courseId)) {
        $deleted++;
    }
}

echo "\n✅ Deleted $deleted course(s).\n\n";
listCourses($db);
