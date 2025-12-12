#!/bin/bash
# Clear LLM cache by restarting llama-server container

echo "Clearing LLM cache by restarting llama-server..."
docker restart phef-llama

echo "Waiting for llama-server to be ready..."
sleep 5

# Wait for health check
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if docker exec phef-llama curl -s http://localhost:8090/health > /dev/null 2>&1; then
        echo "✓ LLM server is ready"
        exit 0
    fi
    echo "Waiting... ($((attempt+1))/$max_attempts)"
    sleep 2
    attempt=$((attempt+1))
done

echo "✗ LLM server did not become ready in time"
exit 1
