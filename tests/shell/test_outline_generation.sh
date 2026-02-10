#!/bin/bash
# Test outline generation endpoint

echo "Testing outline generation API..."
echo ""

# Get admin token first
TOKEN=$(curl -s -X POST http://localhost/basic_educational/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}' | jq -r '.token')

if [ "$TOKEN" = "null" ] || [ -z "$TOKEN" ]; then
    echo "❌ Failed to get admin token"
    exit 1
fi

echo "✅ Got admin token"
echo ""

# Test outline generation (assuming storeId 1 exists)
echo "📋 Generating outline..."
curl -X POST http://localhost/basic_educational/api/admin/generate_outline_from_skills.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "courseId": "test_mathematics_grade1",
    "simplifiedSkillsStoreId": 1,
    "numUnits": 3,
    "lessonsPerUnit": 4
  }' | jq '.'

echo ""
echo "Test complete!"
