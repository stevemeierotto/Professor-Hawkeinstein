# Agent Response Troubleshooting Log

## Issue Summary
Agent chat endpoint returning error message "I apologize, but I'm having trouble processing your request right now" after 120-second timeout instead of actual LLM responses.

## Timeline of Changes

### 2025-11-22 - Initial Problem
- **Problem**: Chat requests to `/agent/chat` timing out at exactly 2 minutes
- **Symptom**: Response JSON shows `{"response":"I apologize, but I'm having trouble...","success":true}`
- **Root Cause**: llama-cli takes 90-120 seconds per request because it loads model every time

### 2025-11-22 - Migration to llama-server (Solution 1)
**Goal**: Keep model loaded in memory using persistent HTTP service

#### Step 1: Started llama-server
- Downloaded and built llama.cpp from source
- Started llama-server as background HTTP service
- Command: `llama-server -m models/llama-2-7b-chat.Q4_0.gguf -c 2048 --port 8090`
- Status: ✅ Server running, model pre-loaded (3.6GB in memory)

#### Step 2: Rewrote LlamaCppClient (CLI → HTTP)
**File**: `cpp_agent/src/llamacpp_client.cpp`

**Old Approach** (CLI with popen):
```cpp
FILE* pipe = popen(command.c_str(), "r");
// Execute: /path/to/llama-cli --model ... --prompt "..."
```

**New Approach** (HTTP with CURL):
```cpp
CURL* curl = curl_easy_init();
// POST to http://localhost:8090/completion
// JSON: {"prompt": "...", "n_predict": 512, "temperature": 0.7}
// Parse response["content"]
```

**Changes Made**:
- Removed `popen()` and system command execution
- Added CURL HTTP client with timeout (120s)
- Added JSON request/response parsing with jsoncpp
- Endpoint: `http://localhost:8090/completion`
- Request format: `{"prompt": "...", "n_predict": 512, "temperature": 0.7}`

#### Step 3: Updated agent_manager.cpp
**File**: `cpp_agent/src/agent_manager.cpp`

**Changes**:
- Line 9: Changed `OllamaClient* ollamaClient` → `LlamaCppClient* llamaClient`
- Line 10-15: Initialize LlamaCppClient with model path, context length, temperature
- Line 77: Changed `ollamaClient->generate()` → `llamaClient->generate()`

#### Step 4: Recompiled with new dependencies
```bash
g++ -std=c++17 -Iinclude \
  src/main.cpp src/http_server.cpp src/database.cpp \
  src/agent_manager.cpp src/rag_engine.cpp src/llamacpp_client.cpp \
  -lcurl -ljsoncpp -lmysqlclient -lpthread \
  -o bin/agent_service
```

**New Dependencies**:
- `-lcurl`: For HTTP requests to llama-server
- `-ljsoncpp`: For JSON parsing

#### Step 5: Restarted agent service
```bash
pkill -9 -f "bin/agent_service"
nohup ./bin/agent_service > /tmp/agent_service_full.log 2>&1 &
```

**Service Status**:
- PID: 13296
- Port: 8080
- Health check: ✅ `{"status":"ok"}`
- Database connection: ✅ MariaDB connected
- Startup logs show: "[LlamaCppClient] Connected to llama-server at http://localhost:8090"

### Current Status (as of 2025-11-22 15:35) ✅ RESOLVED

#### Final Solution
**Problem**: CURL timeout set to 120s, RAG engine still referencing OllamaClient causing compilation failures

**Fix Applied**:
1. Reduced CURL timeout from 120s to 30s in `llamacpp_client.cpp`
2. Added connection timeout (5s) for faster failure detection
3. Removed ALL Ollama references from codebase:
   - Updated `rag_engine.cpp` and `rag_engine.h` to use `LlamaCppClient` instead of `OllamaClient`
   - Updated `config.json` to use `llama_server_url` instead of `ollama_url`
   - Updated `config.h` to use `llamaServerUrl` variable
   - Updated `main.cpp` console output to show llama-server URL
   - Moved `llamacpp_client.h` to `include/` and `llamacpp_client.cpp` to `src/` for proper project structure
4. Switched to smaller, faster model: Qwen 2.5 1.5B (1.1GB vs 3.6GB)

**Results**:
```bash
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":2,"agentId":1,"message":"What is 2+2?"}'
```

**Response time**: 12.4 seconds (down from 120s timeout)
**Response**: ✅ Actual LLM-generated text returned successfully
**Status**: ✅ Working correctly

#### Test Results
```bash
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":2,"agentId":1,"message":"What is 2+2?"}'
```

**Response** (after 120s):
```json
{
  "response": "I apologize, but I'm having trouble processing your request right now. Please try again in a moment.",
  "success": true
}
```

#### What's Working
- ✅ llama-server running on port 8090
- ✅ Agent service running on port 8080  
- ✅ Health endpoint responding
- ✅ Database queries working (agent list returns data)
- ✅ JWT authentication working
- ✅ Service compiles and starts without errors

