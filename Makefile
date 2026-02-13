.PHONY: help sync-web sync-web-dry test-sync clean-logs

# Default target
help:
	@echo "Professor Hawkeinstein - Build & Deployment Targets"
	@echo ""
	@echo "Available targets:"
	@echo "  make sync-web          - Deploy files to web directory"
	@echo "  make sync-web-dry      - Preview deployment changes (dry run)"
	@echo "  make sync-web-verbose  - Deploy with detailed output"
	@echo "  make test-sync         - Run sync validation tests"
	@echo "  make clean-logs        - Clean sync log files"
	@echo "  make help              - Show this help message"
	@echo ""
	@echo "Services:"
	@echo "  make start-services    - Start llama-server and agent_service"
	@echo "  make stop-services     - Stop all services"
	@echo "  make agent-build       - Rebuild C++ agent service"
	@echo ""
	@echo "Docker:"
	@echo "  make docker-up         - Start all Docker containers"
	@echo "  make docker-down       - Stop all Docker containers"
	@echo "  make docker-restart    - Restart Docker containers"
	@echo "  make docker-logs       - Show Docker logs"
	@echo "  make docker-ps         - Show running containers"
	@echo ""

# ==============================================================================
# FILE SYNC TARGETS
# ==============================================================================

# Full deployment sync
sync-web:
	@echo "🚀 Deploying to web directory..."
	@./scripts/sync_to_web.sh
	@echo ""
	@echo "✅ Deployment complete!"
	@echo "   Check /tmp/sync_to_web.log for details"

# Dry run - preview changes without applying
sync-web-dry:
	@echo "🔍 Preview mode - no files will be modified"
	@./scripts/sync_to_web.sh --dry-run

# Verbose sync with detailed output
sync-web-verbose:
	@echo "🚀 Deploying with verbose output..."
	@./scripts/sync_to_web.sh --verbose

# Sync without deleting extra files in target
sync-web-preserve:
	@echo "🚀 Deploying (preserving extra files in target)..."
	@./scripts/sync_to_web.sh --no-delete

# ==============================================================================
# TESTING
# ==============================================================================

# Run sync validation tests
test-sync:
	@echo "🧪 Running sync tests..."
	@if [ -f tests/sync.test ]; then \
		bash tests/sync.test; \
	else \
		echo "❌ tests/sync.test not found"; \
		exit 1; \
	fi

# ==============================================================================
# SERVICE MANAGEMENT
# ==============================================================================

# Start all services (llama-server + agent_service)
start-services:
	@echo "🚀 Starting services..."
	@./scripts/setup/start_services.sh

# Stop all services
stop-services:
	@echo "🛑 Stopping services..."
	@pkill -9 llama-server || true
	@pkill -9 agent_service || true
	@echo "✅ Services stopped"

# Check service health
check-services:
	@echo "🏥 Checking service health..."
	@echo -n "llama-server (8090): "
	@curl -s http://localhost:8090/health > /dev/null && echo "✅ OK" || echo "❌ DOWN"
	@echo -n "agent_service (8080): "
	@curl -s http://localhost:8080/health > /dev/null && echo "✅ OK" || echo "❌ DOWN"

# ==============================================================================
# C++ BUILD
# ==============================================================================

# Build C++ agent service
agent-build:
	@echo "🔨 Building agent service..."
	@cd app/cpp_agent && make clean && make
	@echo "✅ Build complete: app/cpp_agent/bin/agent_service"

# Clean C++ build artifacts
agent-clean:
	@echo "🧹 Cleaning C++ build..."
	@cd app/cpp_agent && make clean

# ==============================================================================
# MAINTENANCE
# ==============================================================================

# Clean log files
clean-logs:
	@echo "🧹 Cleaning log files..."
	@rm -f /tmp/sync_to_web.log
	@rm -f /tmp/llama_server.log
	@rm -f /tmp/agent_service_full.log
	@echo "✅ Logs cleaned"

# Show log file locations
show-logs:
	@echo "📋 Log file locations:"
	@echo "  Sync:         /tmp/sync_to_web.log"
	@echo "  LLM Server:   /tmp/llama_server.log"
	@echo "  Agent:        /tmp/agent_service_full.log"
	@echo ""
	@echo "Quick view commands:"
	@echo "  tail -f /tmp/sync_to_web.log"
	@echo "  tail -f /tmp/llama_server.log"
	@echo "  tail -f /tmp/agent_service_full.log"

# ==============================================================================
# GIT HELPERS
# ==============================================================================

# Show git status with helpful context
status:
	@echo "📊 Git Status:"
	@git status -s
	@echo ""
	@echo "📍 Current branch: $$(git branch --show-current)"
	@echo "🔖 Last commit: $$(git log -1 --oneline)"

# Quick commit shortcut (requires message)
commit:
	@if [ -z "$(msg)" ]; then \
		echo "❌ Usage: make commit msg='Your commit message'"; \
		exit 1; \
	fi
	@git add -A
	@git commit -m "$(msg)"
	@echo "✅ Committed: $(msg)"

# ==============================================================================
# DOCKER OPERATIONS
# ==============================================================================

# Start Docker containers
docker-up:
	@echo "🐳 Starting Docker containers..."
	@cd infra/docker && docker compose up -d
	@echo "✅ Containers started"
	@echo ""
	@echo "Services available at:"
	@echo "  - Database:        localhost:3307"
	@echo "  - Llama Server:    http://localhost:8090"
	@echo "  - Agent Service:   http://localhost:8080"
	@echo "  - PHP API:         http://localhost:8081"

# Stop Docker containers
docker-down:
	@echo "🛑 Stopping Docker containers..."
	@cd infra/docker && docker compose down
	@echo "✅ Containers stopped"

# Restart Docker containers
docker-restart:
	@echo "🔄 Restarting Docker containers..."
	@cd infra/docker && docker compose restart
	@echo "✅ Containers restarted"

# Show Docker logs
docker-logs:
	@cd infra/docker && docker compose logs -f

# Show running containers
docker-ps:
	@cd infra/docker && docker compose ps

# Rebuild and restart containers
docker-rebuild:
	@echo "🔨 Rebuilding Docker containers..."
	@cd infra/docker && docker compose build
	@echo "🚀 Starting containers..."
	@cd infra/docker && docker compose up -d
	@echo "✅ Rebuild complete"
