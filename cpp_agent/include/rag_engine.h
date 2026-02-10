#ifndef RAG_ENGINE_H
#define RAG_ENGINE_H

#include <string>
#include <vector>
#include <memory>

// Forward declarations
class Database;
class LlamaCppClient;
class EmbeddingGenerator;

struct RetrievedChunk {
    int contentId;
    int chunkIndex;
    std::string text;
    float similarity;
    std::string gradeLevel;
    std::string subject;
    std::string agentScope;
};

struct RAGSearchContext {
    int agentId = -1;
    std::string agentScope;
    std::string gradeLevel;
    std::string subject;
    int topK = -1;
    float similarityThreshold = -1.0f;
    std::string metric;
};

class RAGEngine {
private:
    Database* database;
    LlamaCppClient* llamaClient_;
    std::unique_ptr<EmbeddingGenerator> embeddingGenerator_;
    int defaultTopK_;
    float similarityThreshold_;
    std::string metric_;

public:
    RAGEngine(Database* db, LlamaCppClient* llamaClient);
    ~RAGEngine();
    
    std::vector<RetrievedChunk> search(const RAGSearchContext& context, const std::string& query);
    void indexDocument(int agentId, int documentId, const std::string& content);
};

#endif // RAG_ENGINE_H
