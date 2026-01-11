This project uses strict AI agent instructions.

READ AND FOLLOW:
.github/copilot-instructions

These rules are mandatory.
---

## Deployment Methods

**TWO WAYS TO RUN THE SYSTEM:**

### 1. Docker (Recommended)
```bash
docker compose up -d
```
- Isolated, containerized services
- Best for production and testing
- Model configured in `docker-compose.yml` line 39
- Database runs in container (port 3307 external)

### 2. Native (Development)
```bash
./start_services.sh
```
- Direct process execution on host
- Better for debugging and development
- Model configured in `start_services.sh` line 18
- Uses local Apache/PHP/MariaDB stack

**⚠️ IMPORTANT:** 
- These are SEPARATE deployment methods
- Do NOT mix them (don't run both at once)
- Choose one based on your use case
- Model changes require editing different files

### Switching Models

**Docker deployment:**
```bash
# Edit docker-compose.yml line 39
environment:
  - MODEL_FILE=qwen2.5-1.5b-instruct-q4_k_m.gguf

# Restart
docker compose down
docker compose up -d
```

**Native deployment:**
```bash
# Edit start_services.sh line 18
ACTIVE_MODEL="qwen2.5-1.5b-instruct-q4_k_m.gguf"

# Restart
./start_services.sh
```

### Available Models
- `qwen2.5-1.5b-instruct-q4_k_m.gguf` - Fast, lightweight (1.5B params)
- `llama-2-7b-chat.Q4_0.gguf` - Larger, slower (7B params)