// database.cpp
#include "database.h"
#include <iostream>
#include <string>
#include <vector>
#include <map>

Database::Database(const std::string& configPath) {
    std::cout << "[Database] Loaded config from " << configPath << std::endl;
}

AgentConfig Database::getAgentConfig(int agentId) {
    // Static config - PHP backend handles all data storage
    return {"/home/steve/Professor_Hawkeinstein/models/llama-2-7b-chat.Q4_0.gguf", 2048, 0.7f};
}

StudentAdvisor Database::getStudentAdvisor(int userId, int agentId) {
    // Not used - dashboard sends all context in each request to /api/chat
    StudentAdvisor advisor;
    advisor.customSystemPrompt = "You are Professor Hawkeinstein, an expert advisor.";
    advisor.conversationHistory = {};
    return advisor;
}

void Database::updateStudentAdvisor(const StudentAdvisor& advisor) {
    // Not used - PHP backend handles storage via update_advisor_data.php
    std::cout << "[Database] updateStudentAdvisor called (handled by PHP)." << std::endl;
}
