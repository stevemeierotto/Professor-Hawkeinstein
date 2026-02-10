#include "../include/database.h"
#include <iostream>
#include <sstream>
#include <stdexcept>
#include <ctime>
#include <cstring>
#include <iomanip>
#include <vector>
#include <cstdlib>
#include <algorithm>
#include <cctype>

namespace {
constexpr int kEmbeddingDimension = 384;

std::string serializeVector(const std::vector<float>& embedding) {
    std::ostringstream embStr;
    embStr.setf(std::ios::fixed);
    embStr << "[";
    for (size_t i = 0; i < embedding.size(); ++i) {
        if (i > 0) {
            embStr << ",";
        }
        embStr << std::setprecision(8) << embedding[i];
    }
    embStr << "]";
    return embStr.str();
}

std::vector<float> parseVector(const std::string& text) {
    std::vector<float> values;
    if (text.empty()) {
        return values;
    }

    std::string trimmed = text;
    if (trimmed.front() == '[' && trimmed.back() == ']') {
        trimmed = trimmed.substr(1, trimmed.size() - 2);
    }

    std::stringstream ss(trimmed);
    std::string token;
    while (std::getline(ss, token, ',')) {
        if (!token.empty()) {
            values.push_back(static_cast<float>(std::atof(token.c_str())));
        }
    }

    return values;
}
}

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
    (void)agentId; // Agent-specific filtering will be layered on later phases

    if (embedding.empty() || limit <= 0) {
        return documents;
    }

    if (embedding.size() != static_cast<size_t>(kEmbeddingDimension)) {
        std::cerr << "[Database] getRAGDocuments rejected vector with dimension " << embedding.size()
                  << " (expected " << kEmbeddingDimension << ")" << std::endl;
        return documents;
    }

    const std::string serializedEmbedding = serializeVector(embedding);

    const char* sql =
        "SELECT sc.title, ce.text_chunk, VEC_Cosine_Distance(ce.embedding_vector, VEC_FromText(?)) AS distance "
        "FROM content_embeddings ce "
        "JOIN educational_content sc ON ce.content_id = sc.content_id "
        "ORDER BY distance ASC "
        "LIMIT ?";

    MYSQL_STMT* stmt = mysql_stmt_init(connection);
    if (!stmt) {
        std::cerr << "[Database] Failed to init statement for vector search" << std::endl;
        return documents;
    }

    if (mysql_stmt_prepare(stmt, sql, std::strlen(sql)) != 0) {
        std::cerr << "[Database] Failed to prepare vector search: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return documents;
    }

    MYSQL_BIND params[2];
    std::memset(params, 0, sizeof(params));
    params[0].buffer_type = MYSQL_TYPE_STRING;
    params[0].buffer = const_cast<char*>(serializedEmbedding.c_str());
    params[0].buffer_length = serializedEmbedding.size();

    unsigned long embedLength = serializedEmbedding.size();
    params[0].length = &embedLength;

    int limitValue = limit;
    params[1].buffer_type = MYSQL_TYPE_LONG;
    params[1].buffer = &limitValue;
    params[1].buffer_length = sizeof(limitValue);

    if (mysql_stmt_bind_param(stmt, params) != 0) {
        std::cerr << "[Database] Failed to bind vector search params: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return documents;
    }

    if (mysql_stmt_execute(stmt) != 0) {
        std::cerr << "[Database] Vector search execute failed: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return documents;
    }

    if (mysql_stmt_store_result(stmt) != 0) {
        std::cerr << "[Database] Failed to buffer vector search results: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return documents;
    }

    MYSQL_BIND resultBinds[3];
    std::memset(resultBinds, 0, sizeof(resultBinds));
    bool titleNull = false;
    bool chunkNull = false;
    unsigned long titleLen = 0;
    unsigned long chunkLen = 0;
    float distance = 0.0f;

    char titleStub = '\0';
    char chunkStub = '\0';

    resultBinds[0].buffer_type = MYSQL_TYPE_STRING;
    resultBinds[0].buffer = &titleStub;
    resultBinds[0].buffer_length = 0;
    resultBinds[0].is_null = &titleNull;
    resultBinds[0].length = &titleLen;

    resultBinds[1].buffer_type = MYSQL_TYPE_STRING;
    resultBinds[1].buffer = &chunkStub;
    resultBinds[1].buffer_length = 0;
    resultBinds[1].is_null = &chunkNull;
    resultBinds[1].length = &chunkLen;

    resultBinds[2].buffer_type = MYSQL_TYPE_FLOAT;
    resultBinds[2].buffer = &distance;

    if (mysql_stmt_bind_result(stmt, resultBinds) != 0) {
        std::cerr << "[Database] Failed to bind vector search results: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return documents;
    }

    auto fetchStringColumn = [&](unsigned int columnIndex, unsigned long valueLength) {
        std::string value;
        if (valueLength == 0) {
            return value;
        }

        std::vector<char> buffer(valueLength + 1, '\0');
        MYSQL_BIND fetchBind{};
        fetchBind.buffer_type = MYSQL_TYPE_STRING;
        fetchBind.buffer = buffer.data();
        fetchBind.buffer_length = valueLength;

        if (mysql_stmt_fetch_column(stmt, &fetchBind, columnIndex, 0) == 0) {
            value.assign(buffer.data(), valueLength);
        }

        return value;
    };

    while (true) {
        int fetchStatus = mysql_stmt_fetch(stmt);
        if (fetchStatus == MYSQL_NO_DATA) {
            break;
        }
        if (fetchStatus != 0 && fetchStatus != MYSQL_DATA_TRUNCATED) {
            std::cerr << "[Database] Vector search fetch error: " << mysql_stmt_error(stmt) << std::endl;
            break;
        }

        std::string title = titleNull ? "" : fetchStringColumn(0, titleLen);
        std::string chunk = chunkNull ? "" : fetchStringColumn(1, chunkLen);

        if (chunk.empty()) {
            continue;
        }

        std::ostringstream formatted;
        formatted << "## " << (title.empty() ? "Referenced Content" : title) << "\n" << chunk;
        documents.push_back(formatted.str());
    }

    mysql_stmt_close(stmt);
    return documents;
}

