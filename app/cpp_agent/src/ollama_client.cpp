#include "../include/ollama_client.h"
#include <iostream>
#include <sstream>
#include <stdexcept>

OllamaClient::OllamaClient(const std::string& baseUrl, const std::string& modelName) 
    : baseUrl(baseUrl), modelName(modelName) {
    curl_global_init(CURL_GLOBAL_DEFAULT);
    curl = curl_easy_init();
    
    if (!curl) {
        throw std::runtime_error("Failed to initialize CURL");
    }
    
    std::cout << "Ollama client initialized: " << baseUrl << " with model " << modelName << std::endl;
}

OllamaClient::~OllamaClient() {
    if (curl) {
        curl_easy_cleanup(curl);
    }
    curl_global_cleanup();
}

size_t OllamaClient::writeCallback(void* contents, size_t size, size_t nmemb, std::string* userp) {
    size_t totalSize = size * nmemb;
    userp->append((char*)contents, totalSize);
    return totalSize;
}

std::string OllamaClient::makeRequest(const std::string& endpoint, const Json::Value& payload) {
    std::string url = baseUrl + endpoint;
    std::string response;
    
    // Convert JSON to string
    Json::StreamWriterBuilder writer;
    std::string jsonPayload = Json::writeString(writer, payload);
    
    std::cout << "[CURL] URL: " << url << std::endl;
    std::cout << "[CURL] Payload: " << jsonPayload.substr(0, 100) << "..." << std::endl;
    
    // Set CURL options
    curl_easy_setopt(curl, CURLOPT_URL, url.c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, jsonPayload.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, writeCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 300L);  // 5 minute timeout for slow systems
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 30L);  // 30 second connection timeout
    curl_easy_setopt(curl, CURLOPT_VERBOSE, 1L);  // Enable verbose output
    
    // Set headers
    struct curl_slist* headers = NULL;
    headers = curl_slist_append(headers, "Content-Type: application/json");
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    
    std::cout << "[CURL] Starting request..." << std::endl;
    
    // Perform request
    CURLcode res = curl_easy_perform(curl);
    curl_slist_free_all(headers);
    
    std::cout << "[CURL] Request completed with code: " << res << std::endl;
    
    if (res != CURLE_OK) {
        throw std::runtime_error("CURL request failed: " + std::string(curl_easy_strerror(res)));
    }
    
    return response;
}

std::string OllamaClient::generate(const std::string& prompt, float temperature, int maxTokens) {
    std::cout << "[Ollama] Generating response for prompt length: " << prompt.length() << std::endl;
    
    Json::Value payload;
    payload["model"] = modelName;
    payload["prompt"] = prompt;
    payload["stream"] = false;
    payload["options"]["temperature"] = temperature;
    payload["options"]["num_predict"] = maxTokens;
    
    try {
        std::cout << "[Ollama] Sending request to: " << baseUrl << "/api/generate" << std::endl;
        std::string response = makeRequest("/api/generate", payload);
        std::cout << "[Ollama] Received response, parsing JSON..." << std::endl;
        // Parse response
        Json::Value responseJson;
        Json::CharReaderBuilder builder;
        std::stringstream ss(response);
        std::string errs;
        
        std::cout << "[Ollama] Response length: " << response.length() << " bytes" << std::endl;
        
        if (!Json::parseFromStream(builder, ss, &responseJson, &errs)) {
            std::cerr << "[Ollama] JSON parse error: " << errs << std::endl;
            throw std::runtime_error("Failed to parse Ollama response: " + errs);
        }
        
        std::cout << "[Ollama] JSON parsed successfully" << std::endl;
        
        if (responseJson.isMember("response")) {
            std::string result = responseJson["response"].asString();
            std::cout << "[Ollama] Response obtained, length: " << result.length() << std::endl;
            return result;
        } else if (responseJson.isMember("error")) {
            throw std::runtime_error("Ollama error: " + responseJson["error"].asString());
        } else {
            throw std::runtime_error("Unexpected Ollama response format");
        }
        
    } catch (const std::exception& e) {
        std::cerr << "Error generating response: " << e.what() << std::endl;
        throw;
    }
}

std::vector<float> OllamaClient::getEmbedding(const std::string& text) {
    Json::Value payload;
    payload["model"] = modelName;
    payload["prompt"] = text;
    
    try {
        std::string response = makeRequest("/api/embeddings", payload);
        
        // Parse response
        Json::Value responseJson;
        Json::CharReaderBuilder builder;
        std::stringstream ss(response);
        std::string errs;
        
        if (!Json::parseFromStream(builder, ss, &responseJson, &errs)) {
            throw std::runtime_error("Failed to parse embedding response: " + errs);
        }
        
        std::vector<float> embedding;
        if (responseJson.isMember("embedding")) {
            const Json::Value& embArray = responseJson["embedding"];
            for (const auto& val : embArray) {
                embedding.push_back(val.asFloat());
            }
        }
        
        return embedding;
        
    } catch (const std::exception& e) {
        std::cerr << "Error getting embedding: " << e.what() << std::endl;
        throw;
    }
}

bool OllamaClient::isAvailable() {
    try {
        Json::Value payload;
        makeRequest("/api/tags", payload);
        return true;
    } catch (...) {
        return false;
    }
}
