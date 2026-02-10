# 🚀 Quick Start - Agent Pipeline Testing

## Current Status
✅ Agent system prompts restored  
✅ Docker database connectivity fixed  
✅ Agent 5 (Standards Analyzer) tested and working  
⏭️ Ready to test remaining pipeline agents  

---

## Test Commands (Copy-Paste Ready)

### 1. Get Admin Token
```bash
TOKEN=$(curl -s -X POST http://localhost:8081/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"root","password":"Root1234"}' | jq -r '.token')
echo "Token: $TOKEN"
```

### 2. Test Standards Generation (Agent 5) ✅ WORKING
```bash
curl -s -X POST http://localhost:8081/api/admin/generate_standards.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "subject": "Science",
    "grade": "3rd Grade"
  }' | jq '.'
```

**Expected**: JSON array with 8-12 standards, each having id/statement/skills

### 3. Test Outline Generation (Agent 6)
```bash
# First, generate standards and capture them
STANDARDS=$(curl -s -X POST http://localhost:8081/api/admin/generate_standards.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"subject":"Science","grade":"3rd Grade"}' | jq -c '.standards')

# Then generate outline from standards
curl -s -X POST http://localhost:8081/api/admin/generate_draft_outline.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"standards\": $STANDARDS,
    \"subject\": \"Science\",
    \"grade\": \"3rd Grade\"
  }" | jq '.'
```

**Expected**: JSON with units array containing lessons

### 4. Test Lesson Content Generation (Agent 18)
```bash
curl -s -X POST http://localhost:8081/api/admin/generate_lesson_content.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "lesson_id": 1,
    "lesson_title": "Understanding Living Organisms",
    "lesson_description": "Students will learn about the basic structure of living organisms",
    "standard": "Students will understand the basic structure of living organisms.",
    "grade": "3rd Grade",
    "subject": "Science"
  }' | jq '.'
```

**Expected**: 800-1000 words of educational content

### 5. Test Question Generation (Agent 19)
```bash
curl -s -X POST http://localhost:8081/api/admin/generate_lesson_questions.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "lesson_content": "Living organisms are made up of cells. Cells are the smallest units of life...",
    "lesson_title": "Understanding Living Organisms",
    "grade": "3rd Grade"
  }' | jq '.'
```

**Expected**: Structured questions in QUESTION/ANSWER/EXPLANATION format

---

## One-Line Test Script
```bash
/home/steve/Professor_Hawkeinstein/test_standards_generation.sh
```

---

## Troubleshooting

### If login fails ({"success": false}):
```bash
# Check API database connection
docker logs phef-api 2>&1 | tail -5

# Verify environment variables
docker exec phef-api env | grep DB_

# Should see: DB_HOST=database, DB_PORT=3306
```

### If agent doesn't respond:
```bash
# Check agent service
docker logs phef-agent 2>&1 | tail -20

# Check llama-server
docker logs phef-llama 2>&1 | tail -10

# Restart if needed
docker-compose restart agent-service
```

### If JSON parsing fails:
```bash
# Check Apache error log for actual agent response
docker logs phef-api 2>&1 | grep "Agent response" | tail -3
```

---

## Quick Verification

### All services healthy:
```bash
docker-compose ps
```
All should be "Up" and "healthy"

### Agent prompts loaded:
```bash
docker exec phef-database mysql -u professorhawkeinstein_user -pBT1716lit \
  professorhawkeinstein_platform -e \
  "SELECT agent_id, agent_name, SUBSTRING(system_prompt, 1, 50) 
   FROM agents WHERE agent_type='system' AND agent_id IN (5,6,18,19,22);"
```

---

## Files Reference

| File | Purpose |
|------|---------|
| `insert_system_agents.sql` | Agent prompts SQL (for restoration) |
| `test_standards_generation.sh` | Automated standards test |
| `AGENT_SYSTEM_PROMPT_RESTORATION.md` | Full restoration documentation |
| `SYSTEM_STATUS_2026-01-03.md` | Complete system status |
| `DEPLOYMENT_CHECKLIST.md` | Deployment procedures |

---

## Next Test Goal

Test Agent 6 (Outline Generator) to verify it can:
1. Parse standards JSON from Agent 5
2. Generate units/lessons structure
3. Link lessons to standard codes
4. Output valid JSON for next agent

**Command**: See "Test Outline Generation" above

---

## Success Criteria

✅ Standards: 8-12 items with id/statement/skills  
⏭️ Outline: 3-5 units with 3-5 lessons each  
⏭️ Lesson Content: 800-1000 words, age-appropriate  
⏭️ Questions: Multiple questions with answers/explanations  
⏭️ Complete Pipeline: Full course generation working  

---

## Important Notes

- **Always use Docker MySQL**: Port 3307 from host, 3306 from containers
- **Agent responses take 5-10 seconds**: This is normal for LLM inference
- **JSON format is strict**: Agents now output exact formats specified in prompts
- **Test incrementally**: Test each agent before full pipeline