std::vector<VectorSearchResult> Database::vectorSearch(const std::vector<float>& embedding,
                                                       int topK,
                                                       const std::string& metric,
                                                       const VectorSearchFilters* filters) {
    std::vector<VectorSearchResult> results;

    if (embedding.empty()) {
        return results;
    }

    if (embedding.size() != static_cast<size_t>(kEmbeddingDimension)) {
        std::cerr << "[Database] vectorSearch rejected vector with dimension " << embedding.size()
                  << " (expected " << kEmbeddingDimension << ")" << std::endl;
        return results;
    }

    int effectiveTopK = topK > 0 ? topK : 5;

    std::string metricLower = metric;
    std::transform(metricLower.begin(), metricLower.end(), metricLower.begin(), [](unsigned char c) {
        return static_cast<char>(std::tolower(c));
    });

    bool useL2 = (metricLower == "l2" || metricLower == "euclidean" || metricLower == "l2_distance");
    const std::string distanceFunction = useL2 ? "VEC_L2_Distance" : "VEC_Cosine_Distance";

    const std::string serializedEmbedding = serializeVector(embedding);
    std::string sql =
        "SELECT content_id, chunk_index, text_chunk, "
        "JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '$.grade_level')) AS grade_level, "
        "JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '$.subject')) AS subject, "
        "JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '$.agent_scope')) AS agent_scope, ";
    sql += distanceFunction;
    sql += "(embedding_vector, VEC_FromText(?)) AS distance "
           "FROM content_embeddings WHERE 1=1";

    if (filters) {
        if (filters->hasAgentScope()) {
            sql += " AND JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '$.agent_scope')) = ?";
        }
        if (filters->hasGradeLevel()) {
            sql += " AND JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '$.grade_level')) = ?";
        }
        if (filters->hasSubject()) {
            sql += " AND JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '$.subject')) = ?";
        }
    }

    sql += " ORDER BY distance ASC LIMIT ?";

    MYSQL_STMT* stmt = mysql_stmt_init(connection);
    if (!stmt) {
        std::cerr << "[Database] Failed to init statement for vectorSearch" << std::endl;
        return results;
    }

    if (mysql_stmt_prepare(stmt, sql.c_str(), sql.length()) != 0) {
        std::cerr << "[Database] Failed to prepare vectorSearch: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return results;
    }

    unsigned long embedLength = serializedEmbedding.size();
    int limitValue = effectiveTopK;

    std::vector<MYSQL_BIND> params;
    params.reserve(5);

    MYSQL_BIND embeddingBind{};
    embeddingBind.buffer_type = MYSQL_TYPE_STRING;
    embeddingBind.buffer = const_cast<char*>(serializedEmbedding.c_str());
    embeddingBind.buffer_length = serializedEmbedding.size();
    embeddingBind.length = &embedLength;
    params.push_back(embeddingBind);

    std::vector<std::string> stringStorage;
    std::vector<unsigned long> lengthStorage;
    stringStorage.reserve(3);
    lengthStorage.reserve(3);

    if (filters) {
        if (filters->hasAgentScope()) {
            stringStorage.push_back(filters->agentScope);
            lengthStorage.push_back(static_cast<unsigned long>(stringStorage.back().size()));
            MYSQL_BIND bind{};
            bind.buffer_type = MYSQL_TYPE_STRING;
            bind.buffer = const_cast<char*>(stringStorage.back().c_str());
            bind.buffer_length = stringStorage.back().size();
            bind.length = &lengthStorage.back();
            params.push_back(bind);
        }
        if (filters->hasGradeLevel()) {
            stringStorage.push_back(filters->gradeLevel);
            lengthStorage.push_back(static_cast<unsigned long>(stringStorage.back().size()));
            MYSQL_BIND bind{};
            bind.buffer_type = MYSQL_TYPE_STRING;
            bind.buffer = const_cast<char*>(stringStorage.back().c_str());
            bind.buffer_length = stringStorage.back().size();
            bind.length = &lengthStorage.back();
            params.push_back(bind);
        }
        if (filters->hasSubject()) {
            stringStorage.push_back(filters->subject);
            lengthStorage.push_back(static_cast<unsigned long>(stringStorage.back().size()));
            MYSQL_BIND bind{};
            bind.buffer_type = MYSQL_TYPE_STRING;
            bind.buffer = const_cast<char*>(stringStorage.back().c_str());
            bind.buffer_length = stringStorage.back().size();
            bind.length = &lengthStorage.back();
            params.push_back(bind);
        }
    }

    MYSQL_BIND limitBind{};
    limitBind.buffer_type = MYSQL_TYPE_LONG;
    limitBind.buffer = &limitValue;
    limitBind.buffer_length = sizeof(limitValue);
    params.push_back(limitBind);

    if (mysql_stmt_bind_param(stmt, params.data()) != 0) {
        std::cerr << "[Database] Failed to bind vectorSearch params: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return results;
    }

    if (mysql_stmt_execute(stmt) != 0) {
        std::cerr << "[Database] vectorSearch execute failed: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return results;
    }

    if (mysql_stmt_store_result(stmt) != 0) {
        std::cerr << "[Database] Failed to buffer vectorSearch results: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return results;
    }

    MYSQL_BIND resultBinds[7];
    std::memset(resultBinds, 0, sizeof(resultBinds));
    int contentId = 0;
    int chunkIndex = 0;
    bool textNull = false;
    unsigned long textLength = 0;
    bool gradeNull = false;
    unsigned long gradeLength = 0;
    bool subjectNull = false;
    unsigned long subjectLength = 0;
    bool scopeNull = false;
    unsigned long scopeLength = 0;
    float distance = 0.0f;
    char textStub = '\0';
    char gradeStub = '\0';
    char subjectStub = '\0';
    char scopeStub = '\0';

    resultBinds[0].buffer_type = MYSQL_TYPE_LONG;
    resultBinds[0].buffer = &contentId;
    resultBinds[0].buffer_length = sizeof(contentId);

    resultBinds[1].buffer_type = MYSQL_TYPE_LONG;
    resultBinds[1].buffer = &chunkIndex;
    resultBinds[1].buffer_length = sizeof(chunkIndex);

    resultBinds[2].buffer_type = MYSQL_TYPE_STRING;
    resultBinds[2].buffer = &textStub;
    resultBinds[2].buffer_length = 0;
    resultBinds[2].is_null = &textNull;
    resultBinds[2].length = &textLength;

    resultBinds[3].buffer_type = MYSQL_TYPE_STRING;
    resultBinds[3].buffer = &gradeStub;
    resultBinds[3].buffer_length = 0;
    resultBinds[3].is_null = &gradeNull;
    resultBinds[3].length = &gradeLength;

    resultBinds[4].buffer_type = MYSQL_TYPE_STRING;
    resultBinds[4].buffer = &subjectStub;
    resultBinds[4].buffer_length = 0;
    resultBinds[4].is_null = &subjectNull;
    resultBinds[4].length = &subjectLength;

    resultBinds[5].buffer_type = MYSQL_TYPE_STRING;
    resultBinds[5].buffer = &scopeStub;
    resultBinds[5].buffer_length = 0;
    resultBinds[5].is_null = &scopeNull;
    resultBinds[5].length = &scopeLength;

    resultBinds[6].buffer_type = MYSQL_TYPE_FLOAT;
    resultBinds[6].buffer = &distance;
    resultBinds[6].buffer_length = sizeof(distance);

    if (mysql_stmt_bind_result(stmt, resultBinds) != 0) {
        std::cerr << "[Database] Failed to bind vectorSearch results: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return results;
    }

    auto fetchStringColumn = [&](unsigned int columnIndex, unsigned long valueLength) {
        std::string value;
        if (valueLength == 0) {
            return value;
        }

        std::vector<char> buffer(valueLength + 1, '\0');
        MYSQL_BIND fetchBind{};
        fetchBind.buffer_type = MYSQL_TYPE_STRING;
        fetchBind.buffer = buffer.data();
        fetchBind.buffer_length = valueLength;

        if (mysql_stmt_fetch_column(stmt, &fetchBind, columnIndex, 0) == 0) {
            value.assign(buffer.data(), valueLength);
        }

        return value;
    };

    while (true) {
        int fetchStatus = mysql_stmt_fetch(stmt);
        if (fetchStatus == MYSQL_NO_DATA) {
            break;
        }
        if (fetchStatus != 0 && fetchStatus != MYSQL_DATA_TRUNCATED) {
            std::cerr << "[Database] vectorSearch fetch error: " << mysql_stmt_error(stmt) << std::endl;
            break;
        }

        VectorSearchResult row{};
        row.contentId = contentId;
        row.chunkIndex = chunkIndex;
        row.chunkText = textNull ? "" : fetchStringColumn(2, textLength);
        row.gradeLevel = gradeNull ? "" : fetchStringColumn(3, gradeLength);
        row.subject = subjectNull ? "" : fetchStringColumn(4, subjectLength);
        row.agentScope = scopeNull ? "" : fetchStringColumn(5, scopeLength);
        if (row.chunkText.empty()) {
            continue;
        }

        float similarity = 0.0f;
        if (useL2) {
            similarity = 1.0f / (1.0f + std::max(distance, 0.0f));
        } else {
            similarity = 1.0f - distance;
        }
        similarity = std::clamp(similarity, 0.0f, 1.0f);
        row.similarity = similarity;
        results.push_back(std::move(row));
    }

    mysql_stmt_close(stmt);
    return results;
}

