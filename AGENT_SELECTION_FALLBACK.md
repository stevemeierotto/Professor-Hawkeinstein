# Agent Selection - Fallback and Error Handling Documentation

**Date:** November 27, 2025  
**Purpose:** Define behavior when agent instance is missing, inactive, or unavailable

---

## Overview

The agent selection system implements robust fallback mechanisms to ensure students always have a functional experience, even when preferred agents are unavailable.

---

## Error Scenarios and Handling

### 1. Agent Not Found (404)

**Scenario:** Agent ID does not exist in database

**Backend Behavior:**
- `api/agent/set_active.php` returns:
  ```json
  {
    "success": false,
    "message": "Agent not found"
  }
  ```
  HTTP Status: 404

- `api/agent/chat.php` returns:
  ```json
  {
    "success": false,
    "message": "Agent not found"
  }
  ```
  HTTP Status: 404

**Frontend Behavior:**
- Logs warning to console: `[Agents] Failed to track selection: 404`
- Does NOT block UI interaction (non-critical)
- Chat shows error message: "Sorry, I encountered an error: Agent not found"
- User can select a different agent

**Recovery:**
- Student should select a different agent from the available list
- Admin should verify agent exists in database

---

### 2. Agent Inactive (is_active = 0)

**Scenario:** Agent exists but is marked inactive

**Backend Behavior:**
- `api/agent/set_active.php` returns:
  ```json
  {
    "success": false,
    "message": "Agent is inactive"
  }
  ```
  HTTP Status: 400

- `api/agent/list.php` filters out inactive agents (WHERE is_active = 1)
- `api/agent/chat.php` rejects with 404 if agent is inactive

**Frontend Behavior:**
- Agent does NOT appear in sidebar agent list (filtered by backend)
- If somehow selected, logs warning: `[Agents] Backend tracking error (non-critical)`
- Chat shows: "Agent not found"

**Recovery:**
- Admin must reactivate agent via Agent Management panel
- Student automatically sees updated agent list on next page load

---

### 3. C++ Agent Service Offline

**Scenario:** `http://localhost:8080` is unreachable

**Backend Behavior:**
- PHP `api/agent/chat.php` calls `callAgentService()` which throws exception
- Returns:
  ```json
  {
    "success": false,
    "message": "Agent communication failed"
  }
  ```
  HTTP Status: 500

**Frontend Behavior:**
- Status indicator shows: `● Offline` (red)
- Advisor status: "Agent service offline" (red)
- Chat shows: "Sorry, I'm having trouble connecting to the agent service right now."
- UI polls every 15 seconds for service recovery

**Recovery:**
- Restart agent service: `./start_services.sh`
- Check logs: `/tmp/agent_service_full.log`
- Verify llama-server is running on port 8090

---

### 4. Database Connection Failed

**Scenario:** MariaDB unavailable or connection timeout

**Backend Behavior:**
- All endpoints catch exception and return:
  ```json
  {
    "success": false,
    "message": "Failed to fetch agents" // or similar
  }
  ```
  HTTP Status: 500

**Frontend Behavior:**
- Agent list shows: "Unable to load agents" (red text)
- Chat attempts fail with generic error message
- No UI crash (graceful degradation)

**Recovery:**
- Restart MariaDB: `sudo systemctl restart mariadb`
- Verify credentials in `config/database.php`
- Check disk space and database logs

---

### 5. Student Has No Advisor Instance

**Scenario:** Student logs in but no `student_advisors` entry exists

**Backend Behavior:**
- `api/student/ensure_advisor.php` automatically creates advisor instance
- Uses transaction-wrapped logic with race condition protection
- Returns created advisor data

**Frontend Behavior:**
- Shows: `● Loading` (yellow) while advisor loads
- Status: "Loading advisor data..." (gray)
- Waits up to 5 seconds for advisor creation
- If still no advisor: "No advisor assigned. Please contact administration."

**Recovery:**
- Auto-recovery via `ensure_advisor.php` (preferred)
- Manual: Admin uses "Assign Student Advisor" in admin panel

---

### 6. Token Expired or Invalid

**Scenario:** JWT token expired or tampered with

**Backend Behavior:**
- `requireAuth()` catches invalid token
- Returns:
  ```json
  {
    "success": false,
    "message": "Invalid or expired token"
  }
  ```
  HTTP Status: 401

**Frontend Behavior:**
- Logs out user automatically
- Redirects to login page: `window.location.href = 'login.html'`

**Recovery:**
- User logs in again
- New JWT token issued with fresh expiration

---

### 7. Model File Missing (llama-server)

**Scenario:** GGUF model file not found by llama-server

**Backend Behavior:**
- C++ agent service receives error from llama-server
- `callAgentService()` returns:
  ```json
  {
    "success": false,
    "error": "Model loading failed"
  }
  ```

**Frontend Behavior:**
- Chat shows: "Sorry, I encountered an error: Model loading failed"
- Logs error to console with full details

**Recovery:**
- Verify model exists: `ls -lh models/*.gguf`
- Check llama-server logs: `tail -f /tmp/llama_server.log`
- Update agent's `model_name` in database to valid model
- Restart services: `./start_services.sh`

