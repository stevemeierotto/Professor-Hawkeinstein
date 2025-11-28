#include "../include/rag_engine.h"
#include "../include/database.h"
#include "../include/llamacpp_client.h"
#include <iostream>

RAGEngine::RAGEngine(Database* db, LlamaCppClient* llamaClient) 
    : database(db), llamaClient_(llamaClient) {
    std::cout << "RAG Engine initialized" << std::endl;
}

RAGEngine::~RAGEngine() {
}

std::vector<std::string> RAGEngine::search(int agentId, const std::string& query, int topK) {
    try {
        // Generate embedding for the query
        // TODO: Implement embeddings with llama.cpp
        std::vector<float> queryEmbedding; // Placeholder
        
        if (queryEmbedding.empty()) {
            std::cerr << "Failed to generate query embedding" << std::endl;
            return {};
        }
        
        // Search database using vector similarity
        return database->getRAGDocuments(agentId, queryEmbedding, topK);
        
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
