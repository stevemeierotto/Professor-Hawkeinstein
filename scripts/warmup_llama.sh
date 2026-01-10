#!/bin/bash
# Warmup script for llama-server to improve first-request performance
# This sends a minimal prompt to populate caches

echo "Waiting for llama-server to be ready..."
until curl -s http://localhost:8090/health > /dev/null 2>&1; do
    sleep 2
done

echo "llama-server is ready. Sending warmup request..."
curl -s -X POST http://localhost:8090/completion \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Hello",
    "n_predict": 10,
    "temperature": 0.7,
    "stop": ["\n"]
  }' > /dev/null

echo "Warmup complete! Model is now cached and ready for fast responses."
