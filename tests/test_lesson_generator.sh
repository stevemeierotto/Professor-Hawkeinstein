#!/bin/bash
# Test script for Lesson Generator Mode
# Usage: ./test_lesson_generator.sh

echo "=== Testing Lesson Generator Mode ==="
echo ""

# Configuration
API_URL="http://localhost/basic_educational/api/admin/generate_lesson.php"
TOKEN="YOUR_ADMIN_JWT_TOKEN_HERE"

# Test payload
PAYLOAD='{
  "subject": "Algebra",
  "level": "High School",
  "unitNumber": 1,
  "lessonNumber": 2,
  "lessonTitle": "Order of Operations",
  "unitTitle": "Foundations of Algebra",
  "standards": [
    {
      "code": "CCSS.MATH.6.EE.A.1",
      "description": "Write and evaluate numerical expressions involving whole-number exponents"
    },
    {
      "code": "CCSS.MATH.6.EE.A.2.C",
      "description": "Evaluate expressions at specific values of their variables"
    }
  ],
  "prerequisites": ["Basic arithmetic", "Understanding of variables"],
  "estimatedDuration": 50,
  "difficulty": "beginner"
}'

echo "Sending request to: $API_URL"
echo "Payload:"
echo "$PAYLOAD" | jq '.'
echo ""
echo "Generating lesson (this may take 30-60 seconds)..."
echo ""

# Make request
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")

# Check if response is valid JSON
if echo "$RESPONSE" | jq empty 2>/dev/null; then
  echo "✅ Response received:"
  echo "$RESPONSE" | jq '.'
  
  # Save lesson to file if successful
  if echo "$RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
    LESSON_FILE="generated_lesson_$(date +%Y%m%d_%H%M%S).json"
    echo "$RESPONSE" | jq '.lesson' > "$LESSON_FILE"
    echo ""
    echo "✅ Lesson saved to: $LESSON_FILE"
    echo ""
    echo "Lesson statistics:"
    echo "  - Objectives: $(jq '.lesson.objectives | length' <<< "$RESPONSE")"
    echo "  - Guided Examples: $(jq '.lesson.guidedExamples | length' <<< "$RESPONSE")"
    echo "  - Practice Problems: $(jq '.lesson.practiceProblems | length' <<< "$RESPONSE")"
    echo "  - Quiz Questions: $(jq '.lesson.quizQuestions | length' <<< "$RESPONSE")"
    echo "  - Vocabulary Terms: $(jq '.lesson.vocabulary | length' <<< "$RESPONSE")"
  else
    echo "❌ Lesson generation failed"
  fi
else
  echo "❌ Invalid JSON response:"
  echo "$RESPONSE"
fi
