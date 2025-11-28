# Memory Policy Quick Reference

## System Architecture

**TWO MEMORY SYSTEMS** - Completely Isolated

### 1. Advisor Memory (Per-Student)
- **Table:** `student_advisors`
- **Field:** `conversation_history` (JSON)
- **Access:** Student can only read/write their own
- **Constraint:** `UNIQUE KEY unique_student_advisor (student_id)`
- **APIs:** `get_advisor.php`, `update_advisor_data.php`

### 2. Expert Agent Memory (Shared)
- **Table:** `agent_memories`
- **Fields:** `user_message`, `agent_response`, `context_used`
- **Access:** Multi-agent, filtered by `agent_id` + `user_id`
- **APIs:** `chat.php` (lines 90-92)

---

## Access Control Rules

| Agent Type | Can Read Advisor Memory | Can Write Advisor Memory | Can Read Expert Memory | Can Write Expert Memory |
|-----------|------------------------|-------------------------|----------------------|------------------------|
| **Student Advisor** | ✅ Own student only | ✅ Own student only | ❌ No | ❌ No |
| **Expert Agent** | ❌ No | ❌ No | ✅ Yes (filtered) | ✅ Yes |
| **Summarizer Agent** | ❌ No | ❌ No | ❌ No | ❌ No (uses scraped_content) |

---

## Authentication Flow

```
1. Client sends request with JWT token
   Authorization: Bearer <token>

2. API calls requireAuth()
   - Extracts token from header
   - Validates signature with JWT_SECRET
   - Checks expiration
   - Returns: ['userId', 'username', 'role']

3. API uses authenticated userId
   WHERE student_id = ? (bound to userId)

4. Database enforces isolation
   UNIQUE constraint, foreign keys
```

---

## Test Coverage

| Test | Purpose | Status |
|------|---------|--------|
| **Test 1** | Advisor read isolation | ✅ 2/2 |
| **Test 2** | Advisor write isolation | ✅ 2/2 |
| **Test 3** | Expert agent isolation | ✅ 2/2 |
| **Test 4** | Summarizer isolation | ✅ 2/2 |
| **Test 5** | Unique advisor constraint | ✅ 1/1 |
| **Test 6** | API access control | ✅ 2/2 |
| **Test 7** | Agent type enforcement | ✅ 1/1 |
| **Test 8** | Architecture validation | ✅ 2/2 |

**Total:** 14/14 PASSED

---

## Key Files

### API Endpoints
- `api/student/get_advisor.php` - Read advisor memory
- `api/student/update_advisor_data.php` - Write advisor memory
- `api/agent/chat.php` - Expert agent interactions
- `api/admin/summarize_content.php` - Content summarization

### Authentication
- `config/database.php` - `requireAuth()`, `verifyToken()`

### Tests
- `tests/memory_policy_tests.php` - 14 comprehensive tests

### Documentation
- `MEMORY_POLICY_AUDIT_REPORT.json` - Full audit report
- `MEMORY_POLICY_AUDIT_SUMMARY.md` - Executive summary
- `ADVISOR_INSTANCE_API.md` - API documentation

---

## Common Queries

### Get Student's Advisor Memory
```php
$stmt = $db->prepare("
    SELECT conversation_history 
    FROM student_advisors 
    WHERE student_id = ? AND is_active = 1
");
$stmt->execute([$studentId]);
```

### Update Advisor Memory
```php
$history[] = [
    'timestamp' => date('Y-m-d H:i:s'),
    'role' => 'student',
    'message' => $message,
    'metadata' => $metadata
];

$stmt = $db->prepare("
    UPDATE student_advisors 
    SET conversation_history = ?
    WHERE student_id = ?
");
$stmt->execute([json_encode($history), $studentId]);
```

### Expert Agent Memory Write
```php
$stmt = $db->prepare("
    INSERT INTO agent_memories 
    (agent_id, user_id, interaction_type, user_message, agent_response)
    VALUES (?, ?, 'chat', ?, ?)
");
$stmt->execute([$agentId, $userId, $userMsg, $response]);
```

---

## Security Checklist

- [x] JWT authentication on all endpoints
- [x] Parameterized queries (SQL injection prevention)
- [x] userId extracted from authenticated token
- [x] WHERE clauses use authenticated userId
- [x] UNIQUE constraint prevents duplicate advisors
- [x] Foreign keys maintain referential integrity
- [x] No cross-student data access
- [x] Expert agents isolated from advisor memory
- [x] Summarizer isolated from student data

---

## Run Tests

```bash
cd /home/steve/Professor_Hawkeinstein
php tests/memory_policy_tests.php
```

Expected: `✅ All tests passed!`

---

**Status:** All policies enforced ✅  
**Last Audit:** January 20, 2024  
**Next Review:** As needed (system is secure)
