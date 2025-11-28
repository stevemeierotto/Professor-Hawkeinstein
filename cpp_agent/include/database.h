#ifndef DATABASE_H
#define DATABASE_H

#include <string>
#include <vector>
#include <map>
#include <mysql/mysql.h>

// Forward declaration - no includes to avoid circular dependency
struct Agent {
    int id;
    std::string name;
    std::string avatarEmoji;
    std::string description;
    std::string systemPrompt;
    std::string modelName;
    std::map<std::string, std::string> parameters;
};

class Database {
private:
    MYSQL* connection;
    std::string host;
    int port;
    std::string dbName;
    std::string user;
    std::string password;
    
    void connect();
    void disconnect();
    
public:
    Database(const std::string& host, int port, const std::string& dbName, 
             const std::string& user, const std::string& password);
    ~Database();
    
    Agent getAgent(int agentId);
    std::vector<Agent> getAllAgents();
    std::vector<Agent> getStudentVisibleAgents();
    void storeMemory(int userId, int agentId, const std::string& userMessage, const std::string& agentResponse);
    std::vector<std::string> getRAGDocuments(int agentId, const std::vector<float>& embedding, int limit);
    void storeEmbedding(int documentId, const std::vector<float>& embedding);
    std::vector<float> getEmbedding(int embeddingId);
};

#endif // DATABASE_H
