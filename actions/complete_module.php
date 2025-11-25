<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

// Check if required POST data exists
if (!isset($_POST['module_id']) || !isset($_POST['student_id'])) {
    header("Location: ../student/resources.php");
    exit();
}

$studentId = (int)$_POST['student_id'];
$moduleId = (int)$_POST['module_id'];

// Verify the student_id matches the session
if ($studentId !== $_SESSION['user_id']) {
    header("Location: ../student/resources.php");
    exit();
}

try {
    // Check if progress record exists
    $stmt = $conn->prepare("SELECT id FROM student_progress WHERE student_id = ? AND module_id = ?");
    $stmt->execute([$studentId, $moduleId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($progress) {
        // Update existing progress record
        $stmt = $conn->prepare("
            UPDATE student_progress 
            SET status = 'Completed', 
                completed_at = NOW(), 
                updated_at = NOW() 
            WHERE student_id = ? AND module_id = ?
        ");
        $stmt->execute([$studentId, $moduleId]);
    } else {
        // Create new progress record
        $stmt = $conn->prepare("
            INSERT INTO student_progress (student_id, module_id, status, started_at, completed_at) 
            VALUES (?, ?, 'Completed', NOW(), NOW())
        ");
        $stmt->execute([$studentId, $moduleId]);
    }
    
    // Redirect back to the module page
    header("Location: ../student/view_module.php?id=" . $moduleId . "&completed=1");
    exit();
    
} catch (PDOException $e) {
    // Log error and redirect
    error_log("Error completing module: " . $e->getMessage());
    header("Location: ../student/view_module.php?id=" . $moduleId . "&error=1");
    exit();
}
?>
