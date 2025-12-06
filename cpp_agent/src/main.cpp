#include <iostream>
#include <thread>
#include <string>
#include <csignal>
#include <atomic>
#include "../include/http_server.h"
#include "../include/agent_manager.h"
#include "../include/config.h"

std::atomic<bool> running(true);

void signalHandler(int signum) {
    std::cout << "\nShutdown signal received (" << signum << ")..." << std::endl;
    running = false;
}

int main(int argc, char* argv[]) {
    // Set up signal handlers
    signal(SIGINT, signalHandler);
    signal(SIGTERM, signalHandler);

    std::cout << "Professor Hawkeinstein's Educational Foundation - Agent Service" << std::endl;
    std::cout << "================================================================" << std::endl;
    
    // Load configuration - try Docker path first, then local path
    Config config;
    if (!config.load("/app/config.json")) {
        if (!config.load("/home/steve/Professor_Hawkeinstein/cpp_agent/config.json")) {
            std::cerr << "Warning: Could not load config, using defaults" << std::endl;
        }
    }
    
    std::cout << "llama-server URL: http://localhost:8090" << std::endl;
    std::cout << "Model: " << config.modelName << std::endl;
    std::cout << "Database: " << config.dbName << std::endl;
    std::cout << "Listening on port: " << config.serverPort << std::endl;
    
    try {
        // Initialize agent manager
        AgentManager agentManager(config);
        
        // Start HTTP server
        HTTPServer server(config.serverPort, agentManager, config);
        server.start();
        
        std::cout << "Agent service started successfully!" << std::endl;
        std::cout << "Press Ctrl+C to stop..." << std::endl;
        
        // Keep running until signal received
        while (running) {
            std::this_thread::sleep_for(std::chrono::seconds(1));
        }
        
        // Graceful shutdown
        std::cout << "Stopping server..." << std::endl;
        server.stop();
        
    } catch (const std::exception& e) {
        std::cerr << "Fatal error: " << e.what() << std::endl;
        return 1;
    }
    
    std::cout << "Agent service stopped." << std::endl;
    return 0;
}
