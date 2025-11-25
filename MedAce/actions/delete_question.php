<?php
session_start();
require_once '../config/db_conn.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    $_SESSION['error'] = "Please log in as a professor.";
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];

// Get parameters - handle both GET formats
$question_id = 0;
$quiz_id = 0;

// Method 1: Standard GET parameters
if (isset($_GET['question_id'])) {
    $question_id = intval($_GET['question_id']);
}

if (isset($_GET['quiz_id'])) {
    $quiz_id = intval($_GET['quiz_id']);
}

// Validate inputs
if (!$question_id || !$quiz_id) {
    $_SESSION['error'] = "Invalid question or quiz ID. Question: $question_id, Quiz: $quiz_id";
    header("Location: ../professor/manage_quizzes.php");
    exit();
}

try {
    // Verify that this question belongs to a quiz owned by this professor
    $stmt = $conn->prepare("
        SELECT q.id, q.question_text, qz.title as quiz_title
        FROM questions q 
        JOIN quizzes qz ON q.quiz_id = qz.id 
        WHERE q.id = ? AND qz.id = ? AND qz.professor_id = ?
    ");
    $stmt->execute([$question_id, $quiz_id, $professor_id]);
    
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        $_SESSION['error'] = "Question not found or you don't have permission to delete it.";
        header("Location: ../professor/manage_questions.php?quiz_id=" . $quiz_id);
        exit();
    }
    
    // Begin transaction for safe deletion
    $conn->beginTransaction();
    
    // Step 1: Delete all answers associated with this question
    $stmt = $conn->prepare("DELETE FROM answers WHERE question_id = ?");
    $stmt->execute([$question_id]);
    
    // Step 2: Delete the question itself
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    
    // Commit the transaction
    $conn->commit();
    
    $_SESSION['success'] = "Question deleted successfully! 🗑️";
    
} catch (PDOException $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error deleting question: " . $e->getMessage());
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    
} catch (Exception $e) {
    // Rollback on any other error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error deleting question: " . $e->getMessage());
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to manage questions page
header("Location: ../professor/manage_questions.php?quiz_id=" . $quiz_id);
exit();
?>