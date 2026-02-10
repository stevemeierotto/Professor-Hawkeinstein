// database.h
#pragma once
#include <string>
#include <vector>
#include <map>

struct ConversationTurn {
    std::string role;
    std::string text;
    long timestamp;
};

struct StudentAdvisor {
    std::string customSystemPrompt;
    std::vector<ConversationTurn> conversationHistory;
};

struct AgentConfig {
    std::string modelPath;
    int contextLength;
    float temperature;
};

struct Agent {
    int id;
    std::string name;
    std::string description;
    std::string systemPrompt;
    std::string modelName;
    std::map<std::string, std::string> parameters;
};

class Database {
public:
    Database(const std::string& configPath);
    AgentConfig getAgentConfig(int agentId);
    StudentAdvisor getStudentAdvisor(int userId, int agentId);
    void updateStudentAdvisor(const StudentAdvisor& advisor);
};
