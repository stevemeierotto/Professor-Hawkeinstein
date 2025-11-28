#!/bin/bash
# Health check script - polls services until they are ready
# Usage: ./wait_for_services.sh [service_name] [url] [timeout_seconds]

set -e

SERVICE_NAME="${1:-llama-server}"
HEALTH_URL="${2:-http://localhost:8090/health}"
TIMEOUT="${3:-120}"
INTERVAL=2

echo "Waiting for $SERVICE_NAME to become healthy..."
echo "Health check URL: $HEALTH_URL"
echo "Timeout: ${TIMEOUT}s"

elapsed=0
while [ $elapsed -lt $TIMEOUT ]; do
    if curl -sf "$HEALTH_URL" > /dev/null 2>&1; then
        response=$(curl -s "$HEALTH_URL")
        if echo "$response" | grep -q "ok\|healthy\|ready"; then
            echo "✓ $SERVICE_NAME is healthy (took ${elapsed}s)"
            exit 0
        fi
    fi
    
    echo -n "."
    sleep $INTERVAL
    elapsed=$((elapsed + INTERVAL))
done

echo ""
echo "✗ $SERVICE_NAME failed to become healthy within ${TIMEOUT}s"
echo "Last health check attempt failed at: $HEALTH_URL"
exit 1
