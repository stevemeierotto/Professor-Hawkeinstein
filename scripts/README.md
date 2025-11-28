# Service Startup Hardening

## Overview

This directory contains systemd service definitions and health check scripts to ensure proper startup sequencing for the Professor Hawkeinstein platform.

## Files

### Service Definitions
- **llama-server.service** - LLaMA inference server (port 8090)
- **agent-service.service** - C++ agent microservice (port 8080)

### Scripts
- **wait_for_services.sh** - Health check polling script with timeout

## Installation (Optional)

To use systemd services instead of manual startup:

```bash
# Copy service files to systemd directory
sudo cp scripts/llama-server.service /etc/systemd/system/
sudo cp scripts/agent-service.service /etc/systemd/system/

# Reload systemd daemon
sudo systemctl daemon-reload

# Enable services to start on boot (optional)
sudo systemctl enable llama-server.service
sudo systemctl enable agent-service.service

# Start services
sudo systemctl start llama-server.service
sudo systemctl start agent-service.service

# Check status
sudo systemctl status llama-server.service
sudo systemctl status agent-service.service
```

## Manual Startup (Recommended for Development)

Use the hardened `start_services.sh` script:

```bash
cd /home/steve/Professor_Hawkeinstein
./start_services.sh
```

This script:
1. Starts llama-server
2. Waits for llama-server health check (up to 120s)
3. Only then starts agent-service
4. Waits for agent-service health check (up to 60s)
5. Reports success or failure with log locations

## Health Check Script

The `wait_for_services.sh` script polls a health endpoint until it responds:

```bash
# Usage: ./wait_for_services.sh [service_name] [url] [timeout_seconds]

# Example: Wait for llama-server
./scripts/wait_for_services.sh llama-server http://localhost:8090/health 120

# Example: Wait for agent service
./scripts/wait_for_services.sh agent-service http://localhost:8080/health 60
```

**Features:**
- Polls every 2 seconds
- Configurable timeout
- Visual progress indicators (dots)
- Clear success/failure messages
- Non-zero exit code on failure

## Key Improvements

### Before (Fragile)
- ❌ Hardcoded 8-second sleep
- ❌ Single health check attempt
- ❌ No retry logic
- ❌ Fails if model takes longer to load

### After (Robust)
- ✅ Polls until healthy (up to 120s)
- ✅ Multiple health check attempts
- ✅ Retry with backoff
- ✅ Adapts to varying model load times
- ✅ Systemd integration with proper dependencies

## Dependency Chain

```
llama-server (port 8090)
    ↓ health check
agent-service (port 8080)
    ↓ health check
Web API (Apache/PHP)
```

## Testing

Run the startup order test:

```bash
./tests/startup_order.test
```

This test verifies:
1. Health check script timeout behavior
2. Health check success for running services
3. start_services.sh uses health checks
4. Systemd services have proper dependencies

## Logs

- **llama-server:** `/tmp/llama_server.log` or `/var/log/llama-server.log` (systemd)
- **agent-service:** `/tmp/agent_service_full.log` or `/var/log/agent-service.log` (systemd)

## Troubleshooting

### Service won't start
```bash
# Check if ports are already in use
sudo lsof -i :8090
sudo lsof -i :8080

# Check service logs
tail -f /tmp/llama_server.log
tail -f /tmp/agent_service_full.log
```

### Health check timeout
```bash
# Manually test health endpoint
curl -v http://localhost:8090/health
curl -v http://localhost:8080/health

# Check if llama-server is still loading model
ps aux | grep llama-server
```

### Systemd service failures
```bash
# Check service status
sudo systemctl status llama-server.service
sudo systemctl status agent-service.service

# View detailed logs
sudo journalctl -u llama-server.service -n 50
sudo journalctl -u agent-service.service -n 50
```

## Manual Verification Steps

After applying these changes:

1. **Stop all services:**
   ```bash
   pkill -9 llama-server
   pkill -9 agent_service
   ```

2. **Start using new script:**
   ```bash
   ./start_services.sh
   ```

3. **Observe behavior:**
   - Should see "Waiting for llama-server to become healthy..."
   - Dots appear while polling
   - Success message after health check passes
   - agent_service only starts after llama-server is healthy

4. **Verify logs:**
   ```bash
   # Should show health check polling
   tail -f /tmp/llama_server.log
   
   # Should only start after llama-server is healthy
   tail -f /tmp/agent_service_full.log
   ```

## Notes

- The systemd services use `/var/log/` instead of `/tmp/` for production logs
- Resource limits are set (8GB RAM for llama-server, 2GB for agent-service)
- Security hardening is applied (NoNewPrivileges, PrivateTmp)
- Restart policies are configured (on-failure with limits)
