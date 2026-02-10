#!/bin/bash
# Test summarization endpoint

TOKEN=$(cat <<'EOF'
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJwcm9mZXNzb3JoYXdrZWluc3RlaW4iLCJ1c2VySWQiOjEsInVzZXJuYW1lIjoicm9vdF9hZG1pbiIsInJvbGUiOiJyb290IiwiaWF0IjoxNzMyMjM0MjQyLCJleHAiOjE3MzIzMjA2NDJ9.gVmDqJkC0kq4LKqT_WFP9QZEQqKWxPLOPnDf_vEj2lE
EOF
)

# Test content for summarization
TEST_CONTENT=$(cat <<'EOF'
{
    "content_id": 1,
    "agent_id": 1
}
EOF
)

echo "Testing summarization endpoint..."
echo "Content to summarize: ID 1"
echo ""

curl -s -X POST http://localhost/Professor_Hawkeinstein/api/admin/summarize_content.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "$TEST_CONTENT" | jq .

echo ""
echo "Test complete!"
