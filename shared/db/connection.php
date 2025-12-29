<?php
/**
 * Shared Database Connection
 * 
 * Provides a clean interface to the database for subsystems.
 * Wraps config/database.php to provide consistent access.
 * 
 * MUST NOT contain:
 * - Subsystem-specific queries
 * - Business logic
 * - Table-specific operations
 * 
 * See: docs/ARCHITECTURE.md
 */

// Load the main database configuration
require_once __DIR__ . '/../../config/database.php';

/**
 * Get a PDO database connection
 * 
 * This is a wrapper around the existing getDB() function.
 * Provides a consistent interface for subsystems.
 * 
 * @return PDO Database connection
 */
function db_connect() {
    return getDB();
}

/**
 * Execute a prepared statement and return results
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array Query results as associative array
 */
function db_query($sql, $params = []) {
    $pdo = db_connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Execute a prepared statement and return single row
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array|null Single row as associative array, or null if not found
 */
function db_query_one($sql, $params = []) {
    $pdo = db_connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Execute an INSERT/UPDATE/DELETE and return affected row count
 * 
 * @param string $sql SQL statement with placeholders
 * @param array $params Parameters to bind
 * @return int Number of affected rows
 */
function db_execute($sql, $params = []) {
    $pdo = db_connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Execute an INSERT and return the last insert ID
 * 
 * @param string $sql INSERT statement with placeholders
 * @param array $params Parameters to bind
 * @return string|int Last insert ID
 */
function db_insert($sql, $params = []) {
    $pdo = db_connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Begin a database transaction
 * 
 * @return bool True on success
 */
function db_begin_transaction() {
    return db_connect()->beginTransaction();
}

/**
 * Commit current transaction
 * 
 * @return bool True on success
 */
function db_commit() {
    return db_connect()->commit();
}

/**
 * Rollback current transaction
 * 
 * @return bool True on success
 */
function db_rollback() {
    return db_connect()->rollBack();
}

/**
 * Check if currently in a transaction
 * 
 * @return bool True if in transaction
 */
function db_in_transaction() {
    return db_connect()->inTransaction();
}
