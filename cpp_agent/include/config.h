#ifndef CONFIG_H
#define CONFIG_H

#include <string>
#include <fstream>
#include <jsoncpp/json/json.h>

struct Config {
    std::string llamaServerUrl = "http://localhost:8090";
    std::string modelName = "qwen2.5:3b";
    int serverPort = 8080;
    
    // Model configuration
    std::string modelsBasePath = "/home/steve/Professor_Hawkeinstein/models";
    std::string defaultModel = "qwen2.5-1.5b-instruct-q4_k_m.gguf";
    
    // Database configuration
    std::string dbHost = "localhost";
    int dbPort = 3306;
    std::string dbName = "professorhawkeinstein_platform";
    std::string dbUser = "professorhawkeinstein_user";
    std::string dbPassword = "BT1716lit";
    
    // Agent configuration
    int maxContextLength = 4096;
    float temperature = 0.7;
    int topK = 40;
    float topP = 0.9;
    
    bool load(const std::string& configPath) {
        std::ifstream file(configPath);
        if (!file.is_open()) {
            return false;
        }
        
        Json::Value root;
        Json::CharReaderBuilder builder;
        std::string errs;
        
        if (!Json::parseFromStream(builder, file, &root, &errs)) {
            return false;
        }
        
        if (root.isMember("llama_server_url")) llamaServerUrl = root["llama_server_url"].asString();
        if (root.isMember("ollama_url")) llamaServerUrl = root["ollama_url"].asString(); // Backward compat
        if (root.isMember("model_name")) modelName = root["model_name"].asString();
        if (root.isMember("server_port")) serverPort = root["server_port"].asInt();
        if (root.isMember("models_base_path")) modelsBasePath = root["models_base_path"].asString();
        if (root.isMember("default_model")) defaultModel = root["default_model"].asString();
        
        if (root.isMember("database")) {
            auto db = root["database"];
            if (db.isMember("host")) dbHost = db["host"].asString();
            if (db.isMember("port")) dbPort = db["port"].asInt();
            if (db.isMember("name")) dbName = db["name"].asString();
            if (db.isMember("user")) dbUser = db["user"].asString();
            if (db.isMember("password")) dbPassword = db["password"].asString();
        }
        
        if (root.isMember("agent")) {
            auto agent = root["agent"];
            if (agent.isMember("max_context_length")) maxContextLength = agent["max_context_length"].asInt();
            if (agent.isMember("temperature")) temperature = agent["temperature"].asFloat();
            if (agent.isMember("top_k")) topK = agent["top_k"].asInt();
            if (agent.isMember("top_p")) topP = agent["top_p"].asFloat();
        }
        
        return true;
    }
};

#endif // CONFIG_H
