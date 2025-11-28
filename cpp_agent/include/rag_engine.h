#ifndef RAG_ENGINE_H
#define RAG_ENGINE_H

#include <string>
#include <vector>

// Forward declarations
class Database;
class LlamaCppClient;

class RAGEngine {
private:
    Database* database;
    LlamaCppClient* llamaClient_;

public:
    RAGEngine(Database* db, LlamaCppClient* llamaClient);
    ~RAGEngine();
    
    std::vector<std::string> search(int agentId, const std::string& query, int topK);
    void indexDocument(int agentId, int documentId, const std::string& content);
};

#endif // RAG_ENGINE_H
