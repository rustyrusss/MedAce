<?php
/**
 * TEST ENDPOINT - Module Loading Diagnostic
 * Location: Save this as /config/test_modules.php
 * Access: http://yoursite.com/config/test_modules.php
 */

session_start();
header('Content-Type: application/json');

// Check session
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'error' => 'Not logged in',
        'session_exists' => false
    ]));
}

$studentId = $_SESSION['user_id'];

try {
    // Load database connection
    require_once __DIR__ . '/db_conn.php';
    
    echo json_encode([
        'step_1_database' => 'Connected',
        'step_2_student_id' => $studentId,
        'step_3_running_query' => true
    ]);
    
    // Run the actual query from resources.php
    $stmt = $conn->prepare("
        SELECT 
            m.id, 
            m.title, 
            m.description,
            m.status as module_status,
            COALESCE(sp.status, 'Pending') AS student_status
        FROM modules m
        LEFT JOIN student_progress sp ON sp.module_id = m.id AND sp.student_id = ?
        WHERE m.status IN ('active', 'published')
        ORDER BY m.display_order ASC, m.created_at DESC
    ");
    
    $stmt->execute([$studentId]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return detailed results
    echo json_encode([
        'success' => true,
        'student_id' => $studentId,
        'total_modules' => count($modules),
        'modules' => $modules,
        'query_executed' => true,
        'database_connected' => true
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ], JSON_PRETTY_PRINT);
}
?>