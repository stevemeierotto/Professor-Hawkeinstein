#include "../include/chunker.h"
#include <cctype>
#include <algorithm>

Chunker::Chunker(std::size_t chunkSize, std::size_t overlap)
    : chunkSize_(chunkSize), overlap_(std::min(overlap, chunkSize / 2)) {
    if (chunkSize_ == 0) {
        chunkSize_ = 750;
    }
}

std::vector<std::string> Chunker::splitSentences(const std::string& text) const {
    std::vector<std::string> sentences;
    std::string buffer;

    const auto flushBuffer = [&]() {
        std::string trimmed = trim(buffer);
        if (!trimmed.empty()) {
            sentences.push_back(trimmed);
        }
        buffer.clear();
    };

    for (std::size_t i = 0; i < text.size(); ++i) {
        const char current = text[i];
        buffer.push_back(current);

        if (current == '\n') {
            flushBuffer();
            continue;
        }

        if (current == '.' || current == '!' || current == '?') {
            flushBuffer();
        }
    }

    if (!buffer.empty()) {
        flushBuffer();
    }

    if (sentences.empty()) {
        std::string trimmed = trim(text);
        if (!trimmed.empty()) {
            sentences.push_back(trimmed);
        }
    }

    return sentences;
}

std::string Chunker::trim(const std::string& input) {
    auto begin = std::find_if_not(input.begin(), input.end(), [](unsigned char ch) { return std::isspace(ch); });
    auto end = std::find_if_not(input.rbegin(), input.rend(), [](unsigned char ch) { return std::isspace(ch); }).base();
    if (begin >= end) {
        return "";
    }
    return std::string(begin, end);
}

std::string Chunker::tailOverlap(const std::string& text) const {
    if (overlap_ == 0 || text.size() <= overlap_) {
        return text;
    }
    return text.substr(text.size() - overlap_);
}

std::vector<Chunk> Chunker::chunk(const std::string& text) const {
    std::vector<Chunk> chunks;
    auto sentences = splitSentences(text);
    if (sentences.empty()) {
        return chunks;
    }

    std::string current;
    int chunkIndex = 0;

    for (const auto& sentence : sentences) {
        if (sentence.empty()) {
            continue;
        }

        if (current.empty()) {
            current = sentence;
            continue;
        }

        if (current.size() + 1 + sentence.size() <= chunkSize_) {
            current.append(" ").append(sentence);
            continue;
        }

        chunks.push_back({ trim(current), chunkIndex++ });
        std::string overlapTail = tailOverlap(current);
        current = overlapTail;
        if (!current.empty() && !std::isspace(static_cast<unsigned char>(current.back()))) {
            current.push_back(' ');
        }
        current.append(sentence);

        if (current.size() > chunkSize_) {
            chunks.push_back({ trim(current), chunkIndex++ });
            current.clear();
        }
    }

    if (!current.empty()) {
        chunks.push_back({ trim(current), chunkIndex++ });
    }

    return chunks;
}
