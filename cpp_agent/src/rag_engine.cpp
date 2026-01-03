#include "../include/rag_engine.h"
#include "../include/database.h"
#include "../include/llamacpp_client.h"
#include <iostream>
#include <sstream>

RAGEngine::RAGEngine(Database* db, LlamaCppClient* llamaClient) 
    : database(db), llamaClient_(llamaClient) {
    std::cout << "RAG Engine initialized" << std::endl;
}

RAGEngine::~RAGEngine() {
}

std::vector<std::string> RAGEngine::search(int agentId, const std::string& query, int topK) {
    std::vector<std::string> results;
    
    try {
        // Use FULLTEXT search on educational_content table
        auto contentResults = database->searchEducationalContent(query, topK);
        
        // Format results for RAG context
        for (const auto& pair : contentResults) {
            std::ostringstream formatted;
            formatted << "## " << pair.first << "\n" << pair.second;
            results.push_back(formatted.str());
        }
        
        std::cout << "[RAGEngine] Returning " << results.size() << " context chunks" << std::endl;
        return results;
        
    } catch (const std::exception& e) {
        std::cerr << "RAG search error: " << e.what() << std::endl;
        return {};
    }
}

void RAGEngine::indexDocument(int agentId, int documentId, const std::string& content) {
    try {
        // Generate embedding for the document
        // TODO: Implement embeddings with llama.cpp
        std::vector<float> embedding; // Placeholder
        
        if (embedding.empty()) {
            std::cerr << "Failed to generate document embedding" << std::endl;
            return;
        }
        
        // Store embedding in database
        database->storeEmbedding(documentId, embedding);
        
        std::cout << "Document " << documentId << " indexed successfully" << std::endl;
        
    } catch (const std::exception& e) {
        std::cerr << "Document indexing error: " << e.what() << std::endl;
    }
}
