<?php
require_once "../../config/database.php";
require_once "auth_check.php";
require_once "../helpers/system_agent_helper.php";
requireAdmin();
header("Content-Type: application/json");
$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty($input["draftId"])) { echo json_encode(["success"=>false,"message"=>"Missing draftId."]); exit; }
$draftId = intval($input["draftId"]);
$db = getDb();
$stmt = $db->prepare("SELECT * FROM course_drafts WHERE draft_id = ?");
$stmt->execute([$draftId]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$draft) { echo json_encode(["success"=>false,"message"=>"Draft not found."]); exit; }
$stmt = $db->prepare("SELECT standard_code, description FROM approved_standards WHERE draft_id = ? ORDER BY standard_id ASC");
$stmt->execute([$draftId]);
$standards = $stmt->fetchAll(PDO::FETCH_ASSOC);
$outline = organizeStandardsIntoOutline($standards);
if (empty($outline)) { $outline = generateOutlineWithLLM($draft, $standards); }
$outlineJson = json_encode($outline, JSON_PRETTY_PRINT);
$stmt = $db->prepare("INSERT INTO course_outlines (draft_id, outline_json) VALUES (?, ?)");
$stmt->execute([$draftId, $outlineJson]);
$db->prepare("UPDATE course_drafts SET status = \"outline_review\" WHERE draft_id = ?")->execute([$draftId]);
echo json_encode(["success"=>true,"outline"=>$outlineJson]);
function organizeStandardsIntoOutline($standards) {
    $units = []; $pendingLessons = [];
    foreach ($standards as $std) {
        $code = trim($std["standard_code"] ?? "");
        $desc = trim($std["description"] ?? "");
        if (preg_match("/^[A-Z]\\.$/", $code)) { $units[] = ["title"=>"$code $desc","description"=>"","lessons"=>array_reverse($pendingLessons)]; $pendingLessons = []; }
        elseif ($code === "N/A" && stripos($desc, "should understand") === false && strlen($desc) < 80) { $units[] = ["title"=>$desc,"description"=>"","lessons"=>array_reverse($pendingLessons)]; $pendingLessons = []; }
        elseif ($code === "N/A" && stripos($desc, "should understand") !== false) {}
        elseif (preg_match("/^[K\\d]-/", $code)) { $pendingLessons[] = ["title"=>"$code: ".substr($desc,0,60),"description"=>$desc,"standard_code"=>$code]; }
        elseif (preg_match("/^\\d+\\)$/", $code)) { $pendingLessons[] = ["title"=>substr($desc,0,60),"description"=>$desc,"standard_code"=>$code]; }
        elseif ($desc && $code !== "N/A") { $pendingLessons[] = ["title"=>($code?"$code: ":"").substr($desc,0,60),"description"=>$desc,"standard_code"=>$code]; }
    }
    if ($pendingLessons) { $units[] = ["title"=>"Additional Content","description"=>"","lessons"=>array_reverse($pendingLessons)]; }
    return $units;
}
function generateOutlineWithLLM($draft, $standards) {
    $standardsList = "";
    foreach ($standards as $std) { $standardsList .= "- ".($std["standard_code"]?$std["standard_code"].": ":"").$std["description"]."\\n"; }
    $prompt = "Create a course outline for \"".$draft["course_name"]."\". Return ONLY a JSON array of units. Each unit has title and lessons array.";
    $agentResponse = callSystemAgent("outline", $prompt);
    if (isset($agentResponse["response"]) && $agentResponse["response"]) { $resp = $agentResponse["response"]; if (preg_match("/\\[.*\\]/s", $resp, $matches)) { $decoded = json_decode($matches[0], true); if ($decoded) return $decoded; } }
    return [];
}
