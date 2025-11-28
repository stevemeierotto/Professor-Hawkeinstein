<?php
/**
 * Model Validation Helper
 * Validates model files exist and provides fallback logic
 */

// Get available models from filesystem
function getAvailableModels() {
    $modelsPath = getenv('MODELS_BASE_PATH') ?: '/home/steve/Professor_Hawkeinstein/models';
    
    if (!is_dir($modelsPath)) {
        error_log("[Models] Models directory not found: $modelsPath");
        return [];
    }
    
    $models = [];
    $files = scandir($modelsPath);
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'gguf') {
            $models[] = $file;
        }
    }
    
    return $models;
}

// Validate model exists, return valid model or fallback
function validateModelOrFallback($modelName) {
    $modelsPath = getenv('MODELS_BASE_PATH') ?: '/home/steve/Professor_Hawkeinstein/models';
    $defaultModel = getenv('DEFAULT_MODEL') ?: 'qwen2.5-1.5b-instruct-q4_k_m.gguf';
    
    // Check if requested model exists
    $modelPath = $modelsPath . '/' . $modelName;
    if (file_exists($modelPath)) {
        error_log("[Models] Validated model: $modelName");
        return $modelName;
    }
    
    // Model not found, use fallback
    error_log("[Models] Model '$modelName' not found, falling back to: $defaultModel");
    
    // Verify fallback exists
    $fallbackPath = $modelsPath . '/' . $defaultModel;
    if (!file_exists($fallbackPath)) {
        error_log("[Models] ERROR: Default model not found at: $fallbackPath");
        throw new Exception("No valid models available");
    }
    
    return $defaultModel;
}

// Get model file info
function getModelInfo($modelName) {
    $modelsPath = getenv('MODELS_BASE_PATH') ?: '/home/steve/Professor_Hawkeinstein/models';
    $modelPath = $modelsPath . '/' . $modelName;
    
    if (!file_exists($modelPath)) {
        return null;
    }
    
    $sizeBytes = filesize($modelPath);
    $sizeMB = round($sizeBytes / (1024 * 1024), 2);
    $sizeGB = round($sizeMB / 1024, 2);
    
    return [
        'filename' => $modelName,
        'path' => $modelPath,
        'size_bytes' => $sizeBytes,
        'size_mb' => $sizeMB,
        'size_gb' => $sizeGB,
        'exists' => true
    ];
}
