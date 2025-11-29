<?php
/**
 * CourseMetadata Class
 * Handles parsing, validation, and manipulation of course metadata JSON structures
 */

class CourseMetadata {
    private $data;
    private $schemaPath;
    
    /**
     * Constructor
     * @param string|array $source JSON string, file path, or array
     */
    public function __construct($source = null) {
        $this->schemaPath = __DIR__ . '/course_metadata_schema.json';
        
        if (is_string($source)) {
            // Check if it's a file path
            if (file_exists($source)) {
                $this->loadFromFile($source);
            } else {
                // Assume it's JSON string
                $this->loadFromJSON($source);
            }
        } elseif (is_array($source)) {
            $this->data = $source;
        } else {
            $this->initializeEmpty();
        }
    }
    
    /**
     * Initialize empty course structure
     */
    private function initializeEmpty() {
        $this->data = [
            'courseName' => '',
            'subject' => '',
            'level' => '',
            'units' => [],
            'version' => '1.0.0',
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
    }
    
    /**
     * Load course metadata from JSON file
     * @param string $filePath Path to JSON file
     * @return bool Success status
     */
    public function loadFromFile($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("Course metadata file not found: $filePath");
        }
        
        $json = file_get_contents($filePath);
        return $this->loadFromJSON($json);
    }
    
    /**
     * Load course metadata from JSON string
     * @param string $json JSON string
     * @return bool Success status
     */
    public function loadFromJSON($json) {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON: " . json_last_error_msg());
        }
        
