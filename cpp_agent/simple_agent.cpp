#include <iostream>
#include <sstream>
#include <cstring>
#include <unistd.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <array>
#include <memory>
#include <cstdio>
#include <ctime>

std::string escapeShell(const std::string& str) {
    std::string result;
    for (char c : str) {
        if (c == '"' || c == '\\' || c == '$' || c == '`') {
            result += '\\';
        }
        result += c;
    }
    return result;
}

std::string callLlama(const std::string& prompt, float temp = 0.7) {
    std::string escaped = escapeShell(prompt);
    std::ostringstream cmd;
    cmd << "/home/steve/Professor_Hawkeinstein/llama.cpp/build/bin/llama-cli "
        << "-m /home/steve/Professor_Hawkeinstein/models/llama-2-7b-chat.Q4_0.gguf "
        << "--prompt \"" << escaped << "\" "
        << "-n 256 --temp " << temp << " --log-disable 2>/dev/null";
    
    std::cout << "[INFO] Calling llama.cpp with prompt length: " << prompt.length() << std::endl;
    
    std::array<char, 4096> buffer;
    std::string result;
    std::unique_ptr<FILE, decltype(&pclose)> pipe(popen(cmd.str().c_str(), "r"), pclose);
    
    if (pipe) {
        while (fgets(buffer.data(), buffer.size(), pipe.get()) != nullptr) {
            result += buffer.data();
        }
    } else {
        return "Error: Failed to execute llama.cpp";
    }
    
    std::cout << "[INFO] llama.cpp response length: " << result.length() << std::endl;
    return result;
}

std::string extractJSON(const std::string& body) {
    size_t start = body.find("{");
    if (start == std::string::npos) return "";
    return body.substr(start);
}

std::string getJSONValue(const std::string& json, const std::string& key) {
    std::string searchKey = "\"" + key + "\":";
    size_t pos = json.find(searchKey);
    if (pos == std::string::npos) return "";
    
    pos += searchKey.length();
    while (pos < json.length() && (json[pos] == ' ' || json[pos] == '\t')) pos++;
    
    if (json[pos] == '"') {
        pos++;
        size_t end = pos;
        while (end < json.length() && json[end] != '"') {
            if (json[end] == '\\') end++;
            end++;
        }
        return json.substr(pos, end - pos);
    } else if (json[pos] == '[') {
        int bracket_count = 1;
        size_t end = pos + 1;
        while (end < json.length() && bracket_count > 0) {
            if (json[end] == '[') bracket_count++;
            else if (json[end] == ']') bracket_count--;
            end++;
        }
        return json.substr(pos, end - pos);
    }
    
    return "";
}

std::string getLastMessageContent(const std::string& messagesArray) {
    size_t lastContent = messagesArray.rfind("\"content\":");
    if (lastContent == std::string::npos) return "";
    
    lastContent += 10;
    while (lastContent < messagesArray.length() && 
           (messagesArray[lastContent] == ' ' || messagesArray[lastContent] == '\t')) {
        lastContent++;
    }
    
    if (messagesArray[lastContent] == '"') {
        lastContent++;
        size_t end = lastContent;
        while (end < messagesArray.length() && messagesArray[end] != '"') {
            if (messagesArray[end] == '\\') end++;
            end++;
        }
        return messagesArray.substr(lastContent, end - lastContent);
    }
    
    return "";
}

std::string createHTTPResponse(int statusCode, const std::string& body) {
    std::ostringstream response;
    std::string statusText = (statusCode == 200) ? "OK" : "Error";
    
    response << "HTTP/1.1 " << statusCode << " " << statusText << "\r\n";
    response << "Content-Type: application/json\r\n";
    response << "Content-Length: " << body.length() << "\r\n";
    response << "Access-Control-Allow-Origin: *\r\n";
    response << "Access-Control-Allow-Methods: GET, POST, OPTIONS\r\n";
    response << "Access-Control-Allow-Headers: Content-Type, Authorization\r\n";
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
        std::cerr << "[ERROR] Failed to create socket\n";
        return 1;
    }
    
    int opt = 1;
    setsockopt(serverSocket, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));
    
    struct sockaddr_in address;
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons(8080);
    
    if (bind(serverSocket, (struct sockaddr*)&address, sizeof(address)) < 0) {
        std::cerr << "[ERROR] Failed to bind socket\n";
        close(serverSocket);
        return 1;
    }
    
    if (listen(serverSocket, 10) < 0) {
        std::cerr << "[ERROR] Failed to listen on socket\n";
        close(serverSocket);
        return 1;
    }
    
    std::cout << "==================================\n";
    std::cout << "Simple Agent Service Started\n";
    std::cout << "Port: 8080\n";
    std::cout << "Endpoints:\n";
    std::cout << "  GET  /health\n";
    std::cout << "  POST /api/chat\n";
    std::cout << "==================================\n";
    
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
                std::cout << "[INFO] Health check requested\n";
                response = createHTTPResponse(200, "{\"status\":\"ok\"}");
            }
            else if (path == "/api/chat" && method == "POST") {
                std::cout << "[INFO] Chat request received\n";
                std::cout << "[DEBUG] Request body length: " << body.length() << "\n";
                
                std::string jsonBody = extractJSON(body);
                if (jsonBody.empty()) {
                    response = createHTTPResponse(400, "{\"error\":\"Invalid JSON\"}");
                } else {
                    std::string messagesArray = getJSONValue(jsonBody, "messages");
                    std::string prompt = getLastMessageContent(messagesArray);
                    
                    if (prompt.empty()) {
                        response = createHTTPResponse(400, "{\"error\":\"No message content found\"}");
                    } else {
                        std::cout << "[INFO] Prompt: " << prompt.substr(0, 50) << "...\n";
                        
                        std::string llamaResponse = callLlama(prompt);
                        
                        std::ostringstream jsonResponse;
                        jsonResponse << "{\"response\":\"";
                        for (char c : llamaResponse) {
                            if (c == '"') jsonResponse << "\\\"";
                            else if (c == '\\') jsonResponse << "\\\\";
                            else if (c == '\n') jsonResponse << "\\n";
                            else if (c == '\r') jsonResponse << "\\r";
                            else if (c == '\t') jsonResponse << "\\t";
                            else jsonResponse << c;
                        }
                        jsonResponse << "\",\"model\":\"llama-2-7b-chat\"}";
                        
                        response = createHTTPResponse(200, jsonResponse.str());
                        std::cout << "[INFO] Response sent\n";
                    }
                }
            }
            else if (method == "OPTIONS") {
                response = createHTTPResponse(200, "{}");
            }
            else {
                response = createHTTPResponse(404, "{\"error\":\"Not found\"}");
            }
        } catch (const std::exception& e) {
            std::cerr << "[ERROR] Exception: " << e.what() << "\n";
            std::ostringstream errorJson;
            errorJson << "{\"error\":\"" << e.what() << "\"}";
            response = createHTTPResponse(500, errorJson.str());
        }
        
        send(clientSocket, response.c_str(), response.length(), 0);
        close(clientSocket);
    }
    
    close(serverSocket);
    return 0;
}
