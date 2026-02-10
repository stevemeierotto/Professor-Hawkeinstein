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
#include "database.h"
#include <string>
#include <vector>
// #include <json/json.h> // Remove: not present. Use nlohmann/json or another library if needed.

class AgentManager {
public:
    AgentManager(Database& db);
    std::string processMessage(int userId, int agentId, const std::string& message);
    std::string generateResponse(const std::string& systemPrompt, const std::string& userMessage, float temperature);

private:
    Database& db_;
    std::string buildPrompt(const StudentAdvisor& advisor);
    // historyToJson removed: now using manual formatting
};
