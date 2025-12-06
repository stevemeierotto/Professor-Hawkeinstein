#!/bin/bash
# Test outline approval endpoint

echo "Testing outline approval API..."
echo ""

# Get admin token
TOKEN=$(curl -s -X POST http://localhost/basic_educational/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}' | jq -r '.token')

if [ "$TOKEN" = "null" ] || [ -z "$TOKEN" ]; then
    echo "❌ Failed to get admin token"
    exit 1
fi

echo "✅ Got admin token"
echo ""

# Create a minimal test outline
TEST_OUTLINE='{
  "courseId": "test_mathematics_grade1",
  "subject": "Mathematics",
  "gradeLevel": "grade_1",
  "generatedAt": "2025-11-30 10:00:00",
  "units": [
    {
      "unitNumber": 1,
      "title": "Unit 1: Numbers and Operations",
      "skills": [
        {"skillId": "uuid-1", "text": "Count to 20", "code": "MATH.1.NBT.1"}
      ],
      "lessons": [
        {
          "lessonNumber": 1,
          "title": "Lesson 1: Counting",
          "objectives": ["Count to 20", "Recognize numbers"],
          "skillIds": ["uuid-1"]
        }
      ]
    }
  ]
}'

# Test outline approval
echo "📋 Approving outline..."
curl -X POST http://localhost/basic_educational/api/admin/approve_outline.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"courseId\": \"test_mathematics_grade1\",
    \"outline\": $TEST_OUTLINE
  }" | jq '.'

echo ""
echo "Test complete!"
