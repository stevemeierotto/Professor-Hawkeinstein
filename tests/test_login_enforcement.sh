#!/bin/bash
# Test Script: Login Enforcement
# 
# Purpose: Demonstrate that auth_provider_required enforcement works
# Date: February 6, 2026
#
# This script tests three scenarios:
# 1. Student with no auth_provider_required → password login works ✓
# 2. Admin with auth_provider_required = 'google' → password login blocked ✗
# 3. Admin with NULL auth_provider_required → password login works ✓

echo "=========================================="
echo "Login Enforcement Test"
echo "=========================================="
echo ""

# Configuration
API_URL="http://localhost/api/auth/login.php"
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_NAME="professorhawkeinstein_platform"
DB_USER="professorhawkeinstein_user"
DB_PASS="BT1716lit"

echo "[Setup] Creating test users in database..."
echo ""

# Create test student (no auth_provider_required)
mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME <<SQL
-- Clean up any existing test users
DELETE FROM users WHERE username IN ('test_student', 'test_admin_google', 'test_admin_legacy');

-- Create test student (no restriction)
INSERT INTO users (username, email, password_hash, full_name, role, auth_provider_required)
VALUES (
    'test_student',
    'student@test.com',
    '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- 'password123'
    'Test Student',
    'student',
    NULL
);

-- Create admin with Google SSO enforced
INSERT INTO users (username, email, password_hash, full_name, role, auth_provider_required)
VALUES (
    'test_admin_google',
    'admin.google@test.com',
    '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- 'password123'
    'Test Admin (Google Required)',
    'admin',
    'google'
);

-- Create legacy admin (no restriction)
INSERT INTO users (username, email, password_hash, full_name, role, auth_provider_required)
VALUES (
    'test_admin_legacy',
    'admin.legacy@test.com',
    '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- 'password123'
    'Test Admin (Legacy)',
    'admin',
    NULL
);

SELECT 'Test users created successfully' as status;
SQL

echo ""
echo "=========================================="
echo "Test 1: Student Login (Should SUCCEED)"
echo "=========================================="
echo "Username: test_student"
echo "Password: password123"
echo "Expected: Login succeeds (no auth provider restriction)"
echo ""

RESPONSE1=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST $API_URL \
  -H "Content-Type: application/json" \
  -d '{"username":"test_student","password":"password123"}')

HTTP_CODE1=$(echo "$RESPONSE1" | grep "HTTP_CODE" | cut -d: -f2)
BODY1=$(echo "$RESPONSE1" | grep -v "HTTP_CODE")

echo "HTTP Status: $HTTP_CODE1"
echo "Response: $BODY1"

if [ "$HTTP_CODE1" == "200" ]; then
    echo "✓ PASS: Student can log in with password"
else
    echo "✗ FAIL: Student should be able to log in"
fi

echo ""
echo "=========================================="
echo "Test 2: Admin with Google Required (Should FAIL)"
echo "=========================================="
echo "Username: test_admin_google"
echo "Password: password123"
echo "Expected: Login blocked with AUTH_PROVIDER_REQUIRED error"
echo ""

RESPONSE2=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST $API_URL \
  -H "Content-Type: application/json" \
  -d '{"username":"test_admin_google","password":"password123"}')

HTTP_CODE2=$(echo "$RESPONSE2" | grep "HTTP_CODE" | cut -d: -f2)
BODY2=$(echo "$RESPONSE2" | grep -v "HTTP_CODE")

echo "HTTP Status: $HTTP_CODE2"
echo "Response: $BODY2"

if [ "$HTTP_CODE2" == "403" ] && echo "$BODY2" | grep -q "AUTH_PROVIDER_REQUIRED"; then
    echo "✓ PASS: Google-required admin blocked from password login"
else
    echo "✗ FAIL: Should have blocked with 403 and AUTH_PROVIDER_REQUIRED"
fi

echo ""
echo "=========================================="
echo "Test 3: Legacy Admin (Should SUCCEED)"
echo "=========================================="
echo "Username: test_admin_legacy"
echo "Password: password123"
echo "Expected: Login succeeds (no restriction, grandfathered in)"
echo ""

RESPONSE3=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST $API_URL \
  -H "Content-Type: application/json" \
  -d '{"username":"test_admin_legacy","password":"password123"}')

HTTP_CODE3=$(echo "$RESPONSE3" | grep "HTTP_CODE" | cut -d: -f2)
BODY3=$(echo "$RESPONSE3" | grep -v "HTTP_CODE")

echo "HTTP Status: $HTTP_CODE3"
echo "Response: $BODY3"

if [ "$HTTP_CODE3" == "200" ]; then
    echo "✓ PASS: Legacy admin can still log in with password"
else
    echo "✗ FAIL: Legacy admin should be able to log in"
fi

echo ""
echo "=========================================="
echo "Cleanup"
echo "=========================================="
mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME <<SQL
DELETE FROM users WHERE username IN ('test_student', 'test_admin_google', 'test_admin_legacy');
SELECT 'Test users deleted' as status;
SQL

echo ""
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "Test 1 (Student): $([ "$HTTP_CODE1" == "200" ] && echo "✓ PASS" || echo "✗ FAIL")"
echo "Test 2 (Google Admin): $([ "$HTTP_CODE2" == "403" ] && echo "$BODY2" | grep -q "AUTH_PROVIDER_REQUIRED" && echo "✓ PASS" || echo "✗ FAIL")"
echo "Test 3 (Legacy Admin): $([ "$HTTP_CODE3" == "200" ] && echo "✓ PASS" || echo "✗ FAIL")"
echo ""
