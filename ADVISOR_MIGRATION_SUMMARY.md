# Advisor System Migration - Quick Reference

## What Changed

### Database Schema
| Old | New |
|-----|-----|
| `assigned_student_count` in agents table | ❌ REMOVED |
| `student_advisor_assignments` table | ❌ DROPPED |
| New: `student_advisors` table | ✅ CREATED |

### Student Advisors Table
```
advisor_instance_id (PK) - unique ID per student's advisor instance
student_id (UNIQUE FK) - one advisor per student
advisor_type_id (FK) - references agent template
conversation_history (LONGTEXT JSON) - isolated per student
progress_notes (TEXT) - isolated per student
testing_results (LONGTEXT JSON) - isolated per student
strengths_areas (TEXT) - personalized
growth_areas (TEXT) - personalized
custom_system_prompt (TEXT) - can override template
created_at, last_interaction (TIMESTAMP)
is_active (TINYINT)
```

### API Changes

#### OLD API (Deprecated)
```
POST /api/admin/student_advisor_assignments.php
GET  /api/student/get_advisor_assignment.php
```

#### NEW APIs (Implemented)
```
POST /api/admin/assign_student_advisor.php
     - Creates advisor instance for student
     - Body: {student_id, advisor_type_id}
     
GET  /api/student/get_advisor.php
     - Gets student's advisor instance + all data
     - Auth: Student (own instance only)
     
POST /api/student/update_advisor_data.php
     - Updates conversation, progress, tests, etc.
     - Auto-appends conversation & test results
     
GET  /api/admin/list_student_advisors.php
     - Admin view of all instances
     - Filters: student_id, advisor_type_id, is_active
```

## Key Concepts

### 1. Advisor TEMPLATES vs INSTANCES
```
TEMPLATE (agents table):
  agent_id = 1
  agent_name = "Professor Hawkeinstein"
  is_student_advisor = 1

INSTANCES (student_advisors table):
  Student 5:  advisor_instance_id = 42 (based on agent 1)
  Student 10: advisor_instance_id = 43 (based on agent 1)
  Student 12: advisor_instance_id = 44 (based on agent 1)
  
  Same template, different instances, different data!
```

### 2. Data Isolation
```
Student 5's advisor:
  - conversation_history: [student 5's messages]
  - progress_notes: [student 5's progress]
  - testing_results: [student 5's test scores]

Student 10's advisor:
  - conversation_history: [student 10's messages]
  - progress_notes: [student 10's progress]
  - testing_results: [student 10's test scores]
  
  ✅ Zero data leakage between students
```

### 3. Enforcing 1:1 Relationship
```sql
UNIQUE KEY unique_student_advisor (student_id)

Result:
  ✅ Exactly one advisor per student
  ❌ Cannot create 2nd advisor for same student
  ❌ Database enforces constraint automatically
```

## Usage Examples

### Create Advisor Instance
```bash
curl -X POST http://localhost/api/admin/assign_student_advisor.php \
  -H "Content-Type: application/json" \
  -d '{
    "student_id": 5,
    "advisor_type_id": 1
  }'

Response:
{
  "success": true,
  "advisor_instance": {
    "advisor_instance_id": 42,
    "student_id": 5,
    "advisor_type_id": 1,
    "advisor_name": "Professor Hawkeinstein",
    "created_at": "2024-01-20 14:30:00"
  }
}
```

### Get Student's Advisor Instance
```bash
curl -X GET http://localhost/api/student/get_advisor.php \
  -H "Authorization: Bearer [token]"

Response:
{
  "success": true,
  "advisor_instance": {
    "advisor_instance_id": 42,
    "student_id": 5,
    "advisor_type_id": 1,
    "agent_name": "Professor Hawkeinstein",
    "conversation_history": [...],
    "progress_notes": "John is excelling...",
    "testing_results": [...]
  }
}
```

### Update Advisor Data
```bash
curl -X POST http://localhost/api/student/update_advisor_data.php \
  -H "Content-Type: application/json" \
  -d '{
    "conversation_turn": {
      "role": "student",
      "message": "I need help with algebra"
    },
    "test_result": {
      "test_name": "Algebra Unit 1",
      "score": 88,
      "max_score": 100,
      "feedback": "Great work!"
    }
  }'

Response:
{
  "success": true,
  "advisor_data": {
    "advisor_instance_id": 42,
    "conversation_history": [
      {... existing messages ...},
      {
        "timestamp": "2024-01-20 16:45:00",
        "role": "student",
        "message": "I need help with algebra"
      }
    ],
    "testing_results": [
      {... previous tests ...},
      {
        "timestamp": "2024-01-20 16:45:00",
        "test_name": "Algebra Unit 1",
        "score": 88,
        "max_score": 100,
        "percentage": 88.00,
        "feedback": "Great work!"
      }
    ]
  }
}
```

