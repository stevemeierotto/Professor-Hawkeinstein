/*
 * MIT License
 * Copyright (c) 2025 Your Name or Organization
 */
#include "llamacpp_client.h"
#include <iostream>
#include <sstream>
#include <stdexcept>
#include <jsoncpp/json/json.h>

LlamaCppClient::LlamaCppClient(const std::string& serverUrl, const std::string& modelPath, int contextLength, float temperature)
    : serverUrl_(serverUrl), contextLength_(contextLength), temperature_(temperature), curl_(nullptr) {
    // Initialize curl globally once
    curl_global_init(CURL_GLOBAL_DEFAULT);
    std::cout << "[LlamaCppClient] Connected to llama-server at " << serverUrl_ << std::endl;
}

LlamaCppClient::~LlamaCppClient() {
    curl_global_cleanup();
}

size_t LlamaCppClient::writeCallback(void* contents, size_t size, size_t nmemb, std::string* userp) {
    size_t totalSize = size * nmemb;
    userp->append((char*)contents, totalSize);
    return totalSize;
}

std::string LlamaCppClient::makeRequest(const std::string& prompt) {
    std::string responseData;
    
    // Determine token limit based on prompt type
    // Longer prompts (like lesson/question generation) need more tokens
    int tokenLimit = 512;   // Increased default for all requests
    long timeout = 120L;    // 2 minutes default timeout
    
    if (prompt.find("Create") != std::string::npos || 
        prompt.find("educational lesson") != std::string::npos ||
        prompt.find("Generate") != std::string::npos ||
        prompt.find("questions") != std::string::npos ||
        prompt.find("fill-in-the-blank") != std::string::npos ||
        prompt.find("multiple choice") != std::string::npos) {
        tokenLimit = 1024;  // More tokens for content/question generation
        timeout = 300L;     // 5 minutes for longer generations
    }
    
    // Build JSON request
    Json::Value request;
    request["prompt"] = prompt;
    request["n_predict"] = tokenLimit;
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
    
    // Create fresh CURL handle for each request
    CURL* curl = curl_easy_init();
    if (!curl) {
        throw std::runtime_error("Failed to initialize CURL handle");
    }
    
    curl_easy_setopt(curl, CURLOPT_URL, (serverUrl_ + "/completion").c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, jsonRequest.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, writeCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &responseData);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, timeout);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);
    
    struct curl_slist* headers = nullptr;
    headers = curl_slist_append(headers, "Content-Type: application/json");
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    
    // Make request
    CURLcode res = curl_easy_perform(curl);
    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);
    
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
