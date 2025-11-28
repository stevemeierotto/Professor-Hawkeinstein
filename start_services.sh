#!/bin/bash
# Start all required services for the educational platform
# Hardened version with proper health checks and startup sequencing

set -e  # Exit on any error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WAIT_SCRIPT="$SCRIPT_DIR/scripts/wait_for_services.sh"

echo "=== Starting Professor Hawkeinstein Educational Platform Services ==="
echo

# Kill any existing instances
echo "Stopping existing services..."
pkill -9 llama-server 2>/dev/null || true
pkill -9 agent_service 2>/dev/null || true
sleep 2

# Start llama-server
echo "Starting llama-server on port 8090..."
export LD_LIBRARY_PATH="/home/steve/Professor_Hawkeinstein/llama.cpp/build/bin:$LD_LIBRARY_PATH"
nohup /home/steve/Professor_Hawkeinstein/llama.cpp/build/bin/llama-server \
    -m /home/steve/Professor_Hawkeinstein/models/qwen2.5-1.5b-instruct-q4_k_m.gguf \
    --port 8090 \
    --ctx-size 4096 \
    --n-predict 512 \
    --threads 4 \
    --threads-batch 4 \
    --cache-reuse 256 \
    --parallel 2 \
    --cont-batching \
    > /tmp/llama_server.log 2>&1 &

LLAMA_PID=$!
echo "llama-server started with PID: $LLAMA_PID"

# Wait for llama-server to become healthy (120s timeout)
echo "Waiting for llama-server to become healthy..."
if ! "$WAIT_SCRIPT" "llama-server" "http://localhost:8090/health" 120; then
    echo "✗ llama-server failed to become healthy"
    echo "Check logs at: /tmp/llama_server.log"
    exit 1
fi
echo "✓ llama-server is healthy and ready"

# Start C++ agent service (depends on llama-server being healthy)
echo ""
echo "Starting C++ agent service on port 8080..."
nohup /home/steve/Professor_Hawkeinstein/cpp_agent/bin/agent_service \
    > /tmp/agent_service_full.log 2>&1 &

AGENT_PID=$!
echo "agent_service started with PID: $AGENT_PID"

# Wait for agent service to become healthy (60s timeout)
echo "Waiting for agent service to become healthy..."
if ! "$WAIT_SCRIPT" "agent-service" "http://localhost:8080/health" 60; then
    echo "✗ agent service failed to become healthy"
    echo "Check logs at: /tmp/agent_service_full.log"
    exit 1
fi
echo "✓ agent service is healthy and ready"

echo
echo "=== All services started successfully! ==="
echo
echo "Services:"
echo "  - llama-server: http://localhost:8090"
echo "  - agent service: http://localhost:8080"
echo "  - web application: http://localhost/Professor_Hawkeinstein"
echo
echo "Logs:"
echo "  - llama-server: /tmp/llama_server.log"
echo "  - agent service: /tmp/agent_service_full.log"
echo
