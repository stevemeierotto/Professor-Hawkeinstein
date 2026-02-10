/*
 * MIT License
 * Copyright (c) 2025 Your Name or Organization
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
#pragma once
#include <string>
#include <curl/curl.h>
#include <vector>
#include <jsoncpp/json/json.h>

class LlamaCppClient {
public:
    LlamaCppClient(const std::string& serverUrl, const std::string& modelPath, int contextLength = 2048, float temperature = 0.7f);
    ~LlamaCppClient();
    std::string generate(const std::string& prompt, int maxTokens = -1, float temperature = -1.0f);
    std::vector<float> embed(const std::string& text, int expectedDimensions = 384);

private:
    std::string serverUrl_;
    int contextLength_;
    float temperature_;
    CURL* curl_;
    
    static size_t writeCallback(void* contents, size_t size, size_t nmemb, std::string* userp);
    std::string makeRequest(const std::string& prompt, int maxTokens = -1, float temperature = -1.0f);
    std::string performPost(const std::string& path, const Json::Value& payload, long timeoutSeconds);
};
