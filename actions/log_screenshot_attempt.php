<?php
session_start();
require_once '../config/db_conn.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['student_id']) || !isset($data['quiz_id'])) {
    http_response_code(400);
    exit();
}

try {
    // Insert screenshot attempt log
    $stmt = $conn->prepare("
        INSERT INTO screenshot_attempts 
        (student_id, quiz_id, attempt_id, attempted_at, ip_address, user_agent) 
        VALUES (?, ?, ?, NOW(), ?, ?)
    ");
    
    $stmt->execute([
        $data['student_id'],
        $data['quiz_id'],
        $data['attempt_id'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // If table doesn't exist, silently fail (optional logging)
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => 'Table not found']);
}
?>