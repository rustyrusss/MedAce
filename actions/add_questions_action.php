<?php
session_start();
require_once '../config/db_conn.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $question_text = isset($_POST['question_text']) ? trim($_POST['question_text']) : '';
        $question_type = isset($_POST['question_type']) ? trim($_POST['question_type']) : 'multiple_choice';
        $options_json = isset($_POST['options_json']) ? $_POST['options_json'] : '[]';
        $correct_answer_value = isset($_POST['correct_answer_value']) ? $_POST['correct_answer_value'] : '';
        
        // Validate required fields
        if (empty($quiz_id) || empty($question_text) || empty($question_type)) {
            $_SESSION['error'] = "Please fill in all required fields.";
            header("Location: ../view/manage_questions.php?quiz_id=" . $quiz_id);
            exit();
        }
        
        // Verify quiz ownership
        $stmt = $conn->prepare("SELECT professor_id FROM quizzes WHERE id = ?");
        $stmt->execute([$quiz_id]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz || $quiz['professor_id'] != $_SESSION['user_id']) {
            $_SESSION['error'] = "Invalid quiz or you don't have permission to modify it.";
            header("Location: ../view/dashboard.php");
            exit();
        }
        
        // Parse options JSON
        $options = json_decode($options_json, true);
        if ($options === null) {
            $options = [];
        }
        
        // Convert options array to JSON string for database
        $options_db = json_encode($options);
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert question
        $stmt = $conn->prepare("
            INSERT INTO questions (quiz_id, question_text, question_type, options, correct_answer, time_limit) 
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        
        $stmt->execute([
            $quiz_id,
            $question_text,
            $question_type,
            $options_db,
            $correct_answer_value
        ]);
        
        $question_id = $conn->lastInsertId();
        
        // Insert answers for option-based questions
        if (in_array($question_type, ['multiple_choice', 'checkbox', 'dropdown', 'true_false']) && !empty($options)) {
            $stmt = $conn->prepare("
                INSERT INTO answers (question_id, answer_text, is_correct) 
                VALUES (?, ?, ?)
            ");
            
            // Split correct answers for checkbox type (can be comma-separated)
            $correct_answers = explode(',', $correct_answer_value);
            $correct_answers = array_map('trim', $correct_answers);
            
            foreach ($options as $option) {
                $is_correct = in_array($option['letter'], $correct_answers) ? 1 : 0;
                $stmt->execute([
                    $question_id,
                    $option['text'],
                    $is_correct
                ]);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Question added successfully!";
        header("Location: ../professor/manage_questions.php?quiz_id=" . $quiz_id);
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Error adding question: " . $e->getMessage());
        $_SESSION['error'] = "Error adding question: " . $e->getMessage();
        header("Location: ../professor/manage_questions.php?quiz_id=" . $quiz_id);
        exit();
    }
} else {
    // Not a POST request
    header("Location: ../professor/dashboard.php");
    exit();
}
?>