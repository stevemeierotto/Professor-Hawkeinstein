#include <iostream>
#include <sstream>
#include <cstring>
#include <unistd.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <jsoncpp/json/json.h>
#include "llamacpp_client.h"

std::string createHTTPResponse(int statusCode, const std::string& body) {
    std::ostringstream response;
    std::string statusText = (statusCode == 200) ? "OK" : "Error";
    
    response << "HTTP/1.1 " << statusCode << " " << statusText << "\r\n";
    response << "Content-Type: application/json\r\n";
    response << "Content-Length: " << body.length() << "\r\n";
    response << "Access-Control-Allow-Origin: *\r\n";
    response << "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
    response << "Access-Control-Allow-Headers: Content-Type\r\n";
    response << "Connection: close\r\n\r\n";
    response << body;
    
    return response.str();
}

std::string parseHTTPRequest(const std::string& request, std::string& method, std::string& path) {
    std::istringstream stream(request);
    std::string line;
    
    if (std::getline(stream, line)) {
        std::istringstream lineStream(line);
        lineStream >> method >> path;
    }
    
    size_t bodyStart = request.find("\r\n\r\n");
    if (bodyStart != std::string::npos) {
        return request.substr(bodyStart + 4);
    }
    return "";
}

int main() {
    int serverSocket = socket(AF_INET, SOCK_STREAM, 0);
    if (serverSocket < 0) {
        std::cerr << "Failed to create socket\n";
        return 1;
    }
    
    int opt = 1;
    setsockopt(serverSocket, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));
    
    struct sockaddr_in address;
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons(8080);
    
    if (bind(serverSocket, (struct sockaddr*)&address, sizeof(address)) < 0) {
        std::cerr << "Failed to bind socket\n";
        close(serverSocket);
        return 1;
    }
    
    if (listen(serverSocket, 10) < 0) {
        std::cerr << "Failed to listen on socket\n";
        close(serverSocket);
        return 1;
    }
    
    std::cout << "HTTP server running on port 8080\n";
    
    while (true) {
        struct sockaddr_in clientAddress;
        socklen_t clientLen = sizeof(clientAddress);
        int clientSocket = accept(serverSocket, (struct sockaddr*)&clientAddress, &clientLen);
        
        if (clientSocket < 0) continue;
        
        char buffer[65536];
        ssize_t bytesRead = recv(clientSocket, buffer, sizeof(buffer) - 1, 0);
        
        if (bytesRead <= 0) {
            close(clientSocket);
            continue;
        }
        
        buffer[bytesRead] = '\0';
        std::string request(buffer);
        
        std::string method, path;
        std::string body = parseHTTPRequest(request, method, path);
        std::string response;
        
        try {
            if (path == "/health" && method == "GET") {
                std::cerr << "[FATAL] Deprecated binary simple_server called - use agent_service instead\n";
                response = createHTTPResponse(410, "{\"error\":\"Deprecated binary\",\"message\":\"simple_server is deprecated. Use agent_service instead.\"}");
            }
            else if (path == "/api/chat" && method == "POST") {
                std::cerr << "[FATAL] Deprecated endpoint /api/chat called in deprecated binary\n";
                response = createHTTPResponse(410, "{\"error\":\"Endpoint removed\",\"message\":\"/api/chat is deprecated. Use /agent/chat in agent_service instead.\"}");
            }
            else {
                    if (requestJson.isMember("messages") && requestJson["messages"].isArray()) {
                        for (const auto& msg : requestJson["messages"]) {
                            std::string role = msg.get("role", "user").asString();
                            std::string content = msg.get("content", "").asString();
                            
                            if (role == "user") {
                                fullPrompt += "Student: " + content + "\n";
                            } else if (role == "assistant" || role == "advisor") {
                                fullPrompt += "Advisor: " + content + "\n";
                            }
                        }
                    }
                    fullPrompt += "Advisor: ";
                    
                    std::cout << "[Chat] Generating response with llama.cpp\n";
                    LlamaCppClient llamaClient("/home/steve/Professor_Hawkeinstein/models/llama-2-7b-chat.Q4_0.gguf", 2048, temperature);
                    std::string agentResponse = llamaClient.generate(fullPrompt);
                    
                    Json::Value responseJson;
                    responseJson["response"] = agentResponse;
                    responseJson["model"] = model;
                    
                    Json::StreamWriterBuilder writerBuilder;
                    std::string jsonResponse = Json::writeString(writerBuilder, responseJson);
                    response = createHTTPResponse(200, jsonResponse);
                }
            }
            else if (method == "OPTIONS") {
                response = createHTTPResponse(200, "{}");
            }
            else {
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
    
    close(serverSocket);
    return 0;
}
