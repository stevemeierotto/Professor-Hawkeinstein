/*
 * MIT License
 * Copyright (c) 2025 Your Name or Organization
 */
#include "llamacpp_client.h"
#include <iostream>
#include <sstream>
#include <stdexcept>
#include <jsoncpp/json/json.h>

LlamaCppClient::LlamaCppClient(const std::string& modelPath, int contextLength, float temperature)
    : serverUrl_("http://localhost:8090"), contextLength_(contextLength), temperature_(temperature) {
    curl_ = curl_easy_init();
    if (!curl_) {
        throw std::runtime_error("Failed to initialize CURL");
    }
    std::cout << "[LlamaCppClient] Connected to llama-server at " << serverUrl_ << std::endl;
}

LlamaCppClient::~LlamaCppClient() {
    if (curl_) {
        curl_easy_cleanup(curl_);
    }
}

size_t LlamaCppClient::writeCallback(void* contents, size_t size, size_t nmemb, std::string* userp) {
    size_t totalSize = size * nmemb;
    userp->append((char*)contents, totalSize);
    return totalSize;
}

std::string LlamaCppClient::makeRequest(const std::string& prompt) {
    std::string responseData;
    
    // Build JSON request
    Json::Value request;
    request["prompt"] = prompt;
    request["n_predict"] = 256;  // Optimized for conversational responses
    request["temperature"] = temperature_;
    request["cache_prompt"] = true;  // Enable KV cache reuse
    
    // Add stop sequences to prevent runaway generation
    Json::Value stopSequences(Json::arrayValue);
    stopSequences.append("\nStudent:");
    stopSequences.append("\nUser:");
    stopSequences.append("\n\n\n");
    request["stop"] = stopSequences;
    
    Json::StreamWriterBuilder writer;
    std::string jsonRequest = Json::writeString(writer, request);
    
    // Setup CURL
    curl_easy_reset(curl_);
    curl_easy_setopt(curl_, CURLOPT_URL, (serverUrl_ + "/completion").c_str());
    curl_easy_setopt(curl_, CURLOPT_POSTFIELDS, jsonRequest.c_str());
    curl_easy_setopt(curl_, CURLOPT_WRITEFUNCTION, writeCallback);
    curl_easy_setopt(curl_, CURLOPT_WRITEDATA, &responseData);
    curl_easy_setopt(curl_, CURLOPT_TIMEOUT, 120L);
    curl_easy_setopt(curl_, CURLOPT_CONNECTTIMEOUT, 5L);
    
    struct curl_slist* headers = nullptr;
    headers = curl_slist_append(headers, "Content-Type: application/json");
    curl_easy_setopt(curl_, CURLOPT_HTTPHEADER, headers);
    
    // Make request
    CURLcode res = curl_easy_perform(curl_);
    curl_slist_free_all(headers);
    
    if (res != CURLE_OK) {
        throw std::runtime_error("CURL request failed: " + std::string(curl_easy_strerror(res)));
    }
    
    return responseData;
}

std::string LlamaCppClient::generate(const std::string& prompt) {
    std::cout << "[LlamaCppClient] Generating response for prompt length: " << prompt.length() << std::endl;
    
    try {
        std::string responseData = makeRequest(prompt);
        
        // Parse JSON response
        Json::Value response;
        Json::CharReaderBuilder reader;
        std::stringstream ss(responseData);
        std::string errs;
        
        if (!Json::parseFromStream(reader, ss, &response, &errs)) {
            throw std::runtime_error("Failed to parse response: " + errs);
        }
        
        std::string content = response.get("content", "").asString();
        std::cout << "[LlamaCppClient] Response length: " << content.length() << std::endl;
        
        return content;
        
    } catch (const std::exception& e) {
        std::cerr << "[LlamaCppClient] Error: " << e.what() << std::endl;
        throw;
    }
}
