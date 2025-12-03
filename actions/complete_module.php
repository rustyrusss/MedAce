<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)$_POST['student_id'];
    $moduleId = (int)$_POST['module_id'];
    
    // Verify the student ID matches the session
    if ($studentId !== $_SESSION['user_id']) {
        header("Location: ../member/resources.php");
        exit();
    }
    
    try {
        // Check if progress record exists
        $stmt = $conn->prepare("SELECT id FROM student_progress WHERE student_id = ? AND module_id = ?");
        $stmt->execute([$studentId, $moduleId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($progress) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE student_progress 
                SET status = 'Completed', completed_at = NOW() 
                WHERE student_id = ? AND module_id = ?
            ");
            $stmt->execute([$studentId, $moduleId]);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO student_progress (student_id, module_id, status, started_at, completed_at) 
                VALUES (?, ?, 'Completed', NOW(), NOW())
            ");
            $stmt->execute([$studentId, $moduleId]);
        }
        
        // Redirect back to the module view with success parameter
        header("Location: ../member/view_module.php?id=" . $moduleId . "&completed=1");
        exit();
        
    } catch (PDOException $e) {
        error_log("Error completing module: " . $e->getMessage());
        header("Location: ../member/view_module.php?id=" . $moduleId . "&error=1");
        exit();
    }
} else {
    header("Location: ../member/resources.php");
    exit();
}
?>