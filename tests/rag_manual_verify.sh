#!/bin/bash
# Manual RAG validation helper

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LLAMA_URL="${LLAMA_SERVER_URL:-http://127.0.0.1:8090}"
AGENT_SERVICE_URL="${AGENT_SERVICE_URL:-http://127.0.0.1:8080}"
AGENT_LOG="/tmp/agent_service_full.log"

YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

function heading() {
    printf "\n${CYAN}==>${NC} %s\n" "$1"
}

function require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        printf "Command '%s' is required.\n" "$1"
        exit 1
    fi
}

require_command mysql
require_command php
require_command curl

heading "Seeding deterministic dataset"
SEED_OUTPUT=$(php "$PROJECT_ROOT/tests/rag_seed_content.php")
declare -A SEED_MAP=()
while IFS='=' read -r key value; do
    [[ -z "$key" ]] && continue
    SEED_MAP["$key"]="$value"
done <<< "$SEED_OUTPUT"

AGENT_ID="${SEED_MAP[AGENT_ID]:-}"
USER_ID="${SEED_MAP[USER_ID]:-}"
PRIMARY_CONTENT_ID="${SEED_MAP[PRIMARY_CONTENT_ID]:-}"
ALL_CONTENT_IDS="${SEED_MAP[ALL_CONTENT_IDS]:-}"
TEST_QUERY="${SEED_MAP[TEST_QUERY]:-}"
QUERY_VECTOR="${SEED_MAP[QUERY_VECTOR]:-}"

if [[ -z "$AGENT_ID" || -z "$USER_ID" || -z "$ALL_CONTENT_IDS" ]]; then
    echo "$SEED_OUTPUT"
    echo "Seed script did not return expected values."
    exit 1
fi

DB_HOST=$(php -r "require '$PROJECT_ROOT/config/database.php'; echo DB_HOST;" | tr -d '\n')
DB_PORT=$(php -r "require '$PROJECT_ROOT/config/database.php'; echo DB_PORT;" | tr -d '\n')
DB_NAME=$(php -r "require '$PROJECT_ROOT/config/database.php'; echo DB_NAME;" | tr -d '\n')
DB_USER=$(php -r "require '$PROJECT_ROOT/config/database.php'; echo DB_USER;" | tr -d '\n')
DB_PASS=$(php -r "require '$PROJECT_ROOT/config/database.php'; echo DB_PASS;" | tr -d '\n')

export MYSQL_PWD="$DB_PASS"
MYSQL_CMD=(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --default-character-set=utf8mb4 "$DB_NAME")

heading "Dataset overview"
printf "Agent ID: %s\nUser ID: %s\nContent IDs: %s\nPrimary content: %s\n" "$AGENT_ID" "$USER_ID" "$ALL_CONTENT_IDS" "$PRIMARY_CONTENT_ID"
printf "Test query: %s\n" "$TEST_QUERY"

heading "Content records"
"${MYSQL_CMD[@]}" -e "SELECT content_id, title, grade_level, subject, has_embeddings, embedding_count FROM educational_content WHERE content_id IN ($ALL_CONTENT_IDS);"

heading "Chunk metadata"
"${MYSQL_CMD[@]}" -e "SELECT content_id, JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '\$.agent_scope')) AS agent_scope, JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '\$.grade_level')) AS grade, JSON_UNQUOTE(JSON_EXTRACT(chunk_metadata, '\$.subject')) AS subject FROM content_embeddings WHERE content_id IN ($ALL_CONTENT_IDS);"

heading "Similarity ranking"
SIM_SQL="SELECT content_id, chunk_index, ROUND(1 - VEC_Cosine_Distance(embedding_vector, VEC_FromText('$QUERY_VECTOR')), 4) AS similarity FROM content_embeddings WHERE content_id IN ($ALL_CONTENT_IDS) ORDER BY similarity DESC;"
"${MYSQL_CMD[@]}" -e "$SIM_SQL"

heading "agent_service /agent/chat response"
CHAT_BODY=$(php -r '$payload=["userId"=>(int)$argv[1],"agentId"=>(int)$argv[2],"message"=>$argv[3]]; echo json_encode($payload, JSON_UNESCAPED_UNICODE);' "$USER_ID" "$AGENT_ID" "$TEST_QUERY")
CHAT_RESPONSE=$(curl -fsS -X POST -H "Content-Type: application/json" -d "$CHAT_BODY" "$AGENT_SERVICE_URL/agent/chat")
php -r 'echo json_encode(json_decode(stream_get_contents(STDIN), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;' <<< "$CHAT_RESPONSE"

heading "Recent agent_service logs"
if [[ -f "$AGENT_LOG" ]]; then
    tail -n 80 "$AGENT_LOG" | grep -E "RAGEngine|AgentManager" || true
else
    echo "Log file $AGENT_LOG not found."
fi

heading "Manual checklist"
printf "${YELLOW}1.${NC} Run ./tests/rag_flow.test for automated verification.\n"
printf "${YELLOW}2.${NC} Inspect SQL outputs above for metadata accuracy.\n"
printf "${YELLOW}3.${NC} Verify agent response references seeded context tokens (e.g., RAGPHASE6 anchors).\n"
printf "${YELLOW}4.${NC} Confirm log tail shows RAGSearch metrics and injection counts.\n"
