# Debug: Assessment Grading Results Inspector

## Overview
A temporary debug page and API endpoint to verify quiz and unit test grading inputs and outputs.

**Status:** ⚠️ DEBUG/TEMPORARY - For development verification only

## Usage

### View in Browser
Open in your browser:
```
http://localhost:8081/student_portal/debug_quiz_results.html
```

This page displays the most recent quiz or unit test attempt with:
- **Question** - The question text
- **Correct Answer** - The expected answer from the database
- **Student Answer** - What the student submitted
- **Grade** - The grade assigned by the grading agent
- **Points** - Points awarded

### API Endpoint
```
GET /api/progress/debug_quiz_results.php?debug=true
```

Returns JSON with the most recent assessment (quiz or unit test):
```json
{
  "success": true,
  "debug_info": "⚠️ DEBUG PAGE - Temporary assessment grading verification tool",
  "assessment_type": "Quiz",
  "metric_type": "quiz_score",
  "attempt_id": 7,
  "user_id": 4,
  "milestone": "Unit 0 - Lesson 0 Quiz",
  "score": 55.56,
  "timestamp": "2026-01-15 21:07:08",
  "summary": {
    "total_questions": 9,
    "correct": 5,
    "partial": 0,
    "incorrect": 4
  },
  "questions": [
    {
      "index": 0,
      "question": "What is an organism?",
      "type": "short_essay",
      "correct_answer": "An organism is a living thing.",
      "student_answer": "An organism is something that is alive.",
      "grade": "Correct",
      "points": 1
    }
  ]
}
```

## Files
- **API:** `api/progress/debug_quiz_results.php` - JSON endpoint
- **UI:** `student_portal/debug_quiz_results.html` - Visual inspector page

## Works For
✅ Quiz grading (metric_type: `quiz_score`)
✅ Unit test grading (metric_type: `unit_test_score`)

## Data Source
All data comes directly from the `progress_tracking` table - the same source used during live grading. No recomputation or simulation.

## What to Check
1. **Correct Answer is populated** - Should never be empty for short essay questions (after migration)
2. **Student Answer matches submission** - Verify what was sent to the model
3. **Grade matches expected result** - Check if model graded correctly
4. **Points correspond to grade** - Correct=1, Partial=0.5, Incorrect=0

## Security
- Requires `?debug=true` parameter to access
- Should only be used in development
- Remove access control if deploying to production for wider use
