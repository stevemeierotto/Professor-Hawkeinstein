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
#include "agent_manager.h"
#include "llamacpp_client.h"
#include "database.h"
#include <iostream>
#include <ctime>
#include <sstream>
// #include <json/json.h> // Remove: not present. Use manual JSON formatting for now.

AgentManager::AgentManager(Database& db)
    : db_(db) {}

std::string AgentManager::processMessage(int userId, int agentId, const std::string& message) {
    // Fetch agent config (model path, context length, temperature)
    AgentConfig config = db_.getAgentConfig(agentId);

    // Fetch student advisor instance and history
    StudentAdvisor advisor = db_.getStudentAdvisor(userId, agentId);

    // Append student message to history
    advisor.conversationHistory.push_back({"student", message, std::time(nullptr)});

    // Build prompt from history and system prompt
    std::string prompt = buildPrompt(advisor);

    // Query llama.cpp
    LlamaCppClient client(config.modelPath, config.contextLength, config.temperature);
    std::string response;
    try {
        response = client.generate(prompt);
    } catch (const std::exception& e) {
        std::cerr << "[AgentManager] llama.cpp error: " << e.what() << std::endl;
        return "{\"error\": \"Model error\"}";
    }

    // Append advisor response to history
    advisor.conversationHistory.push_back({"advisor", response, std::time(nullptr)});
    db_.updateStudentAdvisor(advisor);

    // Return JSON response (manual formatting)
    std::ostringstream oss;
    oss << "{"
        << "\"timestamp\": " << std::time(nullptr) << ", "
        << "\"userId\": " << userId << ", "
        << "\"agentId\": " << agentId << ", "
        << "\"response\": \"" << response << "\", "
        << "\"history\": [";
    for (size_t i = 0; i < advisor.conversationHistory.size(); ++i) {
        const auto& turn = advisor.conversationHistory[i];
        oss << "{"
            << "\"role\": \"" << turn.role << "\"," 
            << "\"text\": \"" << turn.text << "\"," 
            << "\"timestamp\": " << turn.timestamp
            << "}";
        if (i + 1 < advisor.conversationHistory.size()) oss << ", ";
    }
    oss << "]}";
    return oss.str();
}

std::string AgentManager::buildPrompt(const StudentAdvisor& advisor) {
    std::ostringstream oss;
    oss << advisor.customSystemPrompt << "\n";
    for (const auto& turn : advisor.conversationHistory) {
        oss << (turn.role == "student" ? "Student: " : "Advisor: ") << turn.text << "\n";
    }
    return oss.str();
}

std::string AgentManager::generateResponse(const std::string& systemPrompt, const std::string& userMessage, float temperature) {
    // Get agent config
    AgentConfig config = db_.getAgentConfig(0);
    
    // Build simple prompt
    std::ostringstream oss;
    oss << systemPrompt << "\n\nStudent: " << userMessage << "\nAdvisor: ";
    std::string prompt = oss.str();
    
    // Query llama.cpp
    LlamaCppClient client(config.modelPath, config.contextLength, temperature);
    std::string response;
    try {
        response = client.generate(prompt);
    } catch (const std::exception& e) {
        std::cerr << "[AgentManager] llama.cpp error: " << e.what() << std::endl;
        response = "I apologize, but I'm having trouble processing your request right now. Please try again.";
    }
    
    return response;
}

// historyToJson removed: now using manual formatting
