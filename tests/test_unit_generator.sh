#!/bin/bash
# Test script for Unit Generator Mode
# Usage: ./test_unit_generator.sh

echo "=== Testing Unit Generator Mode ==="
echo ""

# Configuration
API_URL="http://localhost/basic_educational/api/admin/generate_unit.php"
TOKEN="YOUR_ADMIN_JWT_TOKEN_HERE"

# Test payload - Generate a complete unit with 5 lessons
PAYLOAD='{
  "courseId": "algebra_fundamentals",
  "subject": "Algebra",
  "level": "High School",
  "unitNumber": 1,
  "unitTitle": "Foundations of Algebra",
  "standards": [
    {
      "code": "CCSS.MATH.HSA.SSE.A.1",
      "description": "Interpret expressions that represent a quantity in terms of its context"
    },
    {
      "code": "CCSS.MATH.HSA.SSE.B.3",
      "description": "Choose and produce an equivalent form of an expression to reveal properties"
    },
    {
      "code": "CCSS.MATH.HSA.REI.A.1",
      "description": "Explain each step in solving an equation"
    }
  ],
  "lessonCount": 5,
  "difficulty": "beginner",
  "createBackup": true
}'

echo "Generating complete unit with 5 lessons..."
echo "This will take 2-5 minutes depending on LLM speed."
echo ""
echo "Request payload:"
echo "$PAYLOAD" | jq '.'
echo ""
echo "Starting generation..."
echo ""

# Show progress indicator
(
  while true; do
    echo -n "."
    sleep 2
  done
) &
PROGRESS_PID=$!

# Make request
START_TIME=$(date +%s)
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")
END_TIME=$(date +%s)

# Stop progress indicator
kill $PROGRESS_PID 2>/dev/null
wait $PROGRESS_PID 2>/dev/null

echo ""
echo ""
ELAPSED=$((END_TIME - START_TIME))
echo "Request completed in $ELAPSED seconds"
echo ""

# Check if response is valid JSON
if echo "$RESPONSE" | jq empty 2>/dev/null; then
  SUCCESS=$(echo "$RESPONSE" | jq -r '.success')
  
  if [ "$SUCCESS" = "true" ]; then
    echo "✅ UNIT GENERATION SUCCESSFUL"
    echo ""
    
    # Extract key metrics
    UNIT_NUM=$(echo "$RESPONSE" | jq -r '.unitNumber')
    UNIT_TITLE=$(echo "$RESPONSE" | jq -r '.unitTitle')
    REQUESTED=$(echo "$RESPONSE" | jq -r '.lessonsRequested')
    GENERATED=$(echo "$RESPONSE" | jq -r '.lessonsGenerated')
    SAVED=$(echo "$RESPONSE" | jq -r '.lessonsSaved')
    GEN_TIME=$(echo "$RESPONSE" | jq -r '.generationTime')
    BACKUP=$(echo "$RESPONSE" | jq -r '.backupPath')
    
    echo "📚 Unit Information:"
    echo "   Unit Number: $UNIT_NUM"
    echo "   Unit Title: $UNIT_TITLE"
    echo ""
    echo "📊 Generation Statistics:"
    echo "   Lessons Requested: $REQUESTED"
    echo "   Lessons Generated: $GENERATED"
    echo "   Lessons Saved: $SAVED"
    echo "   Generation Time: ${GEN_TIME}s"
    echo ""
    
    if [ "$BACKUP" != "null" ]; then
      echo "💾 Backup: $BACKUP"
      echo ""
    fi
    
    # Show saved lessons
    echo "✅ Saved Lessons:"
    echo "$RESPONSE" | jq -r '.savedLessons[] | "   \(.lessonNumber). \(.title) [\(.action)]"'
    echo ""
    
    # Show failures if any
    FAILURE_COUNT=$(echo "$RESPONSE" | jq '.failures | length')
    if [ "$FAILURE_COUNT" -gt 0 ]; then
      echo "⚠️  Failures ($FAILURE_COUNT):"
      echo "$RESPONSE" | jq -r '.failures[] | "   Lesson \(.lessonNumber): \(.error) (stage: \(.stage))"'
      echo ""
    fi
    
    # Save lessons to individual files for inspection
    LESSON_DIR="generated_lessons_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$LESSON_DIR"
    
    LESSON_COUNT=$(echo "$RESPONSE" | jq '.lessons | length')
    for i in $(seq 0 $((LESSON_COUNT - 1))); do
      LESSON_NUM=$((i + 1))
      echo "$RESPONSE" | jq ".lessons[$i]" > "$LESSON_DIR/lesson_${LESSON_NUM}.json"
    done
    
    echo "📁 Individual lesson files saved to: $LESSON_DIR/"
    echo ""
    
    # Show full response
    echo "Full Response:"
    echo "$RESPONSE" | jq '.'
    
  else
    echo "❌ UNIT GENERATION FAILED"
    echo ""
    ERROR=$(echo "$RESPONSE" | jq -r '.error')
    echo "Error: $ERROR"
    echo ""
    echo "Full Response:"
    echo "$RESPONSE" | jq '.'
  fi
else
  echo "❌ INVALID JSON RESPONSE"
  echo ""
  echo "Raw response:"
  echo "$RESPONSE"
fi

echo ""
echo "=========================================="
echo ""
echo "To verify saved lessons, check:"
echo "  api/course/courses/course_algebra_fundamentals.json"
echo ""
echo "To verify backup, check:"
echo "  api/course/backups/"
