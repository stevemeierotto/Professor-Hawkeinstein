#include "../include/rag_engine.h"
#include "../include/database.h"
#include "../include/llamacpp_client.h"
#include "../include/embedding_generator.h"
#include <iostream>
#include <sstream>
#include <unordered_map>
#include <limits>
#include <algorithm>
#include <iomanip>

namespace {
constexpr int kDefaultTopK = 5;
constexpr float kDefaultSimilarityThreshold = 0.25f;
}

RAGEngine::RAGEngine(Database* db, LlamaCppClient* llamaClient) 
    : database(db),
      llamaClient_(llamaClient),
      defaultTopK_(kDefaultTopK),
      similarityThreshold_(kDefaultSimilarityThreshold),
      metric_("cosine") {
    if (llamaClient_) {
        embeddingGenerator_ = std::make_unique<EmbeddingGenerator>(llamaClient_, 384);
    }
    std::cout << "RAG Engine initialized" << std::endl;
}

RAGEngine::~RAGEngine() {
}

std::vector<RetrievedChunk> RAGEngine::search(const RAGSearchContext& context, const std::string& query) {
    std::vector<RetrievedChunk> filtered;

    if (!database) {
        std::cerr << "[RAGEngine] Database unavailable for search" << std::endl;
        return filtered;
    }

    if (!embeddingGenerator_) {
        std::cerr << "[RAGEngine] Embedding generator unavailable" << std::endl;
        return filtered;
    }

    if (query.empty()) {
        std::cerr << "[RAGEngine] Empty query provided" << std::endl;
        return filtered;
    }

    auto embedding = embeddingGenerator_->generate(query);
    if (embedding.empty()) {
        std::cerr << "[RAGEngine] Failed to generate query embedding" << std::endl;
        return filtered;
    }

    int effectiveTopK = context.topK > 0 ? context.topK : defaultTopK_;
    float minSimilarityThreshold = context.similarityThreshold > 0.0f ? context.similarityThreshold : similarityThreshold_;
    std::string effectiveMetric = context.metric.empty() ? metric_ : context.metric;

    VectorSearchFilters filters;
    filters.agentScope = context.agentScope;
    filters.gradeLevel = context.gradeLevel;
    filters.subject = context.subject;

    {
        std::ostringstream filterLog;
        filterLog << "[RAGEngine] RAGSearch filters agent=" << context.agentId
                  << " scope=" << (filters.agentScope.empty() ? "any" : filters.agentScope)
                  << " grade=" << (filters.gradeLevel.empty() ? "any" : filters.gradeLevel)
                  << " subject=" << (filters.subject.empty() ? "any" : filters.subject)
                  << " metric=" << effectiveMetric
                  << " topK=" << effectiveTopK
                  << " threshold=" << std::fixed << std::setprecision(2) << minSimilarityThreshold;
        std::cout << filterLog.str() << std::endl;
    }

    auto candidates = database->vectorSearch(embedding, effectiveTopK, effectiveMetric, &filters);

    std::unordered_map<int, RetrievedChunk> bestByContent;
    size_t droppedThreshold = 0;
    size_t droppedDuplicates = 0;
    float minSimilarity = std::numeric_limits<float>::max();
    float maxSimilarity = 0.0f;

    auto toRetrieved = [](const VectorSearchResult& row) {
        RetrievedChunk chunk;
        chunk.contentId = row.contentId;
        chunk.chunkIndex = row.chunkIndex;
        chunk.text = row.chunkText;
        chunk.similarity = row.similarity;
        chunk.gradeLevel = row.gradeLevel;
        chunk.subject = row.subject;
        chunk.agentScope = row.agentScope;
        return chunk;
    };

    for (const auto& candidate : candidates) {
        if (candidate.similarity < minSimilarityThreshold) {
            ++droppedThreshold;
            std::ostringstream dropLog;
            dropLog << "[RAGEngine] drop chunk content_id=" << candidate.contentId
                    << " sim=" << std::fixed << std::setprecision(2) << candidate.similarity
                    << " reason=below_threshold";
            std::cout << dropLog.str() << std::endl;
            continue;
        }

        auto converted = toRetrieved(candidate);
        auto it = bestByContent.find(candidate.contentId);
        if (it == bestByContent.end()) {
            bestByContent[candidate.contentId] = converted;
        } else if (candidate.similarity > it->second.similarity) {
            ++droppedDuplicates;
            std::ostringstream dedupeLog;
            dedupeLog << "[RAGEngine] dedupe replaced content_id=" << candidate.contentId
                      << " old_sim=" << std::fixed << std::setprecision(2) << it->second.similarity
                      << " new_sim=" << std::fixed << std::setprecision(2) << candidate.similarity;
            std::cout << dedupeLog.str() << std::endl;
            it->second = converted;
        } else {
            ++droppedDuplicates;
            std::ostringstream dedupeLog;
            dedupeLog << "[RAGEngine] dedupe dropped content_id=" << candidate.contentId
                      << " sim=" << std::fixed << std::setprecision(2) << candidate.similarity;
            std::cout << dedupeLog.str() << std::endl;
        }
    }

    filtered.reserve(bestByContent.size());
    for (const auto& entry : bestByContent) {
        filtered.push_back(entry.second);
        minSimilarity = std::min(minSimilarity, entry.second.similarity);
        maxSimilarity = std::max(maxSimilarity, entry.second.similarity);
    }

    std::sort(filtered.begin(), filtered.end(), [](const RetrievedChunk& a, const RetrievedChunk& b) {
        return a.similarity > b.similarity;
    });

    if (filtered.empty()) {
        minSimilarity = 0.0f;
        maxSimilarity = 0.0f;
    }

    std::ostringstream log;
    log << "[RAGEngine] RAGSearch metric=" << effectiveMetric
        << " topK_req=" << effectiveTopK
        << " candidates=" << candidates.size()
        << " kept=" << filtered.size()
        << " dropped_threshold=" << droppedThreshold
        << " dropped_dedupe=" << droppedDuplicates
        << " min_sim=" << std::fixed << std::setprecision(2) << minSimilarity
        << " max_sim=" << std::fixed << std::setprecision(2) << maxSimilarity;
    std::cout << log.str() << std::endl;

    return filtered;
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
