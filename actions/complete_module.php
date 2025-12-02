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
        // Update existing progress to completed
        $stmt = $conn->prepare("
            UPDATE student_progress 
            SET status = 'completed', completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$progress['id']]);
    } else {
        // Create new progress record as completed (in case it was never started)
        $stmt = $conn->prepare("
            INSERT INTO student_progress (student_id, module_id, status, started_at, completed_at) 
            VALUES (?, ?, 'completed', NOW(), NOW())
        ");
        $stmt->execute([$studentId, $moduleId]);
    }
    
    // ✅ NEW: Check if any quizzes are now unlocked by completing this module
    $stmt = $conn->prepare("
        SELECT id, title 
        FROM quizzes 
        WHERE prerequisite_module_id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$moduleId]);
    $unlockedQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build success message
    $successMessage = "🎉 Congratulations! You've completed the module: " . htmlspecialchars($module['title']);
    
    // Add unlocked quizzes notification
    if (!empty($unlockedQuizzes)) {
        $quizCount = count($unlockedQuizzes);
        $successMessage .= "<br><br>🔓 <strong>Great news!</strong> You've unlocked " . $quizCount . " quiz" . ($quizCount > 1 ? "es" : "") . ":<br>";
        
        foreach ($unlockedQuizzes as $quiz) {
            $successMessage .= "• " . htmlspecialchars($quiz['title']) . "<br>";
        }
        
        $successMessage .= '<br><a href="../member/quizzes.php" class="inline-block mt-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-semibold text-sm">View Available Quizzes</a>';
    }
    
    $_SESSION['success_message'] = $successMessage;
    header("Location: ../member/view_module.php?id=" . $moduleId);
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "An error occurred while updating your progress. Please try again.";
    error_log("Complete Module Error: " . $e->getMessage());
    header("Location: ../member/view_module.php?id=" . $moduleId);
    exit();
}
?>