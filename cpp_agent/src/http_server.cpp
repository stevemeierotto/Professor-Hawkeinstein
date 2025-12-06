#include "../include/http_server.h"
#include "../include/llamacpp_client.h"
#include <iostream>
#include <sstream>
#include <cstring>
#include <vector>
#include <fstream>

HTTPServer::HTTPServer(int port, AgentManager& manager, Config& cfg) 
    : port(port), serverSocket(-1), running(false), agentManager(manager), config(cfg) {
}

HTTPServer::~HTTPServer() {
    stop();
}

void HTTPServer::start() {
    // Create socket
    serverSocket = socket(AF_INET, SOCK_STREAM, 0);
    if (serverSocket < 0) {
        throw std::runtime_error("Failed to create socket");
    }
    
    // Set socket options
    int opt = 1;
    if (setsockopt(serverSocket, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt)) < 0) {
        close(serverSocket);
        throw std::runtime_error("Failed to set socket options");
    }
    
    // Bind socket
    struct sockaddr_in address;
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons(port);
    
    if (bind(serverSocket, (struct sockaddr*)&address, sizeof(address)) < 0) {
        close(serverSocket);
        throw std::runtime_error("Failed to bind socket to port " + std::to_string(port));
    }
    
    // Listen
    if (listen(serverSocket, 10) < 0) {
        close(serverSocket);
        throw std::runtime_error("Failed to listen on socket");
    }
    
    running = true;
    serverThread = std::thread(&HTTPServer::run, this);
}

void HTTPServer::stop() {
    running = false;
    if (serverSocket >= 0) {
        close(serverSocket);
        serverSocket = -1;
    }
    if (serverThread.joinable()) {
        serverThread.join();
    }
}

void HTTPServer::run() {
    while (running) {
        struct sockaddr_in clientAddr;
        socklen_t clientLen = sizeof(clientAddr);
        
        int clientSocket = accept(serverSocket, (struct sockaddr*)&clientAddr, &clientLen);
        if (clientSocket < 0) {
            if (running) {
                std::cerr << "Failed to accept connection" << std::endl;
            }
            continue;
        }
        
        // Handle client in separate thread
        std::thread(&HTTPServer::handleClient, this, clientSocket).detach();
    }
}