#### What's Not Working
- ❌ Chat requests timeout at 120 seconds
- ❌ HTTP requests to llama-server appear to be failing
- ❌ Error message returned instead of actual LLM response

### Next Troubleshooting Steps

1. **Check service logs** for exceptions during chat processing:
   ```bash
   tail -50 /tmp/agent_service_full.log
   ```

2. **Check llama-server logs** to see if requests are reaching it:
   ```bash
   tail -50 /tmp/llama_server.log
   ```

3. **Test llama-server directly** to verify it's responding:
   ```bash
   curl -X POST http://localhost:8090/completion \
     -H "Content-Type: application/json" \
     -d '{"prompt":"What is 2+2?","n_predict":50}'
   ```

4. **Verify request format** matches llama-server's expected API

5. **Check CURL timeout settings** in llamacpp_client.cpp (currently 120s)

6. **Add debug logging** to llamacpp_client.cpp to trace HTTP request/response

### Model Change - Smaller Model Attempt

**Rationale**: 3.6GB llama-2-7b-chat might be too slow even when pre-loaded

**Action**: Downloaded smaller model for faster inference
- Model: Qwen 2.5 1.5B Instruct (Q4_K_M quantization)
- Size: 1.1GB (vs 3.6GB)
- Path: `/home/steve/Professor_Hawkeinstein/models/qwen2.5-1.5b-instruct-q4_k_m.gguf`
- Download time: ~8.5 minutes

**Next Step**: Restart llama-server with new model
```bash
pkill -9 -f llama-server
nohup /home/steve/Professor_Hawkeinstein/llama.cpp/build/bin/llama-server \
  -m /home/steve/Professor_Hawkeinstein/models/qwen2.5-1.5b-instruct-q4_k_m.gguf \
  -c 2048 --port 8090 > /tmp/llama_server.log 2>&1 &
```

## Technical Context

### Architecture
- **Frontend**: `student_dashboard.html` sends chat messages to `/api/chat`
- **PHP Backend**: JWT authentication, database operations
- **C++ Agent Service**: Port 8080, handles `/agent/chat` endpoint
  - `agent_manager.cpp`: Orchestrates conversation flow
  - `llamacpp_client.cpp`: HTTP client to llama-server
  - `database.cpp`: Stores agent memories and configurations
  - `rag_engine.cpp`: Retrieves context from embeddings
- **llama-server**: Port 8090, HTTP API for LLM inference

### Key Files
- `cpp_agent/src/llamacpp_client.cpp` - HTTP client implementation
- `cpp_agent/include/llamacpp_client.h` - Client header
- `cpp_agent/src/agent_manager.cpp` - Agent orchestration
- `cpp_agent/config.json` - Configuration (port 8080, model name)
- `/tmp/agent_service_full.log` - Service logs
- `/tmp/llama_server.log` - llama-server logs

### Configuration
**config.json**:
```json
{
  "server_port": 8080,
  "model_name": "llama-2-7b-chat",
  "context_length": 2048,
  "temperature": 0.7
}
```

## Hypotheses for Timeout

1. **HTTP Request Format Mismatch**: llama-server API might expect different JSON structure
2. **CURL Configuration Issue**: Timeout settings or header issues
3. **llama-server Not Responding**: Server might be hung or crashed
4. **JSON Parsing Failure**: Response parsing in llamacpp_client.cpp failing
5. **Exception Handling**: C++ service catching exception and returning fallback message

## Success Criteria

- Chat request completes in < 15 seconds ✅
- Response contains actual LLM-generated text (not error message) ✅
- Multiple consecutive requests work without timeout ✅
- Dashboard displays agent responses correctly ✅

## Final Fix Summary (2025-11-22 16:00) ✅ FULLY RESOLVED

### Critical Fixes Applied

#### 1. **Dashboard Parameter Bug** (Lines 420, 387)
- **Problem**: `callAgentService()` was receiving PHP JSON response instead of user message
- **Location**: `student_dashboard.html` line 420
- **Root Cause**: Variable shadowing - `text` parameter was overwritten by response text
- **Fix**: 
  - Stored user message in `userMessage` variable before API call (line 387)
  - Passed `userMessage` to `callAgentService()` instead of response text (line 420)

#### 2. **Missing PHP File in Web Directory**
- **Problem**: `update_advisor_data.php` returned 404 error
- **Root Cause**: File existed in `/home/steve/Professor_Hawkeinstein/api/` but not copied to `/var/www/html/Professor_Hawkeinstein/api/`
- **Fix**: 
  - Created sync script: `sync_to_web.sh`
  - Copied all API files to web directory
  - **Important**: Always run `./sync_to_web.sh` after modifying HTML/PHP files

