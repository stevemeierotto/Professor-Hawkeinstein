#pragma once

#include <string>
#include <vector>

struct Chunk {
    std::string text;
    int index;
};

class Chunker {
public:
    Chunker(std::size_t chunkSize = 750, std::size_t overlap = 150);
    std::vector<Chunk> chunk(const std::string& text) const;

private:
    std::size_t chunkSize_;
    std::size_t overlap_;

    std::vector<std::string> splitSentences(const std::string& text) const;
    static std::string trim(const std::string& input);
    std::string tailOverlap(const std::string& text) const;
};
