#!/bin/bash
# Test script for Course Outline Mode
# Usage: ./test_course_outline.sh

echo "=== Testing Course Outline Generator ==="
echo ""

# Configuration
GENERATE_URL="http://localhost/basic_educational/api/admin/generate_course_outline.php"
SAVE_URL="http://localhost/basic_educational/api/admin/save_course_outline.php"
TOKEN="YOUR_ADMIN_JWT_TOKEN_HERE"

echo "Test 1: Generate course outline"
echo "================================"
echo ""

# Generate outline
GENERATE_PAYLOAD='{
  "subject": "Algebra I",
  "level": "High School",
  "unitCount": 6,
  "lessonsPerUnit": 5,
  "standardsSet": "Common Core State Standards"
}'

echo "Generating outline for Algebra I..."
echo "Request:"
echo "$GENERATE_PAYLOAD" | jq '.'
echo ""

OUTLINE_RESPONSE=$(curl -s -X POST "$GENERATE_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$GENERATE_PAYLOAD")

if echo "$OUTLINE_RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
  echo "✅ Outline generation SUCCESSFUL"
  echo ""
  
  # Extract key info
  COURSE_NAME=$(echo "$OUTLINE_RESPONSE" | jq -r '.courseName')
  TOTAL_UNITS=$(echo "$OUTLINE_RESPONSE" | jq -r '.totalUnits')
  TOTAL_LESSONS=$(echo "$OUTLINE_RESPONSE" | jq -r '.totalLessons')
  GEN_TIME=$(echo "$OUTLINE_RESPONSE" | jq -r '.generationTime')
  
  echo "📚 Course: $COURSE_NAME"
  echo "📊 Structure: $TOTAL_UNITS units, $TOTAL_LESSONS lessons"
  echo "⏱️  Generation time: ${GEN_TIME}s"
  echo ""
  
  # Show unit titles
  echo "Unit Titles:"
  echo "$OUTLINE_RESPONSE" | jq -r '.units[] | "  Unit \(.unitNumber): \(.unitTitle)"'
  echo ""
  
  # Show sample lesson titles from first unit
  echo "Sample Lessons (Unit 1):"
  echo "$OUTLINE_RESPONSE" | jq -r '.units[0].lessons[] | "  Lesson \(.lessonNumber): \(.lessonTitle)"'
  echo ""
  
  # Show standards for first unit
  echo "Standards (Unit 1):"
  echo "$OUTLINE_RESPONSE" | jq -r '.units[0].standards[]? | "  \(.code): \(.description)"' 2>/dev/null || echo "  (No standards mapped)"
  echo ""
  
  # Save outline to file
  echo "$OUTLINE_RESPONSE" > "generated_outline_$(date +%Y%m%d_%H%M%S).json"
  echo "💾 Full outline saved to generated_outline_*.json"
  echo ""
  
else
  echo "❌ Outline generation FAILED"
  echo ""
  echo "$OUTLINE_RESPONSE" | jq '.'
  exit 1
fi

echo "================================"
echo ""

echo "Test 2: Save course outline"
echo "==========================="
echo ""

# Save the generated outline
SAVE_PAYLOAD=$(jq -n \
  --arg courseId "algebra_1_test" \
  --argjson outline "$(echo "$OUTLINE_RESPONSE" | jq '.')" \
  '{courseId: $courseId, outline: $outline, overwrite: true}')

echo "Saving outline as course file..."
echo ""

SAVE_RESPONSE=$(curl -s -X POST "$SAVE_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$SAVE_PAYLOAD")

if echo "$SAVE_RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
  echo "✅ Course outline save SUCCESSFUL"
  echo ""
  
  COURSE_ID=$(echo "$SAVE_RESPONSE" | jq -r '.courseId')
  COURSE_FILE=$(echo "$SAVE_RESPONSE" | jq -r '.courseFile')
  
  echo "Course ID: $COURSE_ID"
  echo "Course File: $COURSE_FILE"
  echo ""
  echo "Full Response:"
  echo "$SAVE_RESPONSE" | jq '.'
  
else
  echo "❌ Course outline save FAILED"
  echo ""
  echo "$SAVE_RESPONSE" | jq '.'
fi

echo ""
echo "================================"
echo ""

echo "Test 3: Generate minimal outline (3 units, 3 lessons)"
echo "======================================================"
echo ""

MINIMAL_PAYLOAD='{
  "subject": "Introduction to Biology",
  "level": "Middle School",
  "unitCount": 3,
  "lessonsPerUnit": 3
}'

echo "Generating minimal outline..."

MINIMAL_RESPONSE=$(curl -s -X POST "$GENERATE_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$MINIMAL_PAYLOAD")

if echo "$MINIMAL_RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
  echo "✅ Minimal outline generation SUCCESSFUL"
  echo ""
  
  echo "Structure:"
  echo "$MINIMAL_RESPONSE" | jq -r '.units[] | "Unit \(.unitNumber): \(.unitTitle) (\(.lessons | length) lessons)"'
  echo ""
  
else
  echo "❌ Minimal outline generation FAILED"
  echo ""
  echo "$MINIMAL_RESPONSE" | jq '.'
fi

echo ""
echo "================================"
echo ""
echo "Testing complete!"
echo ""
echo "Next steps:"
echo "1. Review the generated outline in generated_outline_*.json"
echo "2. Edit if needed (change titles, descriptions, standards)"
echo "3. Use save_course_outline.php to save approved outline"
echo "4. Use generate_unit.php to generate full content for each unit"
