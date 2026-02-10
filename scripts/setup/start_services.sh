#!/bin/bash
# Start all required services for the educational platform
# Hardened version with proper health checks and startup sequencing
#
# MULTI-MODEL SUPPORT:
# - Set MULTI_MODEL=1 to load multiple models on different ports (requires more RAM)
# - Default: single model mode (MULTI_MODEL=0) - all agents use ACTIVE_MODEL
#
# To enable multi-model: MULTI_MODEL=1 ./start_services.sh

set -e  # Exit on any error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
WAIT_SCRIPT="$PROJECT_ROOT/scripts/wait_for_services.sh"

# Configuration - change these to switch models
MULTI_MODEL=${MULTI_MODEL:-0}  # Set to 1 to enable multi-model mode
# ACTIVE_MODEL="llama-2-7b-chat.Q4_0.gguf"  # Model to use in single-model mode
ACTIVE_MODEL="qwen2.5-1.5b-instruct-q4_k_m.gguf"  # Uncomment to use Qwen instead

echo "=== Starting Professor Hawkeinstein Educational Platform Services ==="
echo

# Kill any existing instances
echo "Stopping existing services..."
pkill -9 llama-server 2>/dev/null || true
pkill -9 agent_service 2>/dev/null || true
sleep 2

export LD_LIBRARY_PATH="/home/steve/Professor_Hawkeinstein/llama.cpp/build/bin:$LD_LIBRARY_PATH"

if [ "$MULTI_MODEL" = "1" ]; then
    echo "*** MULTI-MODEL MODE ENABLED ***"
    echo ""
    
    # Start llama-server for Qwen model (port 8090 - fast model)
    echo "Starting llama-server for Qwen on port 8090..."
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
        > /tmp/llama_server_qwen.log 2>&1 &

    LLAMA_QWEN_PID=$!
    echo "llama-server (Qwen) started with PID: $LLAMA_QWEN_PID"

    # Wait for Qwen server to become healthy
    echo "Waiting for llama-server (Qwen) to become healthy..."
    if ! "$WAIT_SCRIPT" "llama-server-qwen" "http://localhost:8090/health" 120; then
        echo "✗ llama-server (Qwen) failed to become healthy"
        echo "Check logs at: /tmp/llama_server_qwen.log"
        exit 1
    fi
    echo "✓ llama-server (Qwen) is healthy and ready on port 8090"

    # Start llama-server for Llama-2 model (port 8091 - larger/smarter model)
    echo ""
    echo "Starting llama-server for Llama-2 on port 8091..."
    nohup /home/steve/Professor_Hawkeinstein/llama.cpp/build/bin/llama-server \
        -m /home/steve/Professor_Hawkeinstein/models/llama-2-7b-chat.Q4_0.gguf \
        --port 8091 \
        --ctx-size 4096 \
        --n-predict 512 \
        --threads 4 \
        --threads-batch 4 \
        --cache-reuse 256 \
        --parallel 2 \
        --cont-batching \
        > /tmp/llama_server_llama2.log 2>&1 &

    LLAMA_LLAMA2_PID=$!
    echo "llama-server (Llama-2) started with PID: $LLAMA_LLAMA2_PID"

    # Wait for Llama-2 server to become healthy
    echo "Waiting for llama-server (Llama-2) to become healthy..."
    if ! "$WAIT_SCRIPT" "llama-server-llama2" "http://localhost:8091/health" 180; then
        echo "✗ llama-server (Llama-2) failed to become healthy"
        echo "Check logs at: /tmp/llama_server_llama2.log"
        exit 1
    fi
    echo "✓ llama-server (Llama-2) is healthy and ready on port 8091"
else
    echo "*** SINGLE-MODEL MODE (default) ***"
    echo "Active model: $ACTIVE_MODEL"
    echo "(Set MULTI_MODEL=1 to enable multi-model mode)"
    echo ""
    
    # Start single llama-server on port 8090
    echo "Starting llama-server on port 8090..."
    nohup /home/steve/Professor_Hawkeinstein/llama.cpp/build/bin/llama-server \
        -m /home/steve/Professor_Hawkeinstein/models/$ACTIVE_MODEL \
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

    # Wait for server to become healthy (longer timeout for larger models)
    echo "Waiting for llama-server to become healthy..."
    if ! "$WAIT_SCRIPT" "llama-server" "http://localhost:8090/health" 180; then
        echo "✗ llama-server failed to become healthy"
        echo "Check logs at: /tmp/llama_server.log"
        exit 1
    fi
    echo "✓ llama-server is healthy and ready on port 8090"
fi

# Start C++ agent service (depends on llama-server(s) being healthy)
echo ""
echo "Starting C++ agent service on port 8080..."
nohup /home/steve/Professor_Hawkeinstein/app/cpp_agent/bin/agent_service \
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
if [ "$MULTI_MODEL" = "1" ]; then
    echo "Mode: MULTI-MODEL"
    echo ""
    echo "Services:"
    echo "  - llama-server (Qwen):   http://localhost:8090 (fast, 1.5B params)"
    echo "  - llama-server (Llama2): http://localhost:8091 (smart, 7B params)"
    echo "  - agent service:         http://localhost:8080"
    echo "  - web application:       http://localhost/Professor_Hawkeinstein"
    echo ""
    echo "Logs:"
    echo "  - llama-server (Qwen):   /tmp/llama_server_qwen.log"
    echo "  - llama-server (Llama2): /tmp/llama_server_llama2.log"
    echo "  - agent service:         /tmp/agent_service_full.log"
else
    echo "Mode: SINGLE-MODEL ($ACTIVE_MODEL)"
    echo ""
    echo "Services:"
    echo "  - llama-server:    http://localhost:8090"
    echo "  - agent service:   http://localhost:8080"
    echo "  - web application: http://localhost/Professor_Hawkeinstein"
    echo ""
    echo "Logs:"
    echo "  - llama-server:    /tmp/llama_server.log"
    echo "  - agent service:   /tmp/agent_service_full.log"
    echo ""
    echo "To enable multi-model mode: MULTI_MODEL=1 ./start_services.sh"
fi
echo
