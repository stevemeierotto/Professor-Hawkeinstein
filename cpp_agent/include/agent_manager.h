#ifndef AGENT_MANAGER_H
#define AGENT_MANAGER_H

#include <string>
#include <map>
#include <memory>
#include <jsoncpp/json/json.h>
#include "config.h"
#include "database.h"
#include "rag_engine.h"

class LlamaCppClient;  // Forward declaration

class AgentManager {
private:
    Config& config;
    std::unique_ptr<LlamaCppClient> llamaClient;
    std::unique_ptr<Database> database;
    std::unique_ptr<RAGEngine> ragEngine;
    std::map<int, Agent> agentCache;
    
    Agent loadAgent(int agentId);
    std::vector<std::string> retrieveRelevantContext(int agentId, const std::string& query);
    void storeMemory(int userId, int agentId, const std::string& userMessage, const std::string& agentResponse);
    std::string buildPrompt(const Agent& agent, const std::string& userMessage, const std::vector<std::string>& context);
    
public:
    AgentManager(Config& config);
    ~AgentManager();
    
    std::string processMessage(int userId, int agentId, const std::string& message);
    std::string processMessageWithContext(int userId, int agentId, const std::string& message, const std::string& ragContext);
    Json::Value listAgents();
    Json::Value getAgent(int agentId);
    bool verifyFace(int userId, const std::string& imageData);
    bool verifyVoice(int userId, const std::string& audioData);
};

#endif // AGENT_MANAGER_H
