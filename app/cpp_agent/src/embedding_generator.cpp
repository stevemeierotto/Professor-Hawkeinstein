#include "../include/embedding_generator.h"
#include "../include/llamacpp_client.h"
#include <iostream>

EmbeddingGenerator::EmbeddingGenerator(LlamaCppClient* client, int expectedDimension)
    : client_(client), expectedDimension_(expectedDimension) {
}

std::vector<float> EmbeddingGenerator::generate(const std::string& text) const {
    if (!client_) {
        std::cerr << "[EmbeddingGenerator] Llama client unavailable" << std::endl;
        return {};
    }

    if (text.empty()) {
        std::cerr << "[EmbeddingGenerator] Empty chunk received" << std::endl;
        return {};
    }

    try {
        auto embedding = client_->embed(text, expectedDimension_);
        if (static_cast<int>(embedding.size()) != expectedDimension_) {
            std::cerr << "[EmbeddingGenerator] Dimension mismatch. Expected " << expectedDimension_
                      << " got " << embedding.size() << std::endl;
            return {};
        }
        return embedding;
    } catch (const std::exception& ex) {
        std::cerr << "[EmbeddingGenerator] Failed to generate embedding: " << ex.what() << std::endl;
        return {};
    }
}
