#!/bin/bash
# Test Analytics Endpoints
# This tests that analytics APIs return valid data

echo "=== Testing Analytics Endpoints ==="
echo ""

# Get root admin token (assuming root user exists)
echo "1. Getting admin token..."
LOGIN_RESPONSE=$(curl -s -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Chandra1!"}' 2>&1)

TOKEN=$(echo "$LOGIN_RESPONSE" | grep -oP '"token":"\K[^"]+' || echo "")

if [ -z "$TOKEN" ]; then
    echo "❌ Failed to get admin token"
    echo "Response: $LOGIN_RESPONSE"
    exit 1
fi

echo "✅ Got admin token"
echo ""

# Test overview endpoint
echo "2. Testing /api/admin/analytics/overview.php..."
OVERVIEW_RESPONSE=$(curl -s "http://localhost/api/admin/analytics/overview.php?startDate=2025-12-01&endDate=2026-01-18" \
  -H "Authorization: Bearer $TOKEN" 2>&1)

if echo "$OVERVIEW_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Overview endpoint working"
    echo "Students: $(echo "$OVERVIEW_RESPONSE" | grep -oP '"totalStudents":\K[0-9]+' || echo "0")"
    echo "Courses: $(echo "$OVERVIEW_RESPONSE" | grep -oP '"activeCourses":\K[0-9]+' || echo "0")"
else
    echo "❌ Overview endpoint failed"
    echo "Response: $OVERVIEW_RESPONSE"
fi
echo ""

# Test course analytics
echo "3. Testing /api/admin/analytics/course.php..."
COURSE_RESPONSE=$(curl -s "http://localhost/api/admin/analytics/course.php" \
  -H "Authorization: Bearer $TOKEN" 2>&1)

if echo "$COURSE_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Course analytics endpoint working"
    COURSE_COUNT=$(echo "$COURSE_RESPONSE" | grep -o '"course_id"' | wc -l)
    echo "Courses returned: $COURSE_COUNT"
else
    echo "❌ Course analytics endpoint failed"
    echo "Response: $COURSE_RESPONSE"
fi
echo ""

# Test timeseries
echo "4. Testing /api/admin/analytics/timeseries.php..."
TIMESERIES_RESPONSE=$(curl -s "http://localhost/api/admin/analytics/timeseries.php?startDate=2025-12-01&endDate=2026-01-18&period=daily" \
  -H "Authorization: Bearer $TOKEN" 2>&1)

if echo "$TIMESERIES_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Timeseries endpoint working"
else
    echo "❌ Timeseries endpoint failed"
    echo "Response: $TIMESERIES_RESPONSE"
fi
echo ""

echo "=== Analytics Test Complete ==="