void Database::storeEmbedding(int documentId, const std::vector<float>& embedding) {
    if (embedding.size() != static_cast<size_t>(kEmbeddingDimension)) {
        std::cerr << "[Database] storeEmbedding rejected vector with dimension " << embedding.size()
                  << " (expected " << kEmbeddingDimension << ")" << std::endl;
        return;
    }

    const std::string serializedEmbedding = serializeVector(embedding);
    const char* sql =
        "INSERT INTO content_embeddings (content_id, chunk_index, text_chunk, chunk_metadata, embedding_vector, vector_dimension, model_used) "
        "VALUES (?, 0, '', NULL, VEC_FromText(?), ?, 'llama.cpp')";

    MYSQL_STMT* stmt = mysql_stmt_init(connection);
    if (!stmt) {
        std::cerr << "[Database] Failed to init statement for embedding insert" << std::endl;
        return;
    }

    if (mysql_stmt_prepare(stmt, sql, std::strlen(sql)) != 0) {
        std::cerr << "[Database] Failed to prepare embedding insert: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return;
    }

    int contentId = documentId;
    int dimension = kEmbeddingDimension;
    unsigned long vectorLength = serializedEmbedding.size();

    MYSQL_BIND params[3];
    std::memset(params, 0, sizeof(params));
    params[0].buffer_type = MYSQL_TYPE_LONG;
    params[0].buffer = &contentId;
    params[0].buffer_length = sizeof(contentId);

    params[1].buffer_type = MYSQL_TYPE_STRING;
    params[1].buffer = const_cast<char*>(serializedEmbedding.c_str());
    params[1].buffer_length = serializedEmbedding.size();
    params[1].length = &vectorLength;

    params[2].buffer_type = MYSQL_TYPE_LONG;
    params[2].buffer = &dimension;
    params[2].buffer_length = sizeof(dimension);

    if (mysql_stmt_bind_param(stmt, params) != 0) {
        std::cerr << "[Database] Failed to bind embedding insert params: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return;
    }

    if (mysql_stmt_execute(stmt) != 0) {
        std::cerr << "[Database] Embedding insert failed: " << mysql_stmt_error(stmt) << std::endl;
    }

    mysql_stmt_close(stmt);
}

