<?php
session_start();
require_once __DIR__ . '/../config/db_conn.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_id'])) {
    $moduleId = (int)$_POST['module_id'];
    $professorId = $_SESSION['user_id'];
    
    try {
        // Get module details including file path
        $stmt = $conn->prepare("SELECT content, professor_id FROM modules WHERE id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify the module belongs to this professor
        if (!$module || $module['professor_id'] != $professorId) {
            $_SESSION['error'] = "You don't have permission to delete this module.";
            header("Location: ../professor/manage_modules.php");
            exit();
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete student progress related to this module
        $stmt = $conn->prepare("DELETE FROM student_progress WHERE module_id = ?");
        $stmt->execute([$moduleId]);
        
        // Delete quiz attempts related to quizzes in this module (if any)
        $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id IN (SELECT id FROM quizzes WHERE module_id = ?)");
        $stmt->execute([$moduleId]);
        
        // Delete quizzes related to this module
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE module_id = ?");
        $stmt->execute([$moduleId]);
        
        // Delete the module from database
        $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->execute([$moduleId]);
        
        // Delete the physical file if it exists
        if (!empty($module['content'])) {
            $filePath = __DIR__ . '/../' . $module['content'];
            
            if (file_exists($filePath) && is_file($filePath)) {
                unlink($filePath);
            }
            
            // Also check for converted PDF if it was a PPTX
            if (preg_match('/\.(pptx|ppt)$/i', $module['content'])) {
                $pdfPath = preg_replace('/\.(pptx|ppt)$/i', '.pdf', $filePath);
                if (file_exists($pdfPath) && is_file($pdfPath)) {
                    unlink($pdfPath);
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Module deleted successfully!";
        header("Location: ../professor/manage_modules.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting module: " . $e->getMessage();
        header("Location: ../professor/manage_modules.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../professor/manage_modules.php");
    exit();
}
?>