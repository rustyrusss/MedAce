<?php
session_start();
require_once '../config/db_conn.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../member/resources.php");
    exit();
}

$studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : $_SESSION['user_id'];
$moduleId = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;

if ($moduleId === 0) {
    $_SESSION['error_message'] = "Invalid module ID.";
    header("Location: ../member/resources.php");
    exit();
}

try {
    // Check if the module exists and is active/published
    $stmt = $conn->prepare("SELECT id, title FROM modules WHERE id = ? AND (status = 'active' OR status = 'published')");
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        $_SESSION['error_message'] = "Module not found or not available.";
        header("Location: ../member/resources.php");
        exit();
    }
    
    // Check if progress record exists
    $stmt = $conn->prepare("
        SELECT id, status 
        FROM student_progress 
        WHERE student_id = ? AND module_id = ?
    ");
    $stmt->execute([$studentId, $moduleId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($progress) {
        // Update existing progress to Completed
        $stmt = $conn->prepare("
            UPDATE student_progress 
            SET status = 'Completed', completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$progress['id']]);
    } else {
        // Create new progress record as Completed (in case it was never started)
        $stmt = $conn->prepare("
            INSERT INTO student_progress (student_id, module_id, status, started_at, completed_at) 
            VALUES (?, ?, 'Completed', NOW(), NOW())
        ");
        $stmt->execute([$studentId, $moduleId]);
    }
    
    $_SESSION['success_message'] = "🎉 Congratulations! You've completed the module: " . htmlspecialchars($module['title']);
    header("Location: ../member/view_module.php?id=" . $moduleId);
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "An error occurred while updating your progress. Please try again.";
    error_log("Complete Module Error: " . $e->getMessage());
    header("Location: ../member/view_module.php?id=" . $moduleId);
    exit();
}
?>