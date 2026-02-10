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

std::string LlamaCppClient::makeRequest(const std::string& prompt, int maxTokens, float temperature) {
    // Use provided maxTokens or determine based on prompt type
    int tokenLimit = maxTokens > 0 ? maxTokens : 512;
    long timeout = 180L;    // 3 minutes default timeout
    
    if (maxTokens < 0) {
        // Auto-detect token limit based on prompt type if not specified
        if (prompt.find("Create") != std::string::npos || 
            prompt.find("educational lesson") != std::string::npos ||
            prompt.find("Generate") != std::string::npos ||
            prompt.find("questions") != std::string::npos ||
            prompt.find("fill-in-the-blank") != std::string::npos ||
            prompt.find("multiple choice") != std::string::npos) {
            tokenLimit = 1024;  // More tokens for content/question generation
            timeout = 600L;     // 10 minutes for longer generations
        }
    } else {
        // Adjust timeout based on token limit - be generous with timeouts
        if (tokenLimit > 4000) timeout = 900L;  // 15 minutes for very large generations
        else if (tokenLimit > 2000) timeout = 720L;  // 12 minutes for large generations (questions)
        else if (tokenLimit > 1000) timeout = 480L;  // 8 minutes for medium
        else timeout = 300L;  // 5 minutes for small requests
    }
    
    // Use provided temperature or class default
    float actualTemp = temperature > 0.0f ? temperature : temperature_;
    
    // Build JSON request
    Json::Value request;
    request["prompt"] = prompt;
    request["n_predict"] = tokenLimit;
    request["temperature"] = actualTemp;
    request["cache_prompt"] = true;  // Enable KV cache reuse
    
    // Add stop sequences to prevent runaway generation
    Json::Value stopSequences(Json::arrayValue);
    stopSequences.append("\nStudent:");
    stopSequences.append("\nUser:");
    stopSequences.append("\n\n\n");
    request["stop"] = stopSequences;
    
    return performPost("/completion", request, timeout);
}

std::string LlamaCppClient::generate(const std::string& prompt, int maxTokens, float temperature) {
    std::cout << "[LlamaCppClient] Generating response for prompt length: " << prompt.length();
    if (maxTokens > 0) std::cout << " max_tokens: " << maxTokens;
    if (temperature > 0.0f) std::cout << " temperature: " << temperature;
    std::cout << std::endl;
    
    try {
        std::string responseData = makeRequest(prompt, maxTokens, temperature);
        
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

std::string LlamaCppClient::performPost(const std::string& path, const Json::Value& payload, long timeoutSeconds) {
    Json::StreamWriterBuilder writer;
    std::string jsonRequest = Json::writeString(writer, payload);
    std::string responseData;

    CURL* curl = curl_easy_init();
    if (!curl) {
        throw std::runtime_error("Failed to initialize CURL handle");
    }

    curl_easy_setopt(curl, CURLOPT_URL, (serverUrl_ + path).c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, jsonRequest.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, writeCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &responseData);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, timeoutSeconds);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);

    struct curl_slist* headers = nullptr;
    headers = curl_slist_append(headers, "Content-Type: application/json");
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

    CURLcode res = curl_easy_perform(curl);
    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);

    if (res != CURLE_OK) {
        throw std::runtime_error("CURL request failed: " + std::string(curl_easy_strerror(res)));
    }

    return responseData;
}

std::vector<float> LlamaCppClient::embed(const std::string& text, int expectedDimensions) {
    if (text.empty()) {
        throw std::runtime_error("Cannot embed empty text");
    }

    Json::Value request;
    request["content"] = text;

    std::string responseData = performPost("/embedding", request, 120L);

    Json::Value response;
    Json::CharReaderBuilder reader;
    std::stringstream ss(responseData);
    std::string errs;

    if (!Json::parseFromStream(reader, ss, &response, &errs)) {
        throw std::runtime_error("Failed to parse embedding response: " + errs);
    }

    const Json::Value* embeddingNode = nullptr;
    if (response.isMember("embedding") && response["embedding"].isArray()) {
        embeddingNode = &response["embedding"];
    } else if (response.isMember("data") && response["data"].isArray() && !response["data"].empty()) {
        const Json::Value& first = response["data"][0];
        if (first.isMember("embedding") && first["embedding"].isArray()) {
            embeddingNode = &first["embedding"];
        }
    }

    if (!embeddingNode) {
        throw std::runtime_error("Embedding response missing 'embedding' array");
    }

    std::vector<float> embedding;
    embedding.reserve(embeddingNode->size());
    for (const auto& value : *embeddingNode) {
        embedding.push_back(value.asFloat());
    }

    if (expectedDimensions > 0 && static_cast<int>(embedding.size()) != expectedDimensions) {
        std::ostringstream oss;
        oss << "Expected embedding dimension " << expectedDimensions << " but received " << embedding.size();
        throw std::runtime_error(oss.str());
    }

    return embedding;
}