void HTTPServer::handleClient(int clientSocket) {
    char buffer[65536];
    ssize_t bytesRead = recv(clientSocket, buffer, sizeof(buffer) - 1, 0);
    
    if (bytesRead <= 0) {
        close(clientSocket);
        return;
    }
    
    buffer[bytesRead] = '\0';
    std::string request(buffer);
    
    std::string method, path;
    std::string body = parseHTTPRequest(request, method, path);
    
    std::string response;
    
    try {
        // Route handling
        if (path == "/agent/chat" && method == "POST") {
            // Parse JSON body
            Json::Value requestJson;
            Json::CharReaderBuilder builder;
            std::stringstream ss(body);
            std::string errs;
            
            if (!Json::parseFromStream(builder, ss, &requestJson, &errs)) {
                response = createHTTPResponse(400, "{\"error\":\"Invalid JSON\"}");
            } else {
                int userId = requestJson["userId"].asInt();
                int agentId = requestJson["agentId"].asInt();
                std::string message = requestJson["message"].asString();
                std::string ragContext = requestJson.get("ragContext", "").asString();
                
                // Process through agent manager with RAG context
                std::string agentResponse;
                if (!ragContext.empty()) {
                    agentResponse = agentManager.processMessageWithContext(userId, agentId, message, ragContext);
                } else {
                    agentResponse = agentManager.processMessage(userId, agentId, message);
                }
                
                Json::Value responseJson;
                responseJson["response"] = agentResponse;
                responseJson["success"] = true;
                
                Json::StreamWriterBuilder writerBuilder;
                std::string jsonResponse = Json::writeString(writerBuilder, responseJson);
                
                response = createHTTPResponse(200, jsonResponse);
            }
        } else if (path == "/agent/list" && method == "GET") {
            Json::Value agents = agentManager.listAgents();
            Json::StreamWriterBuilder writerBuilder;
            std::string jsonResponse = Json::writeString(writerBuilder, agents);
            response = createHTTPResponse(200, jsonResponse);
        } else if (path.rfind("/agent/", 0) == 0 && path.length() > 7 && method == "GET") {
            // Extract agent ID from path like /agent/1
            std::string agentIdStr = path.substr(7);
            try {
                int agentId = std::stoi(agentIdStr);
                Json::Value agent = agentManager.getAgent(agentId);
                Json::StreamWriterBuilder writerBuilder;
                std::string jsonResponse = Json::writeString(writerBuilder, agent);
                response = createHTTPResponse(200, jsonResponse);
            } catch (...) {
                response = createHTTPResponse(400, "{\"error\":\"Invalid agent ID\"}");
            }
        } else if (path == "/biometric/verify-face" && method == "POST") {
            Json::Value requestJson;
            Json::CharReaderBuilder builder;
            std::stringstream ss(body);
            std::string errs;
            
            if (!Json::parseFromStream(builder, ss, &requestJson, &errs)) {
                response = createHTTPResponse(400, "{\"error\":\"Invalid JSON\"}");
            } else {
                int userId = requestJson["userId"].asInt();
                std::string imageData = requestJson["imageData"].asString();
                
                bool verified = agentManager.verifyFace(userId, imageData);
                
                Json::Value responseJson;
                responseJson["verified"] = verified;
                responseJson["confidence"] = verified ? 0.92 : 0.15;
                
                Json::StreamWriterBuilder writerBuilder;
                std::string jsonResponse = Json::writeString(writerBuilder, responseJson);
                
                response = createHTTPResponse(200, jsonResponse);
            }
        } else if (path == "/api/chat" && method == "POST") {
            // New endpoint format for dashboard compatibility
            Json::Value requestJson;
            Json::CharReaderBuilder builder;
            std::stringstream ss(body);
            std::string errs;
            
            if (!Json::parseFromStream(builder, ss, &requestJson, &errs)) {
                response = createHTTPResponse(400, "{\"error\":\"Invalid JSON\"}");
            } else {
                int agentId = requestJson.get("agent_id", 1).asInt();
                std::string model = requestJson.get("model", "llama-2-7b-chat").asString();
                std::string systemPrompt = requestJson.get("system_prompt", "").asString();
                float temperature = requestJson.get("temperature", 0.7).asFloat();
                
                // Get agent details for proper role labeling
                Json::Value agentData = agentManager.getAgent(agentId);
                std::string agentName = agentData.get("name", "Advisor").asString();
                
                // Extract messages array
                std::string fullPrompt = systemPrompt + "\n\n";
                if (requestJson.isMember("messages") && requestJson["messages"].isArray()) {
                    for (const auto& msg : requestJson["messages"]) {
                        std::string role = msg.get("role", "user").asString();
                        std::string content = msg.get("content", "").asString();
                        
                        if (role == "user") {
                            fullPrompt += "Student: " + content + "\n";
                        } else if (role == "assistant" || role == "advisor") {
                            fullPrompt += agentName + ": " + content + "\n";
                        }
                    }
                }
                fullPrompt += agentName + ": ";
                
                // Use LlamaCppClient with dynamic model path from config
                // Construct full model path: base path + model filename
                std::string modelPath = config.modelsBasePath + "/" + model;
                std::cout << "[Chat] Attempting to use model: " << modelPath << std::endl;
                
                // Validate model file exists, fallback to default if not found
                std::ifstream modelFile(modelPath);
                if (!modelFile.good()) {
                    std::string fallbackPath = config.modelsBasePath + "/" + config.defaultModel;
                    std::cout << "[Chat] WARNING: Model not found, falling back to: " << fallbackPath << std::endl;
                    modelPath = fallbackPath;
                    model = config.defaultModel;  // Update model name for response
                }
                
                LlamaCppClient llamaClient(config.llamaServerUrl, modelPath, 2048, temperature);
                std::string agentResponse = llamaClient.generate(fullPrompt);
                
                Json::Value responseJson;
                responseJson["response"] = agentResponse;
                responseJson["model"] = model;
                
                Json::StreamWriterBuilder writerBuilder;
                std::string jsonResponse = Json::writeString(writerBuilder, responseJson);
                
                response = createHTTPResponse(200, jsonResponse);
            }
        } else if (path == "/health" && method == "GET") {
            response = createHTTPResponse(200, "{\"status\":\"ok\"}");
        } else if (method == "OPTIONS") {
            // Handle CORS preflight requests
            response = createHTTPResponse(200, "");
        } else {
            response = createHTTPResponse(404, "{\"error\":\"Not found\"}");
        }
    } catch (const std::exception& e) {
        Json::Value errorJson;
        errorJson["error"] = e.what();
        Json::StreamWriterBuilder writerBuilder;
        std::string jsonResponse = Json::writeString(writerBuilder, errorJson);
        response = createHTTPResponse(500, jsonResponse);
    }
    
    send(clientSocket, response.c_str(), response.length(), 0);
    close(clientSocket);
}

std::string HTTPServer::parseHTTPRequest(const std::string& request, std::string& method, std::string& path) {
    std::istringstream stream(request);
    std::string line;
    
    // Parse request line
    if (std::getline(stream, line)) {
        std::istringstream lineStream(line);
        lineStream >> method >> path;
    }
    
    // Find body (after \r\n\r\n)
    size_t bodyStart = request.find("\r\n\r\n");
    if (bodyStart != std::string::npos) {
        return request.substr(bodyStart + 4);
    }
    
    return "";
}

std::string HTTPServer::createHTTPResponse(int statusCode, const std::string& body, const std::string& contentType) {
    std::ostringstream response;
    
    std::string statusText;
    switch (statusCode) {
        case 200: statusText = "OK"; break;
        case 400: statusText = "Bad Request"; break;
        case 404: statusText = "Not Found"; break;
        case 500: statusText = "Internal Server Error"; break;
        default: statusText = "Unknown"; break;
    }
    
    response << "HTTP/1.1 " << statusCode << " " << statusText << "\r\n";
    response << "Content-Type: " << contentType << "\r\n";
    response << "Content-Length: " << body.length() << "\r\n";
    response << "Access-Control-Allow-Origin: *\r\n";
    response << "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
    response << "Access-Control-Allow-Headers: Content-Type\r\n";
    response << "Connection: close\r\n";
    response << "\r\n";
    response << body;
    
    return response.str();
}
