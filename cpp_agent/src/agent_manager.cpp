#include "../include/agent_manager.h"
#include "../include/llamacpp_client.h"
#include <iostream>
#include <sstream>
#include <algorithm>
#include <iomanip>

AgentManager::AgentManager(Config& config) : config(config) {
    std::cout << "Initializing Agent Manager with multi-model support..." << std::endl;
    
    // Initialize llama.cpp clients for each configured model
    for (const auto& [modelName, modelConfig] : config.models) {
        // Use per-model URL for multi-model support
        std::string serverUrl = modelConfig.url;
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

    LlamaCppClient* ragClient = nullptr;
    if (!llamaClients.empty()) {
        ragClient = getClientForModel(config.defaultModel);
    }
    ragEngine = std::make_unique<RAGEngine>(database.get(), ragClient);
    
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

std::vector<RetrievedChunk> AgentManager::retrieveRelevantContext(const Agent& agent, const std::string& query) {
    if (!ragEngine) {
        std::cerr << "[AgentManager] RAG engine unavailable" << std::endl;
        return {};
    }

    auto getParam = [&](const std::string& key) -> std::string {
        auto it = agent.parameters.find(key);
        return it != agent.parameters.end() ? it->second : "";
    };

    RAGSearchContext ctx;
    ctx.agentId = agent.id;
    ctx.topK = 5;

    const std::string customTopK = getParam("rag_top_k");
    if (!customTopK.empty()) {
        try {
            ctx.topK = std::stoi(customTopK);
        } catch (...) {
            std::cerr << "[AgentManager] Invalid rag_top_k value for agent " << agent.id << std::endl;
        }
    }

    const std::string customThreshold = getParam("rag_min_similarity");
    if (!customThreshold.empty()) {
        try {
            ctx.similarityThreshold = std::stof(customThreshold);
        } catch (...) {
            std::cerr << "[AgentManager] Invalid rag_min_similarity value for agent " << agent.id << std::endl;
        }
    }

    ctx.metric = getParam("rag_metric");
    ctx.gradeLevel = getParam("grade_level");
    if (ctx.gradeLevel.empty()) {
        ctx.gradeLevel = getParam("grade");
    }
    ctx.subject = getParam("subject");
    ctx.agentScope = getParam("rag_scope");
    if (ctx.agentScope.empty()) {
        ctx.agentScope = getParam("agent_scope");
    }

    auto chunks = ragEngine->search(ctx, query);
    if (chunks.empty()) {
        std::cout << "[AgentManager] No RAG context returned for agent " << agent.id << std::endl;
    } else {
        std::cout << "[AgentManager] Retrieved " << chunks.size() << " filtered RAG chunk(s) for agent "
                  << agent.id << std::endl;
    }
    return chunks;
}

void AgentManager::storeMemory(int userId, int agentId, const std::string& userMessage, const std::string& agentResponse) {
    database->storeMemory(userId, agentId, userMessage, agentResponse);
}

std::string AgentManager::buildPrompt(const Agent& agent, const std::string& userMessage, const std::vector<RetrievedChunk>& contextChunks) {
    std::ostringstream prompt;
    prompt << agent.systemPrompt << "\n\n";

    const std::size_t kContextBudget = 1200;
    std::size_t usedChars = 0;
    bool headerWritten = false;

    auto appendChunk = [&](const RetrievedChunk& chunk) -> bool {
        if (chunk.text.empty()) {
            return false;
        }
        if (usedChars >= kContextBudget) {
            return false;
        }

        std::string snippet = chunk.text;
        if (usedChars + snippet.size() > kContextBudget) {
            std::size_t remaining = kContextBudget - usedChars;
            if (remaining == 0) {
                return false;
            }
            snippet = snippet.substr(0, remaining);
        }

        const std::string gradeLabel = chunk.gradeLevel.empty() ? "any" : chunk.gradeLevel;
        const std::string subjectLabel = chunk.subject.empty() ? "any" : chunk.subject;

        std::ostringstream metaLine;
        metaLine << "[grade=" << gradeLabel
                 << " subject=" << subjectLabel
                 << " similarity=" << std::fixed << std::setprecision(2) << chunk.similarity << "]";
        const std::string meta = metaLine.str();

        if (usedChars + meta.size() >= kContextBudget) {
            return false;
        }

        if (!headerWritten) {
            prompt << "Relevant knowledge:\n";
            headerWritten = true;
        }

        std::size_t remaining = kContextBudget - usedChars - meta.size();
        if (remaining == 0) {
            return false;
        }

        if (snippet.size() > remaining) {
            snippet = snippet.substr(0, remaining);
        }

        prompt << meta << "\n";
        prompt << snippet << "\n";
        usedChars += meta.size() + snippet.size();
        return true;
    };

    int injectedChunks = 0;
    for (const auto& chunk : contextChunks) {
        if (appendChunk(chunk)) {
            ++injectedChunks;
        }
    }

    if (headerWritten) {
        prompt << "\n";
        std::cout << "[AgentManager] Injected " << injectedChunks << " RAG chunk(s) (" << usedChars
                  << " chars of context)" << std::endl;
    } else {
        std::cout << "[AgentManager] RAG: no context met threshold for agent " << agent.id << std::endl;
    }

    prompt << "Student: " << userMessage << "\n";
    prompt << "Professor Hawkeinstein: ";
    
    return prompt.str();
}

std::string AgentManager::processMessage(int userId, int agentId, const std::string& message) {
    std::cout << "Processing message for user " << userId << " with agent " << agentId << std::endl;
    
    try {
        // Load agent configuration
        Agent agent = loadAgent(agentId);
        
        // Retrieve relevant context from RAG (agent-aware filters)
        std::vector<RetrievedChunk> context = retrieveRelevantContext(agent, message);
        std::cout << "[AgentManager] Retrieved " << context.size() << " RAG context items" << std::endl;
        
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
        std::vector<RetrievedChunk> context;
        if (!ragContext.empty()) {
            context.push_back({-1, 0, ragContext, 1.0f, "", "", ""});
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
