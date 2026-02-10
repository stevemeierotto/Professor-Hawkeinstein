<?php
/**
 * Deprecated embedding helper.
 * RAG is handled exclusively by agent_service, so PHP callers must not
 * attempt vector math here.
 */

function ragEmbeddingGuard(string $feature): void {
    $message = "[EmbeddingGenerator] {$feature} is disabled. RAG handled by agent_service.";
    error_log($message);
    throw new RuntimeException($message);
}

class EmbeddingGenerator {
    public function __construct() {
        ragEmbeddingGuard('EmbeddingGenerator::__construct');
    }

    public function __call($name, $arguments) {
        ragEmbeddingGuard("EmbeddingGenerator::{$name}");
    }
}

function generateEmbedding($text) {
    ragEmbeddingGuard('generateEmbedding');
}

function chunkAndEmbed($text) {
    ragEmbeddingGuard('chunkAndEmbed');
}
