<?php
/**
 * List Student Advisor Instances API
 * Admins can view all student advisor instances for management
 * Can filter by student or advisor type
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

setCORSHeaders();

$admin = requireAdmin();

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
require_rate_limit_auto('admin_list_student_advisors');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    
    // Get query parameters for filtering
    $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
    $advisorTypeId = isset($_GET['advisor_type_id']) ? intval($_GET['advisor_type_id']) : null;
    $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Build query
    $query = "
        SELECT 
            sa.advisor_instance_id,
            sa.student_id,
            sa.advisor_type_id,
            sa.created_at,
            sa.last_interaction,
            sa.is_active,
            u.username,
            u.email,
            a.agent_name,
            a.agent_type,
            (SELECT COUNT(*) FROM student_advisors WHERE student_id = sa.student_id) as total_instances
        FROM student_advisors sa
        JOIN users u ON sa.student_id = u.user_id
        JOIN agents a ON sa.advisor_type_id = a.agent_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($studentId !== null) {
        $query .= " AND sa.student_id = ?";
        $params[] = $studentId;
    }
    
    if ($advisorTypeId !== null) {
        $query .= " AND sa.advisor_type_id = ?";
        $params[] = $advisorTypeId;
    }
    
    if ($isActive !== null) {
        $query .= " AND sa.is_active = ?";
        $params[] = $isActive;
    }
    
    $query .= " ORDER BY sa.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM student_advisors sa
        WHERE 1=1
    ";
    
    $countParams = [];
    if ($studentId !== null) {
        $countQuery .= " AND sa.student_id = ?";
        $countParams[] = $studentId;
    }
    if ($advisorTypeId !== null) {
        $countQuery .= " AND sa.advisor_type_id = ?";
        $countParams[] = $advisorTypeId;
    }
    if ($isActive !== null) {
        $countQuery .= " AND sa.is_active = ?";
        $countParams[] = $isActive;
    }
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($countParams);
    $countResult = $countStmt->fetch();
    $total = $countResult['total'];
    
    echo json_encode([
        'success' => true,
        'instances' => $instances,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'page' => floor($offset / $limit) + 1
        ]
    ]);
    
} catch (Exception $e) {
    error_log("List advisor instances error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve advisor instances: ' . $e->getMessage()]);
}

?>