        $this->data = $data;
        return true;
    }
    
    /**
     * Validate course metadata against schema
     * @return array Array of validation errors (empty if valid)
     */
    public function validate() {
        $errors = [];
        
        // Required fields
        $required = ['courseName', 'subject', 'level', 'units'];
        foreach ($required as $field) {
            if (!isset($this->data[$field]) || empty($this->data[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // Validate units array
        if (isset($this->data['units']) && is_array($this->data['units'])) {
            foreach ($this->data['units'] as $index => $unit) {
                if (!isset($unit['unitNumber'])) {
                    $errors[] = "Unit $index missing unitNumber";
                }
                if (!isset($unit['unitTitle'])) {
                    $errors[] = "Unit $index missing unitTitle";
                }
                if (!isset($unit['lessons']) || !is_array($unit['lessons'])) {
                    $errors[] = "Unit $index missing or invalid lessons array";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Get course metadata as array
     * @return array
     */
    public function toArray() {
        return $this->data;
    }
    
    /**
     * Get course metadata as JSON string
     * @param bool $pretty Pretty print JSON
     * @return string
     */
    public function toJSON($pretty = false) {
        $options = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0;
        return json_encode($this->data, $options);
    }
    
    /**
     * Save course metadata to file
     * @param string $filePath Path to save file
     * @param bool $pretty Pretty print JSON
     * @return bool Success status
     */
    public function saveToFile($filePath, $pretty = true) {
        $json = $this->toJSON($pretty);
        $bytes = file_put_contents($filePath, $json);
        return $bytes !== false;
    }
    
    /**
     * Get course property
     * @param string $key Property name
     * @return mixed
     */
    public function get($key) {
        return $this->data[$key] ?? null;
    }
    
    /**
     * Set course property
     * @param string $key Property name
     * @param mixed $value Property value
     */
    public function set($key, $value) {
        $this->data[$key] = $value;
        $this->data['updatedAt'] = date('c');
    }
    
    /**
     * Get all units
     * @return array
     */
    public function getUnits() {
        return $this->data['units'] ?? [];
    }
    
    /**
     * Get specific unit by number
     * @param int $unitNumber Unit number
     * @return array|null
     */
    public function getUnit($unitNumber) {
        $units = $this->getUnits();
        foreach ($units as $unit) {
            if ($unit['unitNumber'] == $unitNumber) {
                return $unit;
            }
        }
        return null;
    }
    
    /**
     * Add a new unit
     * @param array $unit Unit data
     */
    public function addUnit($unit) {
        if (!isset($this->data['units'])) {
            $this->data['units'] = [];
        }
        $this->data['units'][] = $unit;
        $this->data['updatedAt'] = date('c');
    }
    
    /**
     * Get lessons from specific unit
     * @param int $unitNumber Unit number
     * @return array
     */
    public function getLessons($unitNumber) {
        $unit = $this->getUnit($unitNumber);
        return $unit['lessons'] ?? [];
    }
    
    /**
     * Get specific lesson
     * @param int $unitNumber Unit number
     * @param int $lessonNumber Lesson number
     * @return array|null
     */
    public function getLesson($unitNumber, $lessonNumber) {
        $lessons = $this->getLessons($unitNumber);
        foreach ($lessons as $lesson) {
            if ($lesson['lessonNumber'] == $lessonNumber) {
                return $lesson;
            }
        }
        return null;
    }
    
    /**
     * Add lesson to unit
     * @param int $unitNumber Unit number
     * @param array $lesson Lesson data
     * @return bool Success status
     */
    public function addLesson($unitNumber, $lesson) {
        $units = &$this->data['units'];
        foreach ($units as &$unit) {
            if ($unit['unitNumber'] == $unitNumber) {
                if (!isset($unit['lessons'])) {
                    $unit['lessons'] = [];
                }
                $unit['lessons'][] = $lesson;
                $this->data['updatedAt'] = date('c');
                return true;
            }
        }
        return false;
    }
    
    /**
     * Insert or update a lesson in the correct unit and position
     * This safely handles inserting lessons by lesson number, replacing existing ones
     * 
     * @param int $unitNumber Unit number
     * @param array $lessonData Complete lesson object
     * @return array Result with success status and message
     */
    public function insertLesson($unitNumber, $lessonData) {
        // Validate lesson has required fields
        if (!isset($lessonData['lessonNumber'])) {
            return [
                'success' => false,
                'error' => 'Lesson must have a lessonNumber field'
            ];
        }
        
        $lessonNumber = $lessonData['lessonNumber'];
        $units = &$this->data['units'];
        $unitFound = false;
        
        // Find the unit
        foreach ($units as $unitIndex => &$unit) {
            if ($unit['unitNumber'] == $unitNumber) {
                $unitFound = true;
                
                if (!isset($unit['lessons'])) {
                    $unit['lessons'] = [];
                }
                
                // Check if lesson with this number already exists
                $lessonExists = false;
                foreach ($unit['lessons'] as $index => &$existingLesson) {
                    if ($existingLesson['lessonNumber'] == $lessonNumber) {
                        // Replace existing lesson
                        $unit['lessons'][$index] = $lessonData;
                        $lessonExists = true;
                        $this->data['updatedAt'] = date('c');
                        
                        return [
                            'success' => true,
                            'action' => 'updated',
                            'message' => "Lesson $lessonNumber in Unit $unitNumber updated successfully",
                            'unitNumber' => $unitNumber,
                            'lessonNumber' => $lessonNumber
                        ];
                    }
                }
                
                // If lesson doesn't exist, insert it in the correct position
                if (!$lessonExists) {
                    $inserted = false;
                    $newLessons = [];
                    
                    foreach ($unit['lessons'] as $existingLesson) {
                        if (!$inserted && $existingLesson['lessonNumber'] > $lessonNumber) {
                            $newLessons[] = $lessonData;
                            $inserted = true;
                        }
                        $newLessons[] = $existingLesson;
                    }
                    
                    // If not inserted yet, append at end
                    if (!$inserted) {
                        $newLessons[] = $lessonData;
                    }
                    
                    $unit['lessons'] = $newLessons;
                    $this->data['updatedAt'] = date('c');
                    
                    return [
                        'success' => true,
                        'action' => 'inserted',
                        'message' => "Lesson $lessonNumber added to Unit $unitNumber successfully",
                        'unitNumber' => $unitNumber,
                        'lessonNumber' => $lessonNumber
                    ];
                }
                
                break;
            }
        }
        
        if (!$unitFound) {
            return [
                'success' => false,
                'error' => "Unit $unitNumber not found in course"
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Unknown error occurred'
        ];
    }
    
    /**
     * Get course statistics
     * @return array
     */
    public function getStatistics() {
        $stats = [
            'totalUnits' => count($this->data['units'] ?? []),
            'totalLessons' => 0,
            'totalDuration' => 0,
            'averageLessonDuration' => 0
        ];
        
        foreach ($this->getUnits() as $unit) {
            $lessons = $unit['lessons'] ?? [];
            $stats['totalLessons'] += count($lessons);
            
            foreach ($lessons as $lesson) {
                if (isset($lesson['duration'])) {
                    $stats['totalDuration'] += $lesson['duration'];
                }
            }
        }
        
        if ($stats['totalLessons'] > 0) {
            $stats['averageLessonDuration'] = round($stats['totalDuration'] / $stats['totalLessons'], 2);
        }
        
        return $stats;
    }
    
    /**
     * Search lessons by title or description
     * @param string $query Search query
     * @return array Array of matching lessons with unit context
     */
    public function searchLessons($query) {
        $results = [];
        $query = strtolower($query);
        
        foreach ($this->getUnits() as $unit) {
            foreach ($unit['lessons'] ?? [] as $lesson) {
                $title = strtolower($lesson['lessonTitle'] ?? '');
                $desc = strtolower($lesson['description'] ?? '');
                
                if (strpos($title, $query) !== false || strpos($desc, $query) !== false) {
                    $results[] = [
                        'unitNumber' => $unit['unitNumber'],
                        'unitTitle' => $unit['unitTitle'],
                        'lesson' => $lesson
                    ];
                }
            }
        }
        
        return $results;
    }
}
?>
