#!/bin/bash
# Final Analytics System Verification
# Checks all components are working in production

echo "=========================================="
echo "Analytics System Verification"
echo "=========================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_pass() {
    echo -e "${GREEN}✅ PASS${NC}: $1"
}

check_fail() {
    echo -e "${RED}❌ FAIL${NC}: $1"
}

check_warn() {
    echo -e "${YELLOW}⚠️  WARN${NC}: $1"
}

# Test 1: Database tables
echo "1. Checking database tables..."
TABLE_COUNT=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM information_schema.tables 
   WHERE table_schema='professorhawkeinstein_platform' 
   AND table_name LIKE 'analytics_%'")

if [ "$TABLE_COUNT" -eq 9 ]; then
    check_pass "All 9 analytics tables exist"
else
    check_fail "Expected 9 tables, found $TABLE_COUNT"
fi
echo ""

# Test 2: API files exist in production
echo "2. Checking production API files..."
if [ -f "/var/www/html/basic_educational/api/admin/analytics/overview.php" ]; then
    check_pass "overview.php exists in production"
else
    check_fail "overview.php missing from production"
fi

if [ -f "/var/www/html/basic_educational/api/admin/analytics/course.php" ]; then
    check_pass "course.php exists in production"
else
    check_fail "course.php missing from production"
fi

if [ -f "/var/www/html/basic_educational/api/admin/analytics/timeseries.php" ]; then
    check_pass "timeseries.php exists in production"
else
    check_fail "timeseries.php missing from production"
fi
echo ""

# Test 3: Frontend JavaScript
echo "3. Checking frontend files..."
if [ -f "/var/www/html/basic_educational/course_factory/admin_analytics.js" ]; then
    check_pass "admin_analytics.js exists in production"
else
    check_fail "admin_analytics.js missing from production"
fi

if [ -f "/var/www/html/basic_educational/course_factory/admin_analytics.html" ]; then
    check_pass "admin_analytics.html exists in production"
else
    check_fail "admin_analytics.html missing from production"
fi
echo ""

# Test 4: API endpoint accessibility
echo "4. Testing API endpoint responses..."
OVERVIEW_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  "http://localhost/api/admin/analytics/overview.php")

if [ "$OVERVIEW_CODE" -eq 401 ] || [ "$OVERVIEW_CODE" -eq 200 ]; then
    check_pass "Overview endpoint accessible (HTTP $OVERVIEW_CODE)"
else
    check_fail "Overview endpoint returned HTTP $OVERVIEW_CODE"
fi

COURSE_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  "http://localhost/api/admin/analytics/course.php")

if [ "$COURSE_CODE" -eq 401 ] || [ "$COURSE_CODE" -eq 200 ]; then
    check_pass "Course endpoint accessible (HTTP $COURSE_CODE)"
else
    check_fail "Course endpoint returned HTTP $COURSE_CODE"
fi
echo ""

# Test 5: Data availability
echo "5. Checking data in analytics tables..."
ROLLUP_COUNT=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM analytics_daily_rollup")

if [ "$ROLLUP_COUNT" -gt 0 ]; then
    check_pass "Daily rollup has $ROLLUP_COUNT records"
elif [ "$ROLLUP_COUNT" -eq 0 ]; then
    check_warn "Daily rollup is empty (run aggregate_analytics.php)"
fi

COURSE_METRICS=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM analytics_course_metrics")

if [ "$COURSE_METRICS" -gt 0 ]; then
    check_pass "Course metrics has $COURSE_METRICS records"
elif [ "$COURSE_METRICS" -eq 0 ]; then
    check_warn "Course metrics empty (expected if no aggregation run)"
fi

AGENT_METRICS=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM analytics_agent_metrics")

if [ "$AGENT_METRICS" -gt 0 ]; then
    check_pass "Agent metrics has $AGENT_METRICS records"
elif [ "$AGENT_METRICS" -eq 0 ]; then
    check_warn "Agent metrics empty (expected if no aggregation run)"
fi
echo ""

# Test 6: Check source data availability
echo "6. Checking source data tables..."
PROGRESS_COUNT=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM progress_tracking")

if [ "$PROGRESS_COUNT" -gt 0 ]; then
    check_pass "progress_tracking has $PROGRESS_COUNT records"
else
    check_warn "No progress tracking data (new system)"
fi

USER_COUNT=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM users WHERE role='student'")

if [ "$USER_COUNT" -gt 0 ]; then
    check_pass "$USER_COUNT student accounts exist"
else
    check_warn "No student accounts found"
fi

COURSE_COUNT=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM courses WHERE is_active=1")

if [ "$COURSE_COUNT" -gt 0 ]; then
    check_pass "$COURSE_COUNT active courses exist"
else
    check_warn "No active courses found"
fi
echo ""

# Test 7: Aggregation script
echo "7. Checking aggregation script..."
if [ -f "/home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php" ]; then
    check_pass "Aggregation script exists"
    
    # Check if it's executable
    if [ -x "/home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php" ]; then
        check_pass "Aggregation script is executable"
    else
        check_warn "Aggregation script not executable (chmod +x needed)"
    fi
else
    check_fail "Aggregation script missing"
fi
echo ""

# Test 8: Cron job check
echo "8. Checking cron configuration..."
CRON_EXISTS=$(sudo crontab -l 2>/dev/null | grep -c "aggregate_analytics.php" || echo "0")

if [ "$CRON_EXISTS" -gt 0 ]; then
    check_pass "Cron job configured for analytics aggregation"
else
    check_warn "No cron job found - aggregation must be run manually"
fi
echo ""

# Summary
echo "=========================================="
echo "Verification Summary"
echo "=========================================="

# Count checks
TOTAL_CRITICAL=12  # Tables, files, endpoints
PASSED=0

[ "$TABLE_COUNT" -eq 9 ] && ((PASSED++))
[ -f "/var/www/html/basic_educational/api/admin/analytics/overview.php" ] && ((PASSED++))
[ -f "/var/www/html/basic_educational/api/admin/analytics/course.php" ] && ((PASSED++))
[ -f "/var/www/html/basic_educational/api/admin/analytics/timeseries.php" ] && ((PASSED++))
[ -f "/var/www/html/basic_educational/course_factory/admin_analytics.js" ] && ((PASSED++))
[ -f "/var/www/html/basic_educational/course_factory/admin_analytics.html" ] && ((PASSED++))
[ "$OVERVIEW_CODE" -eq 401 ] || [ "$OVERVIEW_CODE" -eq 200 ] && ((PASSED++))
[ "$COURSE_CODE" -eq 401 ] || [ "$COURSE_CODE" -eq 200 ] && ((PASSED++))
[ "$USER_COUNT" -gt 0 ] && ((PASSED++))
[ "$COURSE_COUNT" -gt 0 ] && ((PASSED++))
[ -f "/home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php" ] && ((PASSED++))

echo "Critical checks passed: $PASSED / $TOTAL_CRITICAL"

if [ "$PASSED" -eq "$TOTAL_CRITICAL" ]; then
    echo -e "${GREEN}✅ System Status: FULLY OPERATIONAL${NC}"
    exit 0
elif [ "$PASSED" -ge 9 ]; then
    echo -e "${YELLOW}⚠️  System Status: OPERATIONAL (minor warnings)${NC}"
    exit 0
else
    echo -e "${RED}❌ System Status: DEGRADED${NC}"
    exit 1
fi