### List All Advisor Instances (Admin)
```bash
curl -X GET http://localhost/api/admin/list_student_advisors.php \
  -H "Authorization: Bearer [admin_token]"

Response:
{
  "success": true,
  "instances": [
    {
      "advisor_instance_id": 42,
      "student_id": 5,
      "username": "john_doe",
      "advisor_type_id": 1,
      "agent_name": "Professor Hawkeinstein",
      "created_at": "2024-01-20 14:30:00",
      "last_interaction": "2024-01-20 16:45:00",
      "is_active": 1
    }
  ],
  "pagination": {"total": 1, "limit": 100, "page": 1}
}
```

## Error Scenarios

### Scenario: Student already has advisor
```
POST /api/admin/assign_student_advisor.php (student 5 again)
Response 409:
{
  "error": "Student already has an advisor instance. Only one advisor per student allowed."
}
```

### Scenario: Invalid advisor type
```
POST /api/admin/assign_student_advisor.php (advisor_type_id=999)
Response 404:
{
  "error": "Agent not found or is not a student advisor type"
}
```

### Scenario: Student has no advisor
```
GET /api/student/get_advisor.php (student with no instance)
Response 404:
{
  "error": "No advisor assigned",
  "message": "You do not have a student advisor assigned yet. Please contact administration."
}
```

## Admin Management Interface

### To Create an Advisor Instance for a Student
1. Admin navigates to Agent Factory
2. Creates "Student Advisor" template (e.g., "Professor Hawkeinstein")
3. Gets advisor_type_id from created agent
4. Calls `POST /api/admin/assign_student_advisor.php`
   ```
   {
     "student_id": X,
     "advisor_type_id": Y
   }
   ```
5. Student now has isolated advisor instance

### To View Student's Advisor Data
1. Admin calls `GET /api/admin/list_student_advisors.php?student_id=X`
2. Gets advisor_instance_id, creation date, last interaction
3. Returns summary of advisor instance (for full data, see student's get_advisor.php)

### To Deactivate Advisor (Soft Delete)
```sql
UPDATE student_advisors SET is_active = 0 WHERE advisor_instance_id = X;
```
This preserves all historical data while marking advisor as inactive.

## Files Modified/Created

### Modified
- `/api/admin/assign_student_advisor.php` - Renamed, rewritten for new pattern
- `/api/student/get_advisor.php` - Updated for student_advisors table

### Created
- `/api/student/update_advisor_data.php` - NEW: Update advisor instance data
- `/api/admin/list_student_advisors.php` - NEW: Admin instance listing
- `/ADVISOR_INSTANCE_API.md` - Full API documentation

### Database
```sql
-- Executed
ALTER TABLE agents DROP COLUMN assigned_student_count;
DROP TABLE IF EXISTS student_advisor_assignments;
CREATE TABLE student_advisors (
  advisor_instance_id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL UNIQUE,
  advisor_type_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_interaction TIMESTAMP NULL,
  is_active TINYINT(1) DEFAULT 1,
  conversation_history LONGTEXT,
  progress_notes TEXT,
  testing_results LONGTEXT,
  strengths_areas TEXT,
  growth_areas TEXT,
  custom_system_prompt TEXT,
  FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (advisor_type_id) REFERENCES agents(agent_id) ON DELETE CASCADE,
  UNIQUE KEY unique_student_advisor (student_id)
);
```

## System Architecture

```
┌─────────────────────────────┐
│   Agent Factory (Admin UI)   │
│  Create advisor templates    │
└──────────────┬──────────────┘
               │ (creates agents.is_student_advisor=1)
               ▼
        ┌─────────────┐
        │ agents      │  TEMPLATES
        │ (templates) │  "Professor Hawkeinstein"
        └─────────────┘
               │
               │ (references via advisor_type_id)
               ▼
   ┌──────────────────────────┐
   │  student_advisors        │  INSTANCES (1 per student)
   │  ┌────────────────────┐  │
   │  │ Student 5:         │  │  ┌──────────────────┐
   │  │ advisor_instance=42│──→  │ conversation_... │
   │  │ advisor_type=1     │  │  │ progress_notes   │
   │  │ (Prof. Hawkstein)  │  │  │ testing_results  │
   │  └────────────────────┘  │  └──────────────────┘
   │                          │
   │  ┌────────────────────┐  │
   │  │ Student 10:        │  │  ┌──────────────────┐
   │  │ advisor_instance=43│──→  │ conversation_... │
   │  │ advisor_type=1     │  │  │ progress_notes   │
   │  │ (Prof. Hawkstein)  │  │  │ testing_results  │
   │  └────────────────────┘  │  └──────────────────┘
   └──────────────────────────┘
```

## Summary

✅ **Before:** Shared agents, data mixed together, assignment table
✅ **After:** Per-student advisor instances, completely isolated data
✅ **Constraint:** UNIQUE on student_id enforces 1-to-1 relationship
✅ **APIs:** Full set for creating, reading, updating advisor instances
✅ **Security:** Students can only access their own instance
✅ **Admin:** Can manage and view all instances