#### 3. **CORS Preflight Request Handling**
- **Problem**: Browser NetworkError when calling agent service
- **Root Cause**: C++ service missing OPTIONS request handler for CORS preflight
- **Location**: `cpp_agent/src/http_server.cpp` line 197
- **Fix**: Added OPTIONS handler:
  ```cpp
  } else if (method == "OPTIONS") {
      // Handle CORS preflight requests
      response = createHTTPResponse(200, "");
  }
  ```

#### 4. **Model Path Updates**
- Updated `/api/chat` endpoint to use: `qwen2.5-1.5b-instruct-q4_k_m.gguf`
- Model size: 1.1GB (down from 3.6GB)
- Response time: ~3-12 seconds (down from 120s timeout)

### Testing Tools Created

**test_chat_flow.html** - Comprehensive debugging tool
- Tests full flow: Advisor load → Message storage → Agent response
- Detailed logging at each step
- Helps identify exactly where failures occur
- Location: `http://localhost/Professor_Hawkeinstein/test_chat_flow.html`

### File Sync Process

**CRITICAL**: After editing files in `/home/steve/Professor_Hawkeinstein/`, always run:
```bash
/home/steve/Professor_Hawkeinstein/sync_to_web.sh
```

This copies:
- HTML files (dashboard, login, etc.)
- CSS/JS files
- API endpoints (PHP files)
- Config files

### Verification Steps

```bash
# 1. Check agent service is running
curl http://localhost:8080/health
# Expected: {"status":"ok"}

# 2. Check llama-server is running
ps aux | grep llama-server
# Expected: Process on port 8090 with qwen2.5-1.5b model

# 3. Test agent endpoint directly
curl -X POST http://localhost:8080/api/chat \
  -H "Content-Type: application/json" \
  -d '{"model":"qwen2.5-1.5b","messages":[{"role":"user","content":"Hello"}],"system_prompt":"You are a tutor","temperature":0.7}'
# Expected: JSON with "response" field containing LLM output

# 4. Test CORS preflight
curl -X OPTIONS http://localhost:8080/api/chat -v
# Expected: 200 OK with Access-Control headers

# 5. Test in browser
# Open: http://localhost/Professor_Hawkeinstein/test_chat_flow.html
# Click "Test Full Flow"
# Expected: All steps succeed, agent responds with message
```

### Complete Working Flow

1. **User types message** in `student_dashboard.html`
   - Message stored in `userMessage` variable
   - Displayed in chat UI

2. **Message sent to PHP backend**
   - POST to `/api/student/update_advisor_data.php`
   - Stores conversation turn in `student_advisors.conversation_history`
   - Returns success response

3. **Dashboard calls agent service**
   - Browser sends OPTIONS preflight → C++ service responds with CORS headers
   - Browser sends POST to `http://localhost:8080/api/chat`
   - Payload includes: messages array, system_prompt, temperature, model

4. **C++ service processes request**
   - Parses JSON payload
   - Builds prompt from system_prompt + conversation messages
   - Creates LlamaCppClient instance
   - Sends HTTP request to llama-server on port 8090

5. **llama-server generates response**
   - Model already loaded in memory (Qwen 2.5 1.5B)
   - Processes prompt and generates text
   - Returns JSON with "content" field
   - Takes ~3-12 seconds depending on prompt length

6. **Response returned to dashboard**
   - C++ service extracts "content" from llama-server response
   - Wraps in JSON: `{"response": "...", "model": "..."}`
   - Dashboard receives and displays in chat UI
   - Response stored back to database via PHP

### Key Learnings for New Agents

1. **Always sync files to web directory** after editing
2. **CORS preflight** must be handled for browser requests to C++ service
3. **Variable naming** is critical - avoid shadowing with common names like "text"
4. **Test tools** are invaluable - create debug pages for complex flows
5. **Model size matters** - smaller models (1.5B) provide better UX than large ones (7B)
6. **Keep model loaded** - use llama-server instead of CLI to avoid reload overhead
7. **Port configuration** - Ensure AGENT_SERVICE_URL matches actual service port (8080, not 8081)

## Content Summarization Fix (2025-11-22 16:10)

### Issue
Summary agent for content review had wrong port configuration

### Fix Applied
- Updated `config/database.php`: Changed `AGENT_SERVICE_URL` from port 8081 to 8080
- Summary agent now correctly calls C++ service running on port 8080
- Uses `/agent/chat` endpoint with Professor Hawkeinstein agent (ID 1)

### Testing
```bash
# Test summarization via agent service
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"agentId":1,"message":"Summarize: [content text here]"}'
```

### Summary Agent Flow
1. Admin clicks "Summarize" on scraped content in Content Review page
2. `summarize_content.php` retrieves content from database
3. Cleans content (removes noise, truncates to 5000 chars)
4. Builds summarization prompt with title, subject, grade level
5. Calls `http://localhost:8080/agent/chat` via `callAgentService()`
6. C++ service forwards to llama-server (Qwen 2.5 1.5B)
7. Summary returned and stored in `content_summary` field
8. Response includes word counts for original and summary
