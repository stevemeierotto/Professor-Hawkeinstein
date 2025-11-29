#!/bin/bash
# Test script for Save Lesson API
# Usage: ./test_save_lesson.sh

echo "=== Testing Save Lesson API ==="
echo ""

# Configuration
API_URL="http://localhost/basic_educational/api/admin/save_lesson.php"
TOKEN="YOUR_ADMIN_JWT_TOKEN_HERE"

# Sample lesson to insert
LESSON_PAYLOAD='{
  "courseId": "algebra_fundamentals",
  "unitNumber": 1,
  "createBackup": true,
  "lesson": {
    "lessonNumber": 1,
    "lessonTitle": "Variables and Constants",
    "objectives": [
      "Understand the difference between variables and constants",
      "Use proper notation for algebraic expressions",
      "Evaluate expressions with given variable values"
    ],
    "explanation": "<h3>What are Variables?</h3><p>Variables are symbols (usually letters) that represent unknown or changeable values. Constants are fixed values that do not change.</p>",
    "guidedExamples": [
      {
        "title": "Evaluating an Expression",
        "problem": "Evaluate 3x + 5 when x = 4",
        "steps": [
          "Substitute x = 4 into the expression: 3(4) + 5",
          "Multiply: 12 + 5",
          "Add: 17"
        ],
        "solution": "17"
      }
    ],
    "practiceProblems": [
      {"problem": "Evaluate 2x - 7 when x = 10"},
      {"problem": "Evaluate x² + 3x when x = 5"},
      {"problem": "Evaluate (x + y) / 2 when x = 8 and y = 6"}
    ],
    "quizQuestions": [
      {
        "question": "What is the value of 5x when x = 3?",
        "type": "multiple-choice",
        "options": ["8", "15", "53", "5x"],
        "correctAnswer": "B"
      },
      {
        "question": "Variables can change their value within an expression.",
        "type": "true-false",
        "correctAnswer": "true"
      }
    ],
    "videoPlaceholder": "Introduction to Variables and Constants",
    "summary": "Variables represent changeable values using letters, while constants are fixed numbers. We can evaluate expressions by substituting values for variables.",
    "vocabulary": [
      {
        "term": "Variable",
        "definition": "A symbol that represents an unknown or changeable value"
      },
      {
        "term": "Constant",
        "definition": "A fixed value that does not change"
      }
    ],
    "estimatedDuration": 35,
    "difficulty": "beginner"
  }
}'

echo "Test 1: Insert new lesson (Lesson 1 in Unit 1)"
echo "================================================"
echo ""

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$LESSON_PAYLOAD")

if echo "$RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
  echo "✅ Test 1 PASSED"
  echo "$RESPONSE" | jq '.'
else
  echo "❌ Test 1 FAILED"
  echo "$RESPONSE"
fi

echo ""
echo "================================================"
echo ""

# Test 2: Update existing lesson
UPDATE_PAYLOAD='{
  "courseId": "algebra_fundamentals",
  "unitNumber": 1,
  "createBackup": true,
  "lesson": {
    "lessonNumber": 1,
    "lessonTitle": "Variables and Constants (Updated)",
    "objectives": [
      "Understand variables and constants",
      "Use algebraic notation",
      "Evaluate expressions",
      "Apply order of operations"
    ],
    "explanation": "<h3>Variables and Constants - Updated Version</h3><p>This is an updated lesson with additional content.</p>",
    "guidedExamples": [
      {
        "title": "Updated Example",
        "problem": "Evaluate 5x - 2 when x = 7",
        "steps": ["Substitute: 5(7) - 2", "Multiply: 35 - 2", "Subtract: 33"],
        "solution": "33"
      }
    ],
    "practiceProblems": [
      {"problem": "Evaluate 4x + 1 when x = 9"}
    ],
    "quizQuestions": [
      {
        "question": "What is 4x when x = 2?",
        "type": "multiple-choice",
        "options": ["6", "8", "42", "2x"],
        "correctAnswer": "B"
      }
    ],
    "videoPlaceholder": "Variables and Constants - Extended",
    "summary": "Updated summary with more detail.",
    "estimatedDuration": 40,
    "difficulty": "beginner"
  }
}'

echo "Test 2: Update existing lesson (same Lesson 1)"
echo "================================================"
echo ""

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$UPDATE_PAYLOAD")

if echo "$RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
  ACTION=$(echo "$RESPONSE" | jq -r '.action')
  if [ "$ACTION" = "updated" ]; then
    echo "✅ Test 2 PASSED (lesson was updated)"
  else
    echo "⚠️  Test 2 WARNING (expected 'updated', got '$ACTION')"
  fi
  echo "$RESPONSE" | jq '.'
else
  echo "❌ Test 2 FAILED"
  echo "$RESPONSE"
fi

echo ""
echo "================================================"
echo ""

# Test 3: Insert lesson into middle of sequence
MIDDLE_PAYLOAD='{
  "courseId": "algebra_fundamentals",
  "unitNumber": 2,
  "createBackup": false,
  "lesson": {
    "lessonNumber": 1,
    "lessonTitle": "Solving One-Step Equations",
    "objectives": ["Solve one-step equations using inverse operations"],
    "explanation": "One-step equations require a single operation to solve.",
    "guidedExamples": [],
    "practiceProblems": [],
    "quizQuestions": [],
    "videoPlaceholder": "One-Step Equations",
    "summary": "Use inverse operations to isolate the variable.",
    "estimatedDuration": 25,
    "difficulty": "beginner"
  }
}'

echo "Test 3: Insert lesson before existing lesson (Unit 2, Lesson 1)"
echo "================================================================="
echo ""

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$MIDDLE_PAYLOAD")

if echo "$RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
  echo "✅ Test 3 PASSED"
  echo "$RESPONSE" | jq '.'
else
  echo "❌ Test 3 FAILED"
  echo "$RESPONSE"
fi

echo ""
echo "================================================"
echo ""

# Test 4: Error handling - invalid unit
ERROR_PAYLOAD='{
  "courseId": "algebra_fundamentals",
  "unitNumber": 99,
  "lesson": {
    "lessonNumber": 1,
    "lessonTitle": "This should fail",
    "objectives": [],
    "explanation": "",
    "guidedExamples": [],
    "practiceProblems": [],
    "quizQuestions": [],
    "videoPlaceholder": "",
    "summary": ""
  }
}'

echo "Test 4: Error handling (invalid unit number)"
echo "============================================="
echo ""

RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$ERROR_PAYLOAD")

if echo "$RESPONSE" | jq -e '.success == false' > /dev/null 2>&1; then
  echo "✅ Test 4 PASSED (correctly returned error)"
  echo "$RESPONSE" | jq '.'
else
  echo "❌ Test 4 FAILED (should have returned error)"
  echo "$RESPONSE"
fi

echo ""
echo "================================================"
echo ""
echo "Testing complete!"
echo ""
echo "To verify the results, check:"
echo "  - api/course/courses/course_algebra_fundamentals.json"
echo "  - api/course/backups/ (for backup files)"
