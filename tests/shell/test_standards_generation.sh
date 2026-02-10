#!/bin/bash
# Test standards generation with new agent prompts

echo "Testing Standards Generation..."
echo "==============================="
echo ""

# Get admin token
echo "1. Logging in as admin..."
TOKEN=$(curl -s -X POST http://localhost:8081/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}' | jq -r '.token')

if [ "$TOKEN" = "null" ] || [ -z "$TOKEN" ]; then
  echo "❌ Login failed"
  exit 1
fi

echo "✅ Login successful"
echo ""

# Generate standards
echo "2. Generating standards for 3rd Grade Science..."
RESPONSE=$(curl -s -X POST http://localhost:8081/api/admin/generate_standards.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"subject":"Science","grade":"3rd Grade"}')

echo ""
echo "Response:"
echo "$RESPONSE" | jq '.'
echo ""

# Check if successful
SUCCESS=$(echo "$RESPONSE" | jq -r '.success')
if [ "$SUCCESS" = "true" ]; then
  COUNT=$(echo "$RESPONSE" | jq -r '.count')
  echo "✅ Success! Generated $COUNT standards"
  
  # Show first standard
  echo ""
  echo "First standard:"
  echo "$RESPONSE" | jq -r '.standards[0]'
else
  echo "❌ Generation failed"
  echo "$RESPONSE" | jq -r '.message'
fi
