# Student Advisor Instance System - API Documentation

## Overview
Converted advisor system from shared agents to per-student isolated instances. Each student has exactly ONE advisor instance based on an advisor agent template, with completely isolated data storage.

## Key Design Pattern

### Old Pattern (Removed)
- Shared agents with `assigned_student_count` 
- `student_advisor_assignments` junction table
- All students' data mixed together

### New Pattern (Implemented)
```
agents table (TEMPLATES)
    ↓ (advisor_type_id)
student_advisors table (PER-STUDENT INSTANCES)
    ↓ (unique per student)
Isolated data: conversation_history, progress_notes, testing_results
```

**Critical Constraint:** `UNIQUE KEY unique_student_advisor (student_id)` 
- Enforces one advisor per student
- Prevents data leakage between students

## Database Schema

### `student_advisors` Table
```sql
advisor_instance_id INT PRIMARY KEY (unique per student)
student_id INT UNIQUE FK (one advisor per student)
advisor_type_id INT FK (references agents.agent_id)
created_at TIMESTAMP
last_interaction TIMESTAMP
is_active TINYINT(1)
conversation_history LONGTEXT (JSON array of messages)
progress_notes TEXT
testing_results LONGTEXT (JSON array of test scores)
strengths_areas TEXT
growth_areas TEXT
custom_system_prompt TEXT (can override agent template)
```

## API Endpoints

### 1. Create Advisor Instance for Student
**File:** `api/admin/assign_student_advisor.php`
**Method:** POST
**Auth:** Admin only

**Request:**
```json
{
  "student_id": 5,
  "advisor_type_id": 1
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Advisor instance created for john_doe",
  "advisor_instance": {
    "advisor_instance_id": 42,
    "student_id": 5,
    "advisor_type_id": 1,
    "advisor_name": "Professor Hawkeinstein",
    "created_at": "2024-01-20 14:30:00"
  }
}
```

**Error Cases:**
- 400: Missing required fields
- 404: Advisor type not found OR not a student advisor OR student not found
- 409: Student already has an advisor instance (only one allowed)

### 2. Get Student's Advisor Instance
**File:** `api/student/get_advisor.php`
**Method:** GET
**Auth:** Student (gets their own instance)

**Request:**
```
GET /api/student/get_advisor.php
```

**Response (200):**
```json
{
  "success": true,
  "advisor_instance": {
    "advisor_instance_id": 42,
    "student_id": 5,
    "advisor_type_id": 1,
    "created_at": "2024-01-20 14:30:00",
    "last_interaction": "2024-01-20 15:45:00",
    "is_active": 1,
    "agent_name": "Professor Hawkeinstein",
    "agent_type": "student_advisor",
    "system_prompt": "You are Professor Hawkeinstein's Student Advisor...",
    "temperature": 0.7,
    "model": "qwen2.5:3b",
    "conversation_history": [
      {
        "timestamp": "2024-01-20 15:45:00",
        "role": "student",
        "message": "Hello Professor, I need help with math",
        "metadata": null
      },
      {
        "timestamp": "2024-01-20 15:45:30",
        "role": "advisor",
        "message": "Of course! What math topic are you struggling with?",
        "metadata": null
      }
    ],
    "progress_notes": "John is excelling in STEM subjects...",
    "testing_results": [
      {
        "timestamp": "2024-01-20 14:00:00",
        "test_name": "Algebra Unit 1 Quiz",
        "course_id": 3,
        "score": 88,
        "max_score": 100,
        "percentage": 88.00,
        "feedback": "Great understanding of linear equations!"
      }
    ],
    "strengths_areas": "Strong analytical skills, quick learner",
    "growth_areas": "Time management, asking for help earlier",
    "custom_system_prompt": null
  }
}
```

**Error Cases:**
- 404: No advisor assigned yet

### 3. Update Advisor Instance Data
**File:** `api/student/update_advisor_data.php`
**Method:** POST
**Auth:** Student (updates own advisor data)

**Request:**
```json
{
  "conversation_turn": {
    "role": "student",
    "message": "I'm having trouble with trigonometry",
    "metadata": {"course_id": 4}
  },
  "progress_notes": "Updated progress on trigonometry",
  "test_result": {
    "test_name": "Trig Unit Test",
    "course_id": 4,
    "score": 92,
    "max_score": 100,
    "feedback": "Excellent work on the identities!"
  },
  "strengths_areas": "Strong conceptual understanding",
  "growth_areas": "More practice on inverse functions"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Advisor instance data updated",
  "advisor_data": {
    "advisor_instance_id": 42,
    "last_interaction": "2024-01-20 16:15:00",
    "conversation_history": [...],
    "progress_notes": "...",
    "testing_results": [...],
    "strengths_areas": "...",
    "growth_areas": "..."
  }
}
```

**Behavior:**
- `conversation_turn`: Appends to `conversation_history` array with timestamp
- `test_result`: Appends to `testing_results` array, auto-calculates percentage
- `progress_notes`, `strengths_areas`, `growth_areas`: Replace existing text
- Always updates `last_interaction` timestamp

### 4. List Student Advisor Instances (Admin)
**File:** `api/admin/list_student_advisors.php`
**Method:** GET
**Auth:** Admin only
**Query Parameters:**
- `student_id` (optional): Filter by specific student
- `advisor_type_id` (optional): Filter by advisor template
- `is_active` (optional): Filter by active status (0 or 1)
- `limit` (optional): Results per page (default: 100)
- `offset` (optional): Pagination offset (default: 0)

