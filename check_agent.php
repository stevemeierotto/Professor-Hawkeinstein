<?php
require_once 'config/database.php';

$db = getDB();
$stmt = $db->prepare("SELECT agent_name, temperature, max_tokens, LENGTH(system_prompt) as prompt_length, system_prompt FROM agents WHERE agent_name LIKE '%Hawkeinstein%' OR is_student_advisor = 1");
$stmt->execute();
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($agents as $agent) {
    echo "Agent: {$agent['agent_name']}\n";
    echo "Temperature: {$agent['temperature']}\n";
    echo "Max Tokens: {$agent['max_tokens']}\n";
    echo "Prompt Length: {$agent['prompt_length']} chars\n";
    echo "System Prompt:\n{$agent['system_prompt']}\n";
    echo str_repeat("-", 80) . "\n";
}
