#!/usr/bin/env php
<?php


$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/database.php';

const LLAMA_DEFAULT_URL = 'http://127.0.0.1:8090';
const RAG_AGENT_SCOPE = 'phase6_math_scope';
const RAG_TEST_QUERY = 'How can I reinforce RAGPHASE6-ANCHOR-ADDITION skills for first graders?';

try {
    main();
} catch (Throwable $e) {
    fwrite(STDERR, "[rag-seed] ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

function main(): void {
    $llamaUrl = rtrim(getenv('LLAMA_SERVER_URL') ?: LLAMA_DEFAULT_URL, '/');
    log_status('Using llama-server at ' . $llamaUrl);

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP curl extension is required for rag_seed_content.php');
    }

    ensureLlamaHealthy($llamaUrl);

    $db = getDB(true);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userId = (int)fetchSingleValue($db, 'SELECT user_id FROM users ORDER BY user_id LIMIT 1');
    if ($userId === 0) {
        throw new RuntimeException('No users found. Run setup_root_admin.sh before seeding RAG content.');
    }

    $agentId = (int)fetchSingleValue($db, 'SELECT agent_id FROM agents WHERE is_active = 1 ORDER BY agent_id LIMIT 1');
    if ($agentId === 0) {
        throw new RuntimeException('No active agents found. Create at least one agent before running rag_seed_content.php');
    }

    $dataset = buildDataset();
    $primaryContentId = null;
    $contentIds = [];

    $db->beginTransaction();
    try {
        foreach ($dataset as $entry) {
            $contentId = seedContentEntry($db, $llamaUrl, $entry, $userId);
            $contentIds[] = $contentId;
            if (($entry['priority'] ?? '') === 'primary') {
                $primaryContentId = $contentId;
            }
            linkAgentToContent($db, $agentId, $contentId, (float)$entry['relevance']);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    if ($primaryContentId === null) {
        $primaryContentId = $contentIds[0];
    }

    $queryVector = vectorToText(embedText($llamaUrl, RAG_TEST_QUERY));

    $result = [
        'AGENT_ID' => (string)$agentId,
        'USER_ID' => (string)$userId,
        'PRIMARY_CONTENT_ID' => (string)$primaryContentId,
        'ALL_CONTENT_IDS' => implode(',', $contentIds),
        'AGENT_SCOPE' => RAG_AGENT_SCOPE,
        'TEST_QUERY' => RAG_TEST_QUERY,
        'QUERY_VECTOR' => $queryVector,
    ];

    foreach ($result as $key => $value) {
        echo $key . '=' . $value . PHP_EOL;
    }
}

function log_status(string $message): void {
    fwrite(STDERR, '[rag-seed] ' . $message . PHP_EOL);
}

function ensureLlamaHealthy(string $llamaUrl): void {
    $healthUrl = $llamaUrl . '/health';
    $ch = curl_init($healthUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    if ($response === false) {
        throw new RuntimeException('Unable to reach llama-server health endpoint at ' . $healthUrl);
    }
    $status = curlResponseCode($ch);
    curl_close($ch);
    if ($status >= 400) {
        throw new RuntimeException('llama-server health check failed with HTTP status ' . $status);
    }
}

function fetchSingleValue(PDO $db, string $sql): ?string {
    $stmt = $db->query($sql);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string)$value;
}

function buildDataset(): array {
    $additionAnchor = 'RAGPHASE6-ANCHOR-ADDITION';
    $subtractionAnchor = 'RAGPHASE6-ANCHOR-SUBTRACTION';
    $practiceAnchor = 'RAGPHASE6-ANCHOR-PRACTICE';

    return [
        [
            'slug' => 'rag-phase6-addition',
            'title' => 'Phase 6 Addition Reference',
            'grade' => 'grade_1',
            'subject' => 'mathematics',
            'text' => "$additionAnchor Addition is combining two quantities to make a larger quantity. Use concrete objects, number bonds, and repeated story prompts to keep students grounded. When you see $additionAnchor in a student request, they are explicitly asking for a first-grade friendly explanation of addition.",
            'anchor' => $additionAnchor,
            'priority' => 'primary',
            'relevance' => 0.98,
        ],
        [
            'slug' => 'rag-phase6-subtraction',
            'title' => 'Phase 6 Subtraction Reference',
            'grade' => 'grade_1',
            'subject' => 'mathematics',
            'text' => "$subtractionAnchor Subtraction is the act of taking away. Frame it as removing snacks from a plate or stepping backwards on a number line. Mention $subtractionAnchor when clarifying the difference between taking away and counting on.",
            'anchor' => $subtractionAnchor,
            'priority' => 'secondary',
            'relevance' => 0.82,
        ],
        [
            'slug' => 'rag-phase6-practice',
            'title' => 'Phase 6 Practice Habits',
            'grade' => 'grade_2',
            'subject' => 'mathematics',
            'text' => "$practiceAnchor Encourage math journals, exit tickets, and playful chants to rehearse facts. The marker $practiceAnchor tells the tutor that the student needs practice routines more than new instruction.",
            'anchor' => $practiceAnchor,
            'priority' => 'secondary',
            'relevance' => 0.76,
        ],
    ];
}

function seedContentEntry(PDO $db, string $llamaUrl, array $entry, int $userId): int {
    $url = 'https://tests.professorhawkeinstein.local/' . $entry['slug'];
    purgeContentByUrl($db, $url);

    $contentId = insertContentRow($db, $url, $entry, $userId);
    $vectorText = vectorToText(embedText($llamaUrl, $entry['text']));
    insertEmbeddingRow($db, $contentId, $entry, $vectorText);
    updateEmbeddingStats($db, $contentId, 1);

    log_status('Seeded ' . $entry['slug'] . ' as content_id ' . $contentId);
    return $contentId;
}

function purgeContentByUrl(PDO $db, string $url): void {
    $stmt = $db->prepare('SELECT content_id FROM educational_content WHERE url = :url');
    $stmt->execute([':url' => $url]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE FROM content_embeddings WHERE content_id IN ($placeholders)")->execute($ids);
    $db->prepare("DELETE FROM agent_content_links WHERE content_id IN ($placeholders)")->execute($ids);
    $db->prepare("DELETE FROM educational_content WHERE content_id IN ($placeholders)")->execute($ids);
}

function insertContentRow(PDO $db, string $url, array $entry, int $userId): int {
    $sql = <<<SQL
        INSERT INTO educational_content (
            url, title, content_type, content_html, content_text, metadata,
            credibility_score, domain, scraped_by, review_status,
            grade_level, subject, is_added_to_rag, has_embeddings, embedding_count
        ) VALUES (
            :url, :title, 'educational', :html, :text, :metadata,
            0.95, :domain, :scraped_by, 'approved',
            :grade, :subject, 1, 0, 0
        )
    SQL;

    $html = '<p>' . htmlspecialchars($entry['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    $metadata = json_encode([
        'tags' => ['rag_phase6', 'math', 'deterministic'],
        'slug' => $entry['slug'],
        'anchor' => $entry['anchor'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':url' => $url,
        ':title' => $entry['title'],
        ':html' => $html,
        ':text' => $entry['text'],
        ':metadata' => $metadata,
        ':domain' => 'tests.professorhawkeinstein.local',
        ':scraped_by' => $userId,
        ':grade' => $entry['grade'],
        ':subject' => $entry['subject'],
    ]);

    return (int)$db->lastInsertId();
}

function insertEmbeddingRow(PDO $db, int $contentId, array $entry, string $vectorText): void {
    $chunkMetadata = json_encode([
        'grade_level' => $entry['grade'],
        'subject' => $entry['subject'],
        'agent_scope' => RAG_AGENT_SCOPE,
        'slug' => $entry['slug'],
        'anchor' => $entry['anchor'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $sql = <<<SQL
        INSERT INTO content_embeddings (
            content_id, chunk_index, text_chunk, chunk_metadata,
            embedding_vector, vector_dimension, model_used
        ) VALUES (
            :content_id, 0, :text_chunk, :metadata,
            VEC_FromText(:vector), 384, 'llama.cpp'
        )
    SQL;

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':content_id' => $contentId,
        ':text_chunk' => $entry['text'],
        ':metadata' => $chunkMetadata,
        ':vector' => $vectorText,
    ]);
}

function updateEmbeddingStats(PDO $db, int $contentId, int $chunkCount): void {
    $stmt = $db->prepare('UPDATE educational_content SET has_embeddings = 1, embedding_count = :count, last_embedded = NOW() WHERE content_id = :id');
    $stmt->execute([
        ':count' => $chunkCount,
        ':id' => $contentId,
    ]);
}

function linkAgentToContent(PDO $db, int $agentId, int $contentId, float $score): void {
    $sql = 'INSERT INTO agent_content_links (agent_id, content_id, relevance_score) VALUES (:agent, :content, :score)
            ON DUPLICATE KEY UPDATE relevance_score = VALUES(relevance_score)';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':agent' => $agentId,
        ':content' => $contentId,
        ':score' => $score,
    ]);
}

function embedText(string $llamaUrl, string $text): array {
    $payload = json_encode(['content' => $text], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($llamaUrl . '/embedding');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Embedding request failed: ' . $error);
    }

    $status = curlResponseCode($ch);
    curl_close($ch);
    if ($status >= 400) {
        throw new RuntimeException('Embedding endpoint returned HTTP ' . $status);
    }

    $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    $embedding = null;
    if (isset($data['embedding']) && is_array($data['embedding'])) {
        $embedding = $data['embedding'];
    } elseif (isset($data['data'][0]['embedding']) && is_array($data['data'][0]['embedding'])) {
        $embedding = $data['data'][0]['embedding'];
    }

    if ($embedding === null) {
        throw new RuntimeException('Embedding response missing vector data');
    }

    if (count($embedding) !== 384) {
        throw new RuntimeException('Expected 384-dim embedding, received ' . count($embedding));
    }

    return array_map('floatval', $embedding);
}

function vectorToText(array $vector): string {
    $parts = array_map(static fn (float $value): string => sprintf('%.8f', $value), $vector);
    return '[' . implode(',', $parts) . ']';
}

function curlResponseCode($ch): int {
    $infoKey = defined('CURLINFO_RESPONSE_CODE') ? CURLINFO_RESPONSE_CODE : CURLINFO_HTTP_CODE;
    return (int)curl_getinfo($ch, $infoKey);
}