---

## Fallback Hierarchy

When agent selection fails, the system follows this priority:

1. **Primary:** Selected agent by student
2. **Fallback 1:** Student's assigned advisor instance
3. **Fallback 2:** Default agent (agent_id = 1, "Professor Hawkeinstein")
4. **Fallback 3:** Error message with guidance to contact admin

**Implementation:**
```javascript
// Student dashboard fallback logic
if (!agents.find(a => a.id === activeAgentId)) {
    // Selected agent not available, use default
    activeAgentId = 1; // Professor Hawkeinstein
    console.warn('[Agents] Selected agent unavailable, using default');
}
```

---

## Logging

All agent selection events are logged for debugging:

### Backend Logs
- **PHP error_log:** `/var/log/apache2/error.log` or `/tmp/php_errors.log`
- **Agent service:** `/tmp/agent_service_full.log`
- **llama-server:** `/tmp/llama_server.log`

**Example log entries:**
```
[Set Active] User 5 selected agent Professor Hawkeinstein (ID: 1)
[Agent Chat] Updated last_active for agent 1
[Agents] Selection tracked: {"success":true,"agent":{"id":1,"name":"Professor Hawkeinstein"}}
```

### Frontend Logs
- **Browser console:** All agent selection, routing, and error events
- **Format:** `[Agents]`, `[Agent Chat]`, `[Advisor]` prefixes

**Example console output:**
```
[Agents] Selecting agent: 1
[Agents] Recording agent selection on backend...
[Agents] Selection tracked: {success: true, agent: {...}}
[Agent Chat] Calling /agent/chat with payload: {userId: 5, agentId: 1, message: "Hello"}
```

---

## Testing Fallback Behavior

### Manual UI Checks

1. **Test Agent Selection:**
   - Select different agents in sidebar
   - Verify active card updates (blue border, "✓ Active" badge)
   - Verify chat header updates with correct agent name/emoji

2. **Test Inactive Agent:**
   - Admin: Set agent to inactive
   - Student: Verify agent disappears from list
   - Student: Cannot select inactive agent

3. **Test Missing Agent:**
   - Select agent, then delete from database
   - Send chat message
   - Verify error message displayed
   - Verify UI doesn't crash

4. **Test Service Offline:**
   - Stop agent service: `pkill -9 agent_service`
   - Verify status indicator: "● Offline"
   - Send message, verify error handling
   - Restart service, verify recovery

5. **Test Network Failure:**
   - Disconnect network
   - Select agent, send message
   - Verify graceful error messages
   - Reconnect, verify recovery

### Automated Test

Run integration test:
```bash
php tests/agent_selection_test.php
```

Expected output:
```
✅ ALL TESTS PASSED
- Agent exists and is active
- Set active agent logic validated
- last_active timestamp updated
- Agent selection persists correctly
- Chat routes to correct agent
- Inactive agents correctly rejected
- Missing agents correctly handled
```

---

## Monitoring Recommendations

### Production Monitoring

1. **Health Check Endpoint:**
   - Monitor: `http://localhost:8080/health`
   - Expected: `{"status": "ok"}`
   - Alert if down > 30 seconds

2. **Agent Selection Rate:**
   - Query: `SELECT agent_id, COUNT(*) FROM admin_activity_log WHERE action = 'AGENT_SELECTED' GROUP BY agent_id`
   - Track popular agents for capacity planning

3. **Error Rate:**
   - Monitor PHP error logs for "Agent communication failed"
   - Alert if > 5% of requests fail

4. **last_active Monitoring:**
   - Query: `SELECT agent_name, last_active FROM agents WHERE last_active < DATE_SUB(NOW(), INTERVAL 7 DAY)`
   - Identify unused agents for cleanup

---

## Admin Actions for Error Recovery

### Common Issues and Fixes

| Issue | Admin Action | Location |
|-------|-------------|----------|
| Agent inactive | Enable agent | Admin Agents > Edit > is_active = true |
| Student has no advisor | Assign advisor | Admin Dashboard > Assign Student Advisor |
| Agent service offline | Restart service | SSH: `./start_services.sh` |
| Model missing | Upload model | Place in `models/` directory |
| Database corruption | Restore from backup | Use MariaDB backup procedures |

---

## Future Enhancements

1. **Automatic Failover:** If primary agent unavailable, automatically route to backup agent
2. **Circuit Breaker:** Temporarily disable failing agents to prevent cascade failures  
3. **Rate Limiting:** Prevent abuse of agent switching (max 10 switches/minute)
4. **Analytics Dashboard:** Visual monitoring of agent selection patterns and errors
5. **Graceful Degradation:** Queue messages when service offline, process on recovery

---

## Related Files

- **Frontend:** `student_dashboard.html` (lines 400-433 - selectAgent)
- **Backend:** `api/agent/set_active.php` (agent selection endpoint)
- **Backend:** `api/agent/chat.php` (chat routing)
- **Database:** `schema.sql` (agents table)
- **Tests:** `tests/agent_selection_test.php` (integration test)
- **Migration:** `migrations/add_agent_last_active.sql` (last_active column)

---

**Contact:** System Administrator  
**Last Updated:** November 27, 2025
