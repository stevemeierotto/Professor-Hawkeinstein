<?php
/**
 * Migration: Rename suggested_answer to correct_answer in short_essay questions
 * 
 * This migrates existing lesson question banks to use the correct field name
 * for short essay question answers, ensuring quiz grading can find the expected answer.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDb();
    
    // Get all short_essay question banks
    $stmt = $db->query("
        SELECT bank_id, questions 
        FROM lesson_question_banks 
        WHERE question_type = 'short_essay'
    ");
    
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $skipped = 0;
    
    foreach ($banks as $bank) {
        $questions = json_decode($bank['questions'], true);
        
        if (!$questions || !is_array($questions)) {
            $skipped++;
            continue;
        }
        
        $modified = false;
        foreach ($questions as &$question) {
            // If it has suggested_answer but not correct_answer, migrate it
            if (isset($question['suggested_answer']) && !isset($question['correct_answer'])) {
                $question['correct_answer'] = $question['suggested_answer'];
                unset($question['suggested_answer']);
                $modified = true;
            }
        }
        
        if ($modified) {
            // Update the bank with migrated questions
            $updateStmt = $db->prepare("
                UPDATE lesson_question_banks 
                SET questions = ? 
                WHERE bank_id = ?
            ");
            $updateStmt->execute([json_encode($questions), $bank['bank_id']]);
            $updated++;
        } else {
            $skipped++;
        }
    }
    
    echo "Migration complete!\n";
    echo "Banks updated: $updated\n";
    echo "Banks skipped (no changes needed): $skipped\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
