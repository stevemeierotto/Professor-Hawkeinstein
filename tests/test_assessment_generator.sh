#!/bin/bash
# Test script for Assessment Generator
# Usage: ./test_assessment_generator.sh

echo "=== Testing Assessment Generator ==="
echo ""

# Configuration
API_URL="http://localhost/basic_educational/api/admin/generate_assessment.php"
TOKEN="YOUR_ADMIN_JWT_TOKEN_HERE"
COURSE_ID="algebra_1_test"

echo "⚠️  Note: This test assumes you have a course with generated lessons."
echo "   If not, run test_full_course_generator.sh first."
echo ""

# Test 1: Generate Unit Test
echo "Test 1: Generate Unit Test for Unit 1"
echo "======================================"
echo ""

PAYLOAD='{
  "courseId": "'"$COURSE_ID"'",
  "assessmentType": "unit_test",
  "unitNumber": 1,
  "numQuestions": 15,
  "difficulty": "mixed",
  "includeAnswerKey": true
}'

echo "Request payload:"
echo "$PAYLOAD" | jq '.'
echo ""
echo "Generating unit test (this may take 1-2 minutes)..."
echo ""

START_TIME=$(date +%s)
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")
END_TIME=$(date +%s)

ELAPSED=$((END_TIME - START_TIME))
echo "Request completed in $ELAPSED seconds"
echo ""

# Check if response is valid JSON
if echo "$RESPONSE" | jq empty 2>/dev/null; then
  SUCCESS=$(echo "$RESPONSE" | jq -r '.success')
  
  if [ "$SUCCESS" = "true" ]; then
    echo "✅ UNIT TEST GENERATION SUCCESSFUL"
    echo ""
    
    # Extract metrics
    COURSE_NAME=$(echo "$RESPONSE" | jq -r '.courseName')
    UNIT_NUM=$(echo "$RESPONSE" | jq -r '.unitNumber')
    TOTAL_Q=$(echo "$RESPONSE" | jq -r '.totalQuestions')
    TITLE=$(echo "$RESPONSE" | jq -r '.assessment.title')
    POINTS=$(echo "$RESPONSE" | jq -r '.assessment.totalPoints')
    TIME=$(echo "$RESPONSE" | jq -r '.assessment.estimatedTime')
    LESSONS=$(echo "$RESPONSE" | jq -r '.lessonsCovered | length')
    
    echo "📚 Course: $COURSE_NAME"
    echo "📝 Unit: $UNIT_NUM"
    echo "📋 Title: $TITLE"
    echo "❓ Total Questions: $TOTAL_Q"
    echo "💯 Total Points: $POINTS"
    echo "⏱️  Estimated Time: $TIME"
    echo "📖 Lessons Covered: $LESSONS"
    echo ""
    
    # Show question breakdown
    echo "Question Types:"
    echo "$RESPONSE" | jq -r '.assessment.questions | group_by(.type) | map({type: .[0].type, count: length}) | .[] | "  \(.type): \(.count) questions"'
    echo ""
    
    # Show lessons covered
    echo "Lessons Covered:"
    echo "$RESPONSE" | jq -r '.lessonsCovered[] | "  Unit \(.unit) Lesson \(.lesson): \(.title)"'
    echo ""
    
    # Save full response
    RESPONSE_FILE="unit_test_response_$(date +%Y%m%d_%H%M%S).json"
    echo "$RESPONSE" > "$RESPONSE_FILE"
    echo "💾 Full response saved to: $RESPONSE_FILE"
    echo ""
    
    # Show sample question
    echo "Sample Question:"
    echo "$RESPONSE" | jq -r '.assessment.questions[0] | "Q\(.questionNumber): \(.question)\nType: \(.type)\nPoints: \(.points)"'
    echo ""
    
  else
    echo "❌ UNIT TEST GENERATION FAILED"
    echo ""
    ERROR=$(echo "$RESPONSE" | jq -r '.error')
    echo "Error: $ERROR"
    echo ""
  fi
else
  echo "❌ INVALID JSON RESPONSE"
  echo ""
  echo "Raw response:"
  echo "$RESPONSE"
fi

echo "=========================================="
echo ""

# Test 2: Generate Midterm
echo "Test 2: Generate Midterm (Units 1-3)"
echo "====================================="
echo ""

MIDTERM_PAYLOAD='{
  "courseId": "'"$COURSE_ID"'",
  "assessmentType": "midterm",
  "upToUnit": 3,
  "numQuestions": 30,
  "difficulty": "mixed",
  "includeAnswerKey": true
}'

echo "Request payload:"
echo "$MIDTERM_PAYLOAD" | jq '.'
echo ""
echo "Generating midterm exam (this may take 2-3 minutes)..."
echo ""

START_TIME=$(date +%s)
MIDTERM_RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$MIDTERM_PAYLOAD")
END_TIME=$(date +%s)

