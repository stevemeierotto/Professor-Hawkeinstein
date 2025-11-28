# Professor Hawkeinstein's Agent Service - C++ Backend

This is the C++ microservice that powers the AI agents for the educational platform.

## Architecture

- **HTTP Server**: Listens on port 8080 for requests from PHP frontend
- **Agent Manager**: Loads agent configurations and manages conversations
- **Ollama Client**: Communicates with local Ollama instance for LLM inference
- **RAG Engine**: Retrieves relevant context using vector similarity search
- **Database**: MariaDB connection for agent configs, memories, and embeddings

## Dependencies

Install required libraries:

```bash
sudo apt-get update
sudo apt-get install -y \
    build-essential \
    cmake \
    libcurl4-openssl-dev \
    libjsoncpp-dev \
    libmariadb-dev \
    libmariadb-dev-compat
```

## Building

### Option 1: Using Makefile

```bash
cd /home/steve/Professor_Hawkeinstein/cpp_agent

# Check if all dependencies are installed
make check-deps

# Build the service
make

# Run it
make run
```

### Option 2: Using CMake

```bash
cd /home/steve/Professor_Hawkeinstein/cpp_agent
mkdir build
cd build
cmake ..
make
./agent_service
```

## Configuration

Edit `config.json` to customize:

- **ollama_url**: Ollama API endpoint (default: http://localhost:11434)
- **model_name**: Which Ollama model to use (default: qwen2.5:3b)
- **server_port**: Port for HTTP server (default: 8080)
- **database**: MariaDB connection settings

## Running as a Service

Create systemd service file:

```bash
sudo nano /etc/systemd/system/agent-service.service
```

Add:

```ini
[Unit]
Description=Professor Hawkeinstein's Agent Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/home/steve/Professor_Hawkeinstein/cpp_agent
ExecStart=/usr/local/bin/agent_service
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable agent-service
sudo systemctl start agent-service
sudo systemctl status agent-service
```

## API Endpoints

### POST /agent/chat
Process a student message through an AI agent.

**Request:**
```json
{
  "userId": 1,
  "agentId": 1,
  "message": "Can you help me understand quadratic equations?"
}
```

**Response:**
```json
{
  "response": "Of course! Let me explain...",
  "success": true
}
```

### GET /agent/list
Get all available agents.

### POST /biometric/verify-face
Verify student identity using facial recognition.

### GET /health
Health check endpoint.

## Project Structure

```
cpp_agent/
├── src/
│   ├── main.cpp              # Entry point
│   ├── http_server.cpp       # HTTP server implementation
│   ├── agent_manager.cpp     # Agent logic and orchestration
│   ├── ollama_client.cpp     # Ollama API client
│   ├── database.cpp          # MariaDB operations
│   └── rag_engine.cpp        # Vector similarity search
├── include/
│   ├── http_server.h
│   ├── agent_manager.h
│   ├── ollama_client.h
│   ├── database.h
│   ├── rag_engine.h
│   └── config.h
├── build/                    # Build artifacts
├── bin/                      # Compiled binary
├── config.json              # Configuration file
├── CMakeLists.txt           # CMake build configuration
├── Makefile                 # Make build configuration
└── README.md                # This file
```

## Testing

Test Ollama connection:

```bash
curl http://localhost:11434/api/tags
```

Test the service:

```bash
# Health check
curl http://localhost:8080/health

# Send a message
curl -X POST http://localhost:8080/agent/chat \
  -H "Content-Type: application/json" \
  -d '{
    "userId": 1,
    "agentId": 1,
    "message": "Hello, Professor!"
  }'
```

## Directory Strategy

- **Development**: `/home/steve/Professor_Hawkeinstein/` - Source code, git repository
- **Production**: `/var/www/html/Professor_Hawkeinstein/` - Web-accessible files only (HTML, CSS, JS, PHP)
- **C++ Service**: Runs separately from Apache, compiled from development directory

The C++ agent service should **NOT** be in the web directory. It runs as an independent service and communicates with PHP via HTTP on port 8080.

## Next Steps

1. Configure database credentials in `config.json`
2. Ensure Ollama is running: `ollama serve`
3. Build the service: `make`
4. Import database schema from `/home/steve/Professor_Hawkeinstein/schema.sql`
5. Create test agents in the database
6. Run the service and test endpoints
