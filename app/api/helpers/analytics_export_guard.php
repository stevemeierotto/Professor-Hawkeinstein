<?php
// 🚨 ANALYTICS EXPORT SAFEGUARDS
// Research exports MUST have soft limits to prevent accidental overexposure
// 
// This module enforces export limits and requires confirmation for large exports.
//
// Created: 2026-02-08 (Phase 4: Operational Safeguards)

// Export limits
define('MAX_EXPORT_ROWS', 50000);  // Maximum rows per export
define('MAX_EXPORT_DATE_RANGE_DAYS', 365);  // Maximum date range (1 year)
define('EXPORT_WARNING_THRESHOLD', 10000);  // Warn when approaching limit

/**
 * Validate export parameters against soft limits
 * 
 * @param string $dataset Dataset being exported
 * @param array $dateRange ['start' => date, 'end' => date]
 * @param int $estimatedRows Estimated row count
 * @param bool $confirmed Whether user has confirmed large export
 * @return array ['valid' => bool, 'warnings' => array, 'errors' => array]
 */
function validateExportParameters($dataset, $dateRange, $estimatedRows = 0, $confirmed = false) {
    $warnings = [];
    $errors = [];
    
    // Check date range
    if (isset($dateRange['start']) && isset($dateRange['end'])) {
        $startTime = strtotime($dateRange['start']);
        $endTime = strtotime($dateRange['end']);
        
        if ($startTime === false || $endTime === false) {
            $errors[] = 'Invalid date format. Use YYYY-MM-DD.';
        } elseif ($endTime < $startTime) {
            $errors[] = 'End date must be after start date.';
        } else {
            $rangeDays = ($endTime - $startTime) / 86400;
            
            if ($rangeDays > MAX_EXPORT_DATE_RANGE_DAYS) {
                $errors[] = sprintf(
                    'Date range too large. Maximum %d days allowed (requested: %d days).',
                    MAX_EXPORT_DATE_RANGE_DAYS,
                    $rangeDays
                );
            } elseif ($rangeDays > MAX_EXPORT_DATE_RANGE_DAYS * 0.8) {
                $warnings[] = sprintf(
                    'Large date range: %d days (max: %d days)',
                    $rangeDays,
                    MAX_EXPORT_DATE_RANGE_DAYS
                );
            }
        }
    }
    
    // Check row count
    if ($estimatedRows > MAX_EXPORT_ROWS) {
        $errors[] = sprintf(
            'Export too large. Maximum %s rows allowed (estimated: %s rows).',
            number_format(MAX_EXPORT_ROWS),
            number_format($estimatedRows)
        );
    } elseif ($estimatedRows > EXPORT_WARNING_THRESHOLD) {
        $warnings[] = sprintf(
            'Large export: %s rows (limit: %s rows)',
            number_format($estimatedRows),
            number_format(MAX_EXPORT_ROWS)
        );
    }
    
    // Check if confirmation required
    $requiresConfirmation = !empty($warnings) && !$confirmed;
    
    if ($requiresConfirmation) {
        $errors[] = 'This export requires confirmation due to size or date range. Add confirmed=1 parameter.';
    }
    
    return [
        'valid' => empty($errors),
        'warnings' => $warnings,
        'errors' => $errors,
        'requires_confirmation' => $requiresConfirmation
    ];
}

/**
 * Check if export parameters are within safe limits
 * 
 * @param array $dateRange Date range for export
 * @param int $rowCount Actual or estimated row count
 * @return bool True if within limits
 */
function isExportWithinLimits($dateRange, $rowCount) {
    // Check date range
    if (isset($dateRange['start']) && isset($dateRange['end'])) {
        $rangeDays = (strtotime($dateRange['end']) - strtotime($dateRange['start'])) / 86400;
        if ($rangeDays > MAX_EXPORT_DATE_RANGE_DAYS) {
            return false;
        }
    }
    
    // Check row count
    if ($rowCount > MAX_EXPORT_ROWS) {
        return false;
    }
    
    return true;
}

/**
 * Get export metadata for logging
 * 
 * @param array $dateRange Date range
 * @param int $rowCount Row count
 * @param string $format Export format
 * @return array Metadata array
 */
function getExportMetadata($dateRange, $rowCount, $format) {
    $metadata = [
        'row_count' => $rowCount,
        'format' => $format,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (isset($dateRange['start']) && isset($dateRange['end'])) {
        $rangeDays = (strtotime($dateRange['end']) - strtotime($dateRange['start'])) / 86400;
        $metadata['date_range_days'] = round($rangeDays, 1);
        $metadata['start_date'] = $dateRange['start'];
        $metadata['end_date'] = $dateRange['end'];
    }
    
    return $metadata;
}

/**
 * Create export confirmation token
 * 
 * Generates a token that expires after 5 minutes for export confirmation
 * 
 * @param string $dataset Dataset name
 * @param array $parameters Export parameters
 * @return string Confirmation token
 */
function createExportConfirmationToken($dataset, $parameters) {
    $data = [
        'dataset' => $dataset,
        'parameters' => $parameters,
        'expires' => time() + 300  // 5 minutes
    ];
    
    return base64_encode(json_encode($data));
}

/**
 * Validate export confirmation token
 * 
 * @param string $token Confirmation token
 * @return array|null Decoded token data or null if invalid
 */
function validateExportConfirmationToken($token) {
    $json = @base64_decode($token);
    if ($json === false) {
        return null;
    }
    
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    
    // Check expiration
    if (!isset($data['expires']) || $data['expires'] < time()) {
        return null;
    }
    
    return $data;
}
