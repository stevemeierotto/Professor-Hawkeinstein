#pragma once

#include <vector>
#include <string>

class LlamaCppClient;

class EmbeddingGenerator {
public:
    explicit EmbeddingGenerator(LlamaCppClient* client, int expectedDimension = 384);
    std::vector<float> generate(const std::string& text) const;
    int expectedDimension() const { return expectedDimension_; }

private:
    LlamaCppClient* client_;
    int expectedDimension_;
};
