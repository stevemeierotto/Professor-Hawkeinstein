#ifndef HTTP_SERVER_H
#define HTTP_SERVER_H

#include <string>
#include <thread>
#include <atomic>
#include <sys/socket.h>
#include <netinet/in.h>
#include <unistd.h>
#include <jsoncpp/json/json.h>
#include "agent_manager.h"
#include "config.h"

class HTTPServer {
private:
    int port;
    int serverSocket;
    std::atomic<bool> running;
    std::thread serverThread;
    AgentManager& agentManager;
    Config& config;  // Add config reference for model path resolution
    static const int REQUEST_TIMEOUT = 300;  // 5 minutes
    
    void handleClient(int clientSocket);
    std::string parseHTTPRequest(const std::string& request, std::string& method, std::string& path);
    std::string createHTTPResponse(int statusCode, const std::string& body, const std::string& contentType = "application/json");
    
public:
    HTTPServer(int port, AgentManager& manager, Config& cfg);
    ~HTTPServer();
    
    void start();
    void stop();
    void run();
};

#endif // HTTP_SERVER_H
