#!/bin/bash
# Test script for Full Course Generator Mode
# Usage: ./test_full_course_generator.sh

echo "=== Testing Full Course Generator ==="
echo ""

# Configuration
API_URL="http://localhost/basic_educational/api/admin/generate_full_course.php"
TOKEN="YOUR_ADMIN_JWT_TOKEN_HERE"

# Note: This test assumes you have a course outline already saved
# If not, run test_course_outline.sh first

COURSE_ID="algebra_1_test"

echo "⚠️  WARNING: This is a LONG-RUNNING operation!"
echo "   Generating a full course with 30 lessons can take 15-30 minutes."
echo ""
echo "For testing, we'll generate just the first 2 lessons."
echo ""

# Test 1: Generate first 2 lessons only
echo "Test 1: Generate first 2 lessons (Unit 1, Lessons 1-2)"
echo "======================================================="
echo ""

# Since we can't easily stop after 2 lessons without modifying the API,
# we'll test with a small course or manually create a test course with 2 lessons

echo "Generating lessons for course: $COURSE_ID"
echo ""

PAYLOAD='{
  "courseId": "algebra_1_test",
  "startUnit": 1,
  "startLesson": 1,
  "pauseOnFailure": true,
  "createBackup": true
}'

echo "Request payload:"
echo "$PAYLOAD" | jq '.'
echo ""
echo "Starting generation (this may take several minutes)..."
echo ""

# Show progress dots
(
  while true; do
    echo -n "."
    sleep 5
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
    echo "✅ FULL COURSE GENERATION SUCCESSFUL"
    echo ""
    
    # Extract metrics
    COURSE_NAME=$(echo "$RESPONSE" | jq -r '.courseName')
    TOTAL_LESSONS=$(echo "$RESPONSE" | jq -r '.totalLessons')
    PROCESSED=$(echo "$RESPONSE" | jq -r '.processedLessons')
    SUCCESSFUL=$(echo "$RESPONSE" | jq -r '.successfulLessons')
    FAILED=$(echo "$RESPONSE" | jq -r '.failedLessons')
    COMPLETED=$(echo "$RESPONSE" | jq -r '.completed')
    GEN_TIME=$(echo "$RESPONSE" | jq -r '.generationTime')
    AVG_TIME=$(echo "$RESPONSE" | jq -r '.averageTimePerLesson')
    MESSAGE=$(echo "$RESPONSE" | jq -r '.message')
    
    echo "📚 Course: $COURSE_NAME"
    echo "📊 Total Lessons: $TOTAL_LESSONS"
    echo "✅ Processed: $PROCESSED"
    echo "✅ Successful: $SUCCESSFUL"
    if [ "$FAILED" != "0" ]; then
      echo "❌ Failed: $FAILED"
    fi
    echo "🎯 Completed: $COMPLETED"
    echo "⏱️  Total Time: ${GEN_TIME}s"
    echo "⏱️  Avg Time/Lesson: ${AVG_TIME}s"
    echo ""
    echo "Message: $MESSAGE"
    echo ""
    
    # Show progress for each lesson
    echo "Lesson Progress:"
    echo "$RESPONSE" | jq -r '.progress[] | "  Unit \(.unit) Lesson \(.lesson): \(.title) - \(.status) (\(.time)s)"'
    echo ""
    
    # Show failures if any
    FAILURE_COUNT=$(echo "$RESPONSE" | jq '.failures | length')
    if [ "$FAILURE_COUNT" -gt 0 ]; then
      echo "⚠️  Failures:"
      echo "$RESPONSE" | jq -r '.failures[] | "  Unit \(.unit) Lesson \(.lesson): \(.error)"'
      echo ""
    fi
    
    # Save full response
    RESPONSE_FILE="full_course_response_$(date +%Y%m%d_%H%M%S).json"
    echo "$RESPONSE" > "$RESPONSE_FILE"
    echo "💾 Full response saved to: $RESPONSE_FILE"
    echo ""
    
  else
    echo "❌ FULL COURSE GENERATION FAILED"
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

# Test 2: Resume from specific point (if previous failed)
echo "Test 2: Resume Capability"
echo "========================="
echo ""
echo "If the previous generation stopped early, you can resume with:"
echo ""
echo "curl -X POST $API_URL \\"
echo "  -H 'Authorization: Bearer \$TOKEN' \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{"
echo "    \"courseId\": \"$COURSE_ID\","
echo "    \"startUnit\": 2,"
echo "    \"startLesson\": 3,"
echo "    \"pauseOnFailure\": false"
echo "  }'"
echo ""

echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Review generated lessons in the course file:"
echo "   api/course/courses/course_${COURSE_ID}.json"
echo ""
echo "2. Check backup in:"
echo "   api/course/backups/"
echo ""
echo "3. If failures occurred, review and fix issues, then:"
echo "   - Regenerate specific failed lessons using generate_lesson.php, or"
echo "   - Resume from last failure point using startUnit/startLesson"
echo ""
echo "4. For production use, consider:"
echo "   - Running as background job"
echo "   - Implementing progress polling endpoint"
echo "   - Adding email notifications on completion"
