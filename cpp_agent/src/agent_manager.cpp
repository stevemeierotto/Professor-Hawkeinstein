#include "../include/agent_manager.h"
#include "../include/llamacpp_client.h"
#include <iostream>
#include <sstream>
#include <algorithm>

AgentManager::AgentManager(Config& config) : config(config) {
    std::cout << "Initializing Agent Manager with multi-model support..." << std::endl;
    
    // Initialize llama.cpp clients for each configured model
    for (const auto& [modelName, modelConfig] : config.models) {
        std::string serverUrl = "http://localhost:" + std::to_string(modelConfig.port);
        std::string modelPath = config.modelsBasePath + "/" + modelConfig.file;
        
        std::cout << "Registering model: " << modelName << " at " << serverUrl << std::endl;
        
        llamaClients[modelName] = std::make_unique<LlamaCppClient>(
            serverUrl,
            modelPath,
            modelConfig.ctxSize,
            config.temperature
        );
    }
    
    // Also create a default client for backward compatibility
    if (llamaClients.empty()) {
        std::string modelPath = config.modelsBasePath + "/" + config.defaultModel;
        std::cout << "No models configured, using default: " << modelPath << std::endl;
        
        llamaClients[config.defaultModel] = std::make_unique<LlamaCppClient>(
            config.llamaServerUrl,
            modelPath,
            config.maxContextLength,
            config.temperature
        );
    }
    
    database = std::make_unique<Database>(config.dbHost, config.dbPort, config.dbName, config.dbUser, config.dbPassword);
    ragEngine = std::make_unique<RAGEngine>(database.get(), nullptr);  // RAG without embeddings for now
    
    std::cout << "Agent Manager initialized with " << llamaClients.size() << " model(s)" << std::endl;
}

AgentManager::~AgentManager() {
}

LlamaCppClient* AgentManager::getClientForModel(const std::string& modelName) {
    // Try exact match first
    auto it = llamaClients.find(modelName);
    if (it != llamaClients.end()) {
        return it->second.get();
    }
    
    // Try partial match (e.g., "llama-2-7b-chat" matches "llama-2-7b-chat.Q4_0.gguf")
    for (auto& [name, client] : llamaClients) {
        if (name.find(modelName) != std::string::npos || modelName.find(name) != std::string::npos) {
            std::cout << "Matched model '" << modelName << "' to '" << name << "'" << std::endl;
            return client.get();
        }
    }
    
    // Fallback to default model
    std::cout << "Model '" << modelName << "' not found, using default: " << config.defaultModel << std::endl;
    it = llamaClients.find(config.defaultModel);
    if (it != llamaClients.end()) {
        return it->second.get();
    }
    
    // Last resort: return first available client
    if (!llamaClients.empty()) {
        return llamaClients.begin()->second.get();
    }
    
    return nullptr;
}

Agent AgentManager::loadAgent(int agentId) {
    // Check cache first
    auto it = agentCache.find(agentId);
    if (it != agentCache.end()) {
        return it->second;
    }
    
    // Load from database
    Agent agent = database->getAgent(agentId);
    agentCache[agentId] = agent;
    
    return agent;
}

std::vector<std::string> AgentManager::retrieveRelevantContext(int agentId, const std::string& query) {
    // Use RAG engine to find relevant documents (reduced from 5 to 3 for performance)
    return ragEngine->search(agentId, query, 3);
}

void AgentManager::storeMemory(int userId, int agentId, const std::string& userMessage, const std::string& agentResponse) {
    database->storeMemory(userId, agentId, userMessage, agentResponse);
}

std::string AgentManager::buildPrompt(const Agent& agent, const std::string& userMessage, const std::vector<std::string>& context) {
    std::ostringstream prompt;
    
    // OPTIMIZATION: System prompt sent only on first request, then cached by llama-server
    // Since we're using cache_prompt=true, the system prompt will be cached automatically
    prompt << agent.systemPrompt << "\n\n";
    
    // Context from RAG (limit to top 3 for performance)
    if (!context.empty()) {
        prompt << "Relevant information:\n";
        int contextCount = 0;
        for (const auto& ctx : context) {
            if (contextCount++ >= 3) break;  // Cap at 3 context items
            prompt << "- " << ctx << "\n";
        }
        prompt << "\n";
    }
    
    // User message
    prompt << "Student: " << userMessage << "\n";
    prompt << "Professor Hawkeinstein: ";
    
    return prompt.str();
}