ELAPSED=$((END_TIME - START_TIME))
echo "Request completed in $ELAPSED seconds"
echo ""

if echo "$MIDTERM_RESPONSE" | jq empty 2>/dev/null; then
  SUCCESS=$(echo "$MIDTERM_RESPONSE" | jq -r '.success')
  
  if [ "$SUCCESS" = "true" ]; then
    echo "✅ MIDTERM GENERATION SUCCESSFUL"
    echo ""
    
    TITLE=$(echo "$MIDTERM_RESPONSE" | jq -r '.assessment.title')
    TOTAL_Q=$(echo "$MIDTERM_RESPONSE" | jq -r '.totalQuestions')
    POINTS=$(echo "$MIDTERM_RESPONSE" | jq -r '.assessment.totalPoints')
    LESSONS=$(echo "$MIDTERM_RESPONSE" | jq -r '.lessonsCovered | length')
    
    echo "📋 Title: $TITLE"
    echo "❓ Total Questions: $TOTAL_Q"
    echo "💯 Total Points: $POINTS"
    echo "📖 Lessons Covered: $LESSONS"
    echo ""
    
    # Save response
    MIDTERM_FILE="midterm_response_$(date +%Y%m%d_%H%M%S).json"
    echo "$MIDTERM_RESPONSE" > "$MIDTERM_FILE"
    echo "💾 Full response saved to: $MIDTERM_FILE"
    echo ""
    
  else
    echo "❌ MIDTERM GENERATION FAILED"
    ERROR=$(echo "$MIDTERM_RESPONSE" | jq -r '.error')
    echo "Error: $ERROR"
  fi
else
  echo "❌ INVALID JSON RESPONSE"
fi

echo "=========================================="
echo ""

# Test 3: Generate Final Exam
echo "Test 3: Generate Final Exam (All Units)"
echo "========================================"
echo ""

FINAL_PAYLOAD='{
  "courseId": "'"$COURSE_ID"'",
  "assessmentType": "final_exam",
  "numQuestions": 50,
  "difficulty": "mixed",
  "includeAnswerKey": true
}'

echo "Request payload:"
echo "$FINAL_PAYLOAD" | jq '.'
echo ""
echo "Generating final exam (this may take 3-5 minutes)..."
echo ""

START_TIME=$(date +%s)
FINAL_RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$FINAL_PAYLOAD")
END_TIME=$(date +%s)

ELAPSED=$((END_TIME - START_TIME))
echo "Request completed in $ELAPSED seconds"
echo ""

if echo "$FINAL_RESPONSE" | jq empty 2>/dev/null; then
  SUCCESS=$(echo "$FINAL_RESPONSE" | jq -r '.success')
  
  if [ "$SUCCESS" = "true" ]; then
    echo "✅ FINAL EXAM GENERATION SUCCESSFUL"
    echo ""
    
    TITLE=$(echo "$FINAL_RESPONSE" | jq -r '.assessment.title')
    TOTAL_Q=$(echo "$FINAL_RESPONSE" | jq -r '.totalQuestions')
    POINTS=$(echo "$FINAL_RESPONSE" | jq -r '.assessment.totalPoints')
    LESSONS=$(echo "$FINAL_RESPONSE" | jq -r '.lessonsCovered | length')
    
    echo "📋 Title: $TITLE"
    echo "❓ Total Questions: $TOTAL_Q"
    echo "💯 Total Points: $POINTS"
    echo "📖 Lessons Covered: $LESSONS"
    echo ""
    
    # Question distribution
    echo "Coverage by Unit:"
    echo "$FINAL_RESPONSE" | jq -r '.lessonsCovered | group_by(.unit) | map({unit: .[0].unit, lessons: length}) | .[] | "  Unit \(.unit): \(.lessons) lessons"'
    echo ""
    
    # Save response
    FINAL_FILE="final_exam_response_$(date +%Y%m%d_%H%M%S).json"
    echo "$FINAL_RESPONSE" > "$FINAL_FILE"
    echo "💾 Full response saved to: $FINAL_FILE"
    echo ""
    
  else
    echo "❌ FINAL EXAM GENERATION FAILED"
    ERROR=$(echo "$FINAL_RESPONSE" | jq -r '.error')
    echo "Error: $ERROR"
  fi
else
  echo "❌ INVALID JSON RESPONSE"
fi

echo "=========================================="
echo ""
echo "Testing Complete!"
echo ""
echo "Next steps:"
echo "1. Review generated assessment files (JSON saved above)"
echo "2. Check answer keys are complete and accurate"
echo "3. Verify questions align with lesson objectives"
echo "4. Test different difficulty levels and question types"
echo "5. Consider saving assessments to course metadata"
echo ""
echo "API Endpoints:"
echo "- Unit Test: POST $API_URL (assessmentType: unit_test)"
echo "- Midterm:   POST $API_URL (assessmentType: midterm)"
echo "- Final:     POST $API_URL (assessmentType: final_exam)"