**Request:**
```
GET /api/admin/list_student_advisors.php?is_active=1&limit=20
```

**Response (200):**
```json
{
  "success": true,
  "instances": [
    {
      "advisor_instance_id": 42,
      "student_id": 5,
      "advisor_type_id": 1,
      "created_at": "2024-01-20 14:30:00",
      "last_interaction": "2024-01-20 16:15:00",
      "is_active": 1,
      "username": "john_doe",
      "email": "john@school.edu",
      "agent_name": "Professor Hawkeinstein",
      "agent_type": "student_advisor",
      "total_instances": 1
    }
  ],
  "pagination": {
    "total": 47,
    "limit": 20,
    "offset": 0,
    "page": 1
  }
}
```

## Data Isolation Features

### 1. Conversation History
- **What:** Isolated per-student conversation with their advisor
- **Storage:** `conversation_history` LONGTEXT (JSON array)
- **Fields:** timestamp, role (student/advisor), message, metadata
- **Append Operation:** Automatically timestamped on each new message

### 2. Progress Tracking
- **What:** Student-specific progress notes
- **Storage:** `progress_notes` TEXT
- **Access:** Student can read/write, advisor can review per student

### 3. Test Results
- **What:** Per-student test scores and performance
- **Storage:** `testing_results` LONGTEXT (JSON array)
- **Auto-calc:** Percentage automatically calculated from score/max_score
- **Historical:** All tests kept for trend analysis

### 4. Assessments
- **Strengths:** Student's identified strengths
- **Growth Areas:** Areas for improvement (personalized per student)
- **Custom System Prompt:** Override advisor template's system prompt per student

## Implementation Notes

### Enforcing 1:1 Relationship
```sql
UNIQUE KEY unique_student_advisor (student_id)
```
This ensures:
- Exactly one advisor per student
- Prevents multiple advisor instances for same student
- Prevents accidental data mixing

### JSON Append Pattern (Conversation/Tests)
```php
// Get existing array
$existing = json_decode($current_json, true) ?: [];

// Append new item
$existing[] = [
    'timestamp' => date('Y-m-d H:i:s'),
    'field1' => $value1,
    'field2' => $value2
];

// Save back as JSON
$newJson = json_encode($existing);
```

### Agent Instance Creation Flow
```
Admin creates advisor type in Agent Factory
  ↓
Creates advisor template in agents table (is_student_advisor = 1)
  ↓
Admin creates student advisor instance via assign_student_advisor.php
  ↓
System creates unique entry in student_advisors with advisor_type_id
  ↓
Each student has separate instance (advisor_instance_id)
  ↓
Student can only see their own advisor instance
```

## Migration from Old System

**Deprecated APIs:**
- `api/admin/create_student_advisor_assignments.php` (old pattern)
- `api/student/get_advisor_assignment.php` (old pattern)

**New APIs Replace Them:**
- `api/admin/assign_student_advisor.php` → Creates advisor instance
- `api/student/get_advisor.php` → Gets advisor instance + data
- `api/student/update_advisor_data.php` → Updates advisor instance data
- `api/admin/list_student_advisors.php` → Admin management view

## Security Considerations

1. **Student Privacy:** 
   - Students can only access their own advisor instance
   - Conversation history, progress, test results completely isolated

2. **Data Integrity:**
   - Foreign key constraints prevent orphaned advisor instances
   - UNIQUE constraint prevents duplicate advisors
   - Soft delete via `is_active` flag (don't delete, deactivate)

3. **Admin Oversight:**
   - Admins can view all advisor instances for management
   - Admin action logging available (if implemented)

## Example Usage Scenarios

### Scenario 1: Assign Advisor to New Student
```
POST /api/admin/assign_student_advisor.php
{
  "student_id": 10,
  "advisor_type_id": 1  // Professor Hawkeinstein
}
```
Result: Student 10 now has unique advisor instance based on Professor Hawkeinstein template

### Scenario 2: Student Chats with Advisor
```
POST /api/student/update_advisor_data.php
{
  "conversation_turn": {
    "role": "student",
    "message": "I'm confused about calculus"
  }
}
```
Result: New message appended to student's conversation_history (timestamped)

### Scenario 3: Record Test Score
```
POST /api/student/update_advisor_data.php
{
  "test_result": {
    "test_name": "Calculus Midterm",
    "course_id": 7,
    "score": 78,
    "max_score": 100,
    "feedback": "Good effort, review derivatives"
  }
}
```
Result: Test added to testing_results, percentage auto-calculated to 78.00%

### Scenario 4: Admin Reviews Student's Advisor Data
```
GET /api/admin/list_student_advisors.php?student_id=10
```
Result: Admin sees Student 10's advisor instance, can review their isolated data

## Testing Checklist

- [ ] Create advisor instance (assign_student_advisor.php)
- [ ] Verify UNIQUE constraint prevents 2nd advisor per student
- [ ] Get advisor instance (get_advisor.php)
- [ ] Student cannot see other students' advisor instances
- [ ] Add conversation turn to advisor instance
- [ ] Verify conversation appended with timestamp
- [ ] Add test result
- [ ] Verify percentage auto-calculated
- [ ] Update progress notes
- [ ] Admin list instances with filtering
- [ ] Verify data isolation between students