std::string AgentManager::processMessage(int userId, int agentId, const std::string& message) {
    std::cout << "Processing message for user " << userId << " with agent " << agentId << std::endl;
    
    try {
        // Load agent configuration
        Agent agent = loadAgent(agentId);
        
        // Skip RAG for now - context should be provided via processMessageWithContext
        std::vector<std::string> context;
        
        // Build prompt with system prompt + context + user message
        std::string prompt = buildPrompt(agent, message, context);
        
        // Extract temperature and max_tokens from agent parameters
        int maxTokens = agent.parameters.count("max_tokens") ? std::stoi(agent.parameters["max_tokens"]) : 512;
        float temperature = agent.parameters.count("temperature") ? std::stof(agent.parameters["temperature"]) : 0.7f;
        
        // Get the appropriate client for this agent's model
        LlamaCppClient* client = getClientForModel(agent.modelName);
        if (!client) {
            throw std::runtime_error("No LLM client available for model: " + agent.modelName);
        }
        
        std::cout << "Querying llama.cpp with model: " << agent.modelName 
                  << " (max_tokens=" << maxTokens << ", temp=" << temperature << ")" << std::endl;
        
        // Query llama.cpp with agent-specific parameters
        std::string response = client->generate(prompt, maxTokens, temperature);
        
        // Store conversation in memory
        storeMemory(userId, agentId, message, response);
        
        std::cout << "Response generated successfully" << std::endl;
        return response;
    } catch (const std::exception& e) {
        std::cerr << "Error processing message: " << e.what() << std::endl;
        return "I apologize, but I'm having trouble processing your request right now. Please try again later.";
    }
}

std::string AgentManager::processMessageWithContext(int userId, int agentId, const std::string& message, const std::string& ragContext) {
    std::cout << "Processing message with RAG context for user " << userId << " with agent " << agentId << std::endl;
    
    try {
        // Load agent configuration
        Agent agent = loadAgent(agentId);
        
        // Use provided RAG context
        std::vector<std::string> context;
        if (!ragContext.empty()) {
            context.push_back(ragContext);
            std::cout << "RAG context injected into prompt" << std::endl;
        }
        
        // Build prompt with system prompt + RAG context + user message
        std::string prompt = buildPrompt(agent, message, context);
        
        // Extract temperature and max_tokens from agent parameters
        int maxTokens = agent.parameters.count("max_tokens") ? std::stoi(agent.parameters["max_tokens"]) : 512;
        float temperature = agent.parameters.count("temperature") ? std::stof(agent.parameters["temperature"]) : 0.7f;
        
        // Get the appropriate client for this agent's model
        LlamaCppClient* client = getClientForModel(agent.modelName);
        if (!client) {
            throw std::runtime_error("No LLM client available for model: " + agent.modelName);
        }
        
        std::cout << "Querying llama.cpp with model: " << agent.modelName 
                  << " (max_tokens=" << maxTokens << ", temp=" << temperature << ")" << std::endl;
        
        // Query llama.cpp with agent-specific parameters
        std::string response = client->generate(prompt, maxTokens, temperature);
        
        // Store conversation in memory
        storeMemory(userId, agentId, message, response);
        
        std::cout << "Response generated successfully with RAG context" << std::endl;
        return response;
        
    } catch (const std::exception& e) {
        std::cerr << "Error processing message: " << e.what() << std::endl;
        return "I apologize, but I'm having trouble processing your request right now. Please try again in a moment.";
    }
}

Json::Value AgentManager::listAgents() {
    Json::Value agents(Json::arrayValue);
    
    try {
        std::vector<Agent> agentList = database->getAllAgents();
        
        for (const auto& agent : agentList) {
            Json::Value agentJson;
            agentJson["id"] = agent.id;
            agentJson["name"] = agent.name;
            agentJson["avatarEmoji"] = agent.avatarEmoji;
            agentJson["description"] = agent.description;
            agentJson["model"] = agent.modelName;
            agents.append(agentJson);
        }
    } catch (const std::exception& e) {
        std::cerr << "Error listing agents: " << e.what() << std::endl;
    }
    
    return agents;
}

Json::Value AgentManager::getAgent(int agentId) {
    Json::Value result;
    
    try {
        Agent agent = database->getAgent(agentId);
        
        result["id"] = agent.id;
        result["name"] = agent.name;
        result["avatarEmoji"] = agent.avatarEmoji;
        result["description"] = agent.description;
        result["system_prompt"] = agent.systemPrompt;
        result["model"] = agent.modelName;
        
        // Extract temperature and max_tokens from parameters map
        if (agent.parameters.count("temperature")) {
            result["temperature"] = std::stof(agent.parameters["temperature"]);
        } else {
            result["temperature"] = 0.7;
        }
        
        if (agent.parameters.count("max_tokens")) {
            result["max_tokens"] = std::stoi(agent.parameters["max_tokens"]);
        } else {
            result["max_tokens"] = 512;
        }
    } catch (const std::exception& e) {
        std::cerr << "Error getting agent " << agentId << ": " << e.what() << std::endl;
        result["error"] = "Agent not found";
    }
    
    return result;
}

bool AgentManager::verifyFace(int userId, const std::string& imageData) {
    // TODO: Implement OpenCV facial recognition
    // For now, return placeholder
    std::cout << "Face verification requested for user " << userId << std::endl;
    
    // This will be replaced with actual OpenCV implementation
    // that compares the captured face with stored biometric_signature
    
    return true;  // Placeholder
}

bool AgentManager::verifyVoice(int userId, const std::string& audioData) {
    // TODO: Implement voice authentication
    std::cout << "Voice verification requested for user " << userId << std::endl;
    
    return true;  // Placeholder
}