std::vector<float> Database::getEmbedding(int embeddingId) {
    std::vector<float> embedding;

    const char* sql = "SELECT VEC_ToText(embedding_vector) FROM content_embeddings WHERE id = ?";
    MYSQL_STMT* stmt = mysql_stmt_init(connection);
    if (!stmt) {
        std::cerr << "[Database] Failed to init statement for getEmbedding" << std::endl;
        return embedding;
    }

    if (mysql_stmt_prepare(stmt, sql, std::strlen(sql)) != 0) {
        std::cerr << "[Database] Failed to prepare getEmbedding: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return embedding;
    }

    MYSQL_BIND param{};
    param.buffer_type = MYSQL_TYPE_LONG;
    param.buffer = &embeddingId;
    param.buffer_length = sizeof(embeddingId);
    if (mysql_stmt_bind_param(stmt, &param) != 0) {
        std::cerr << "[Database] Failed to bind getEmbedding param: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return embedding;
    }

    if (mysql_stmt_execute(stmt) != 0) {
        std::cerr << "[Database] getEmbedding execute failed: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return embedding;
    }

    MYSQL_BIND resultBind{};
    bool isNull = false;
    unsigned long valueLength = 0;
    resultBind.buffer_type = MYSQL_TYPE_STRING;
    resultBind.is_null = &isNull;
    resultBind.length = &valueLength;

    if (mysql_stmt_bind_result(stmt, &resultBind) != 0) {
        std::cerr << "[Database] Failed to bind getEmbedding result: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return embedding;
    }

    if (mysql_stmt_store_result(stmt) != 0) {
        std::cerr << "[Database] Failed to buffer getEmbedding result: " << mysql_stmt_error(stmt) << std::endl;
        mysql_stmt_close(stmt);
        return embedding;
    }

    if (mysql_stmt_fetch(stmt) == 0 && !isNull && valueLength > 0) {
        std::vector<char> buffer(valueLength + 1, '\0');
        MYSQL_BIND fetchBind{};
        fetchBind.buffer_type = MYSQL_TYPE_STRING;
        fetchBind.buffer = buffer.data();
        fetchBind.buffer_length = valueLength;

        if (mysql_stmt_fetch_column(stmt, &fetchBind, 0, 0) == 0) {
            embedding = parseVector(std::string(buffer.data(), valueLength));
        }
    }

    mysql_stmt_close(stmt);
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
