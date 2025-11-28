#ifndef OLLAMA_CLIENT_H
#define OLLAMA_CLIENT_H

#include <string>
#include <curl/curl.h>
#include <jsoncpp/json/json.h>

class OllamaClient {
private:
    std::string baseUrl;
    std::string modelName;
    CURL* curl;
    
    static size_t writeCallback(void* contents, size_t size, size_t nmemb, std::string* userp);
    std::string makeRequest(const std::string& endpoint, const Json::Value& payload);
    
public:
    OllamaClient(const std::string& baseUrl, const std::string& modelName);
    ~OllamaClient();
    
    std::string generate(const std::string& prompt, float temperature = 0.7, int maxTokens = 2048);
    std::vector<float> getEmbedding(const std::string& text);
    bool isAvailable();
};

#endif // OLLAMA_CLIENT_H
