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

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../professor/manage_quizzes.php");
    exit();
}

try {
    // Get form data
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    $question_text = isset($_POST['question_text']) ? trim($_POST['question_text']) : '';
    $question_type = isset($_POST['question_type']) ? trim($_POST['question_type']) : '';
    
    // Validate required fields
    if (empty($question_id) || empty($quiz_id) || empty($question_text) || empty($question_type)) {
        throw new Exception("Please fill in all required fields. Missing: " . 
            (!$question_id ? "question_id " : "") . 
            (!$quiz_id ? "quiz_id " : "") . 
            (!$question_text ? "question_text " : "") . 
            (!$question_type ? "question_type" : ""));
    }
    
    // Verify ownership - check that this question belongs to a quiz owned by this professor
    $stmt = $conn->prepare("
        SELECT q.id, q.quiz_id, qz.professor_id, qz.title
        FROM questions q 
        JOIN quizzes qz ON q.quiz_id = qz.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$question_id]);
    $question_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question_check) {
        throw new Exception("Question not found.");
    }
    
    if ($question_check['professor_id'] != $professor_id) {
        throw new Exception("You don't have permission to edit this question.");
    }
    
    if ($question_check['quiz_id'] != $quiz_id) {
        throw new Exception("Question does not belong to this quiz.");
    }
    
    // Process options based on input format
    $options = [];
    $correct_answer_value = '';
    
    // Check if we're getting options_json (from add form or edit with options)
    if (isset($_POST['options_json']) && !empty($_POST['options_json'])) {
        $options = json_decode($_POST['options_json'], true);
        if ($options === null) {
            $options = [];
        }
        $correct_answer_value = isset($_POST['correct_answer_value']) ? $_POST['correct_answer_value'] : '';
    }
    // Check if we're getting individual option fields (edit_option1, edit_option2, etc.)
    else if (isset($_POST['edit_option1'])) {
        $option_fields = [];
        $index = 1;
        
        // Collect all edit_option fields
        while (isset($_POST['edit_option' . $index])) {
            $option_text = trim($_POST['edit_option' . $index]);
            if (!empty($option_text)) {
                $letter = chr(64 + $index); // A, B, C, D...
                $options[] = [
                    'letter' => $letter,
                    'text' => $option_text
                ];
            }
            $index++;
        }
        
        // Get correct answer from radio button
        // Try different possible field names
        if (isset($_POST['correct_answer_edit_' . $question_id])) {
            $correct_answer_value = $_POST['correct_answer_edit_' . $question_id];
        } else if (isset($_POST['correct_answer'])) {
            // If it's an answer ID, convert to letter
            $answer_id = intval($_POST['correct_answer']);
            
            // Find which option was selected
            $stmt = $conn->prepare("SELECT id FROM answers WHERE question_id = ? ORDER BY id ASC");
            $stmt->execute([$question_id]);
            $existing_answers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $answer_index = array_search($answer_id, $existing_answers);
            if ($answer_index !== false) {
                $correct_answer_value = chr(65 + $answer_index); // A, B, C, D...
            }
        }
    }
    // For questions without options (short_answer, paragraph, essay)
    else {
        $options = [];
        $correct_answer_value = '';
    }
    
    // Convert options array to JSON string for database
    $options_db = json_encode($options);
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Update question
    $stmt = $conn->prepare("
        UPDATE questions 
        SET question_text = ?, 
            question_type = ?, 
            options = ?, 
            correct_answer = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $question_text,
        $question_type,
        $options_db,
        $correct_answer_value,
        $question_id
    ]);
    
    // Delete old answers
    $stmt = $conn->prepare("DELETE FROM answers WHERE question_id = ?");
    $stmt->execute([$question_id]);
    
    // Insert new answers for option-based questions
    if (in_array($question_type, ['multiple_choice', 'checkbox', 'dropdown', 'true_false']) && !empty($options)) {
        $stmt = $conn->prepare("
            INSERT INTO answers (question_id, answer_text, is_correct) 
            VALUES (?, ?, ?)
        ");
        
        // Split correct answers (for checkbox type - can be comma-separated)
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
    
    $_SESSION['success'] = "Question updated successfully! ✓";
    
} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Database error updating question: " . $e->getMessage());
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    
} catch (Exception $e) {
    // Rollback transaction on any error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error updating question: " . $e->getMessage());
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to manage questions page
header("Location: ../professor/manage_questions.php?quiz_id=" . $quiz_id);
exit();
?>