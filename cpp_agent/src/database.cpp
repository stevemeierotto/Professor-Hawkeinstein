#include "../include/database.h"
#include <iostream>
#include <sstream>
#include <stdexcept>
#include <ctime>
#include <cstring>

Database::Database(const std::string& host, int port, const std::string& dbName, 
                   const std::string& user, const std::string& password)
    : host(host), port(port), dbName(dbName), user(user), password(password), connection(nullptr) {
    connect();
}

Database::~Database() {
    disconnect();
}

void Database::connect() {
    connection = mysql_init(nullptr);
    
    if (!connection) {
        throw std::runtime_error("Failed to initialize MySQL connection");
    }
    
    if (!mysql_real_connect(connection, host.c_str(), user.c_str(), password.c_str(), 
                           dbName.c_str(), port, nullptr, 0)) {
        std::string error = mysql_error(connection);
        mysql_close(connection);
        throw std::runtime_error("Failed to connect to database: " + error);
    }
    
    std::cout << "Connected to database: " << dbName << std::endl;
}

void Database::disconnect() {
    if (connection) {
        mysql_close(connection);
        connection = nullptr;
    }
}

Agent Database::getAgent(int agentId) {
    std::ostringstream query;
    query << "SELECT agent_id, agent_name, specialization, system_prompt, model_name, temperature, max_tokens "
          << "FROM agents WHERE agent_id = " << agentId;
    
    if (mysql_query(connection, query.str().c_str())) {
        throw std::runtime_error("Failed to query agent: " + std::string(mysql_error(connection)));
    }
    
    MYSQL_RES* result = mysql_store_result(connection);
    if (!result) {
        throw std::runtime_error("Failed to get result: " + std::string(mysql_error(connection)));
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    Agent agent;
    
    if (row) {
        agent.id = std::stoi(row[0]);
        agent.name = row[1] ? row[1] : "";
        agent.avatarEmoji = "🎓";  // Default emoji, not from DB
        agent.description = row[2] ? row[2] : "";
        agent.systemPrompt = row[3] ? row[3] : "";
        
        // Model name from DB with fallback to default
        // Note: This is just the filename (e.g., "qwen2.5-1.5b-instruct-q4_k_m.gguf")
        // Full path construction happens in agent_manager or http_server
        agent.modelName = row[4] && strlen(row[4]) > 0 ? row[4] : "qwen2.5-1.5b-instruct-q4_k_m.gguf";
        std::cout << "[Database] Loaded agent " << agent.name << " with model: " << agent.modelName << std::endl;
        
        // Load temperature and max_tokens from database
        agent.parameters["temperature"] = row[5] ? row[5] : "0.7";
        agent.parameters["max_tokens"] = row[6] ? row[6] : "512";
        std::cout << "[Database] Agent parameters: temperature=" << agent.parameters["temperature"] 
                  << ", max_tokens=" << agent.parameters["max_tokens"] << std::endl;
    } else {
        mysql_free_result(result);
        throw std::runtime_error("Agent not found");
    }
    
    mysql_free_result(result);
    return agent;
}

std::vector<Agent> Database::getAllAgents() {
    std::vector<Agent> agents;
    
    const char* query = "SELECT agent_id, agent_name, specialization, system_prompt, model_name, temperature, max_tokens FROM agents WHERE is_active = 1 AND visible_to_students = 1";
    
    if (mysql_query(connection, query)) {
        throw std::runtime_error("Failed to query agents: " + std::string(mysql_error(connection)));
    }
    
    MYSQL_RES* result = mysql_store_result(connection);
    if (!result) {
        throw std::runtime_error("Failed to get result: " + std::string(mysql_error(connection)));
    }
    
    MYSQL_ROW row;
    while ((row = mysql_fetch_row(result))) {
        Agent agent;
        agent.id = std::stoi(row[0]);
        agent.name = row[1] ? row[1] : "";
        agent.avatarEmoji = "🎓";  // Default emoji, not from DB
        agent.description = row[2] ? row[2] : "";
        agent.systemPrompt = row[3] ? row[3] : "";
        
        // Model name from DB with fallback to default
        agent.modelName = row[4] && strlen(row[4]) > 0 ? row[4] : "qwen2.5-1.5b-instruct-q4_k_m.gguf";
        std::cout << "[Database] Agent " << agent.name << " uses model: " << agent.modelName << std::endl;
        
        // Load temperature and max_tokens
        agent.parameters["temperature"] = row[5] ? row[5] : "0.7";
        agent.parameters["max_tokens"] = row[6] ? row[6] : "512";
        
        agents.push_back(agent);
    }
    
    mysql_free_result(result);
    return agents;
}

std::vector<Agent> Database::getStudentVisibleAgents() {
    // For now, this is the same as getAllAgents since we filter by visible_to_students
    return getAllAgents();
}

void Database::storeMemory(int userId, int agentId, const std::string& userMessage, const std::string& agentResponse) {
    // Escape strings
    char* escapedUser = new char[userMessage.length() * 2 + 1];
    char* escapedAgent = new char[agentResponse.length() * 2 + 1];
    
    mysql_real_escape_string(connection, escapedUser, userMessage.c_str(), userMessage.length());
    mysql_real_escape_string(connection, escapedAgent, agentResponse.c_str(), agentResponse.length());
    
    std::ostringstream query;
    query << "INSERT INTO agent_memories (user_id, agent_id, interaction_type, user_message, agent_response, importance_score, created_at) VALUES "
          << "(" << userId << ", " << agentId << ", 'chat', '" << escapedUser << "', '" << escapedAgent << "', 5, NOW())";
    
    delete[] escapedUser;
    delete[] escapedAgent;
    
    if (mysql_query(connection, query.str().c_str())) {
        std::cerr << "Failed to store memory: " << mysql_error(connection) << std::endl;
    }
}

std::vector<std::string> Database::getRAGDocuments(int agentId, const std::vector<float>& embedding, int limit) {
    std::vector<std::string> documents;
    
    // Build embedding string for vector similarity search
    std::ostringstream embStr;
    embStr << "[";
    for (size_t i = 0; i < embedding.size(); ++i) {
        if (i > 0) embStr << ",";
        embStr << embedding[i];
    }
    embStr << "]";
    
    // Query using vector similarity (MariaDB vector functions)
    std::ostringstream query;
    query << "SELECT rd.content, VEC_DISTANCE(e.embedding_vector, VEC_FromText('" << embStr.str() << "')) as distance "
          << "FROM rag_documents rd "
          << "JOIN embeddings e ON rd.id = e.document_id "
          << "WHERE rd.agent_id = " << agentId << " "
          << "ORDER BY distance ASC "
          << "LIMIT " << limit;
    
    if (mysql_query(connection, query.str().c_str())) {
        std::cerr << "Failed to query RAG documents: " << mysql_error(connection) << std::endl;
        return documents;
    }
    
    MYSQL_RES* result = mysql_store_result(connection);
    if (!result) {
        return documents;
    }
    
    MYSQL_ROW row;
    while ((row = mysql_fetch_row(result))) {
        if (row[0]) {
            documents.push_back(row[0]);
        }
    }
    
    mysql_free_result(result);
    return documents;
}

void Database::storeEmbedding(int documentId, const std::vector<float>& embedding) {
    // Build embedding string
    std::ostringstream embStr;
    embStr << "[";
    for (size_t i = 0; i < embedding.size(); ++i) {
        if (i > 0) embStr << ",";
        embStr << embedding[i];
    }
    embStr << "]";
    
    std::ostringstream query;
    query << "INSERT INTO embeddings (document_id, embedding_vector, created_at) VALUES "
          << "(" << documentId << ", VEC_FromText('" << embStr.str() << "'), NOW())";
    
    if (mysql_query(connection, query.str().c_str())) {
        std::cerr << "Failed to store embedding: " << mysql_error(connection) << std::endl;
    }
}

std::vector<float> Database::getEmbedding(int embeddingId) {
    std::vector<float> embedding;
    
    std::ostringstream query;
    query << "SELECT VEC_ToText(embedding_vector) FROM embeddings WHERE id = " << embeddingId;
    
    if (mysql_query(connection, query.str().c_str())) {
        return embedding;
    }
    
    MYSQL_RES* result = mysql_store_result(connection);
    if (!result) {
        return embedding;
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    if (row && row[0]) {
        // Parse the vector string [1.23,4.56,...]
        std::string vecStr = row[0];
        // TODO: Implement vector string parsing
    }
    
    mysql_free_result(result);
    return embedding;
}

std::vector<std::pair<std::string, std::string>> Database::searchEducationalContent(const std::string& query, int limit) {
    std::vector<std::pair<std::string, std::string>> results;
    
    // Escape the search query
    char* escapedQuery = new char[query.length() * 2 + 1];
    mysql_real_escape_string(connection, escapedQuery, query.c_str(), query.length());
    
    // FULLTEXT search on educational_content table for generated lessons
    std::ostringstream sql;
    sql << "SELECT title, content_text, MATCH(title, content_text) AGAINST('" << escapedQuery 
        << "' IN NATURAL LANGUAGE MODE) as relevance "
        << "FROM educational_content "
        << "WHERE content_type = 'educational' "
        << "AND MATCH(title, content_text) AGAINST('" << escapedQuery << "' IN NATURAL LANGUAGE MODE) "
        << "ORDER BY relevance DESC "
        << "LIMIT " << limit;
    
    delete[] escapedQuery;
    
    std::cout << "[RAG] Searching educational content for: " << query << std::endl;
    
    if (mysql_query(connection, sql.str().c_str())) {
        std::cerr << "[RAG] Search query failed: " << mysql_error(connection) << std::endl;
        return results;
    }
    
    MYSQL_RES* result = mysql_store_result(connection);
    if (!result) {
        std::cerr << "[RAG] Failed to store result" << std::endl;
        return results;
    }
    
    MYSQL_ROW row;
    while ((row = mysql_fetch_row(result))) {
        if (row[0] && row[1]) {
            std::string title = row[0];
            std::string content = row[1];
            // Truncate content to avoid overwhelming the context
            if (content.length() > 1500) {
                content = content.substr(0, 1500) + "...";
            }
            results.push_back({title, content});
            std::cout << "[RAG] Found: " << title << " (relevance: " << (row[2] ? row[2] : "?") << ")" << std::endl;
        }
    }
    
    mysql_free_result(result);
    std::cout << "[RAG] Found " << results.size() << " relevant lessons" << std::endl;
    return results;
}
