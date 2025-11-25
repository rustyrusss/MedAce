<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$quizId = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
$attemptNumber = isset($_POST['attempt_number']) ? intval($_POST['attempt_number']) : 1;
$autoSubmitted = isset($_POST['auto_submitted']) ? intval($_POST['auto_submitted']) : 0;

if (!$quizId) {
    die("Invalid quiz ID.");
}

// Determine status: 'completed' for manual submits, 'auto-submitted' for auto-triggers
$status = ($autoSubmitted === 1) ? 'auto-submitted' : 'completed';

try {
    $conn->beginTransaction();
    
    // Fetch questions to calculate score
    $stmt = $conn->prepare("SELECT id, question_type, points FROM questions WHERE quiz_id = ? ORDER BY id");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $score = 0;
    $totalPoints = 0;
    
    // Calculate score for multiple choice and true/false questions only
    foreach ($questions as $q) {
        $questionId = $q['id'];
        $questionType = $q['question_type'];
        $points = isset($q['points']) && $q['points'] > 0 ? intval($q['points']) : 1;
        
        // Only count points for questions that can be auto-graded
        if (in_array($questionType, ['multiple_choice', 'true_false'])) {
            $totalPoints += $points;
            
            // Check if answer is correct
            if (isset($_POST['answers'][$questionId])) {
                $chosenAnswerId = intval($_POST['answers'][$questionId]);
                
                $stmt = $conn->prepare("SELECT is_correct FROM answers WHERE id = ? AND question_id = ?");
                $stmt->execute([$chosenAnswerId, $questionId]);
                $answer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($answer && $answer['is_correct'] == 1) {
                    $score += $points;
                }
            }
        }
    }
    
    // Create quiz attempt record (now includes status)
    $stmt = $conn->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, score, attempt_number, attempted_at, status) 
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$quizId, $studentId, $score, $attemptNumber, $status]);
    $attemptId = $conn->lastInsertId();
    
    // Save student answers
    foreach ($questions as $q) {
        $questionId = $q['id'];
        $questionType = $q['question_type'];
        
        if (in_array($questionType, ['multiple_choice', 'true_false'])) {
            // Save multiple choice / true-false answers
            if (isset($_POST['answers'][$questionId])) {
                $answerId = intval($_POST['answers'][$questionId]);
                
                $stmt = $conn->prepare("
                    INSERT INTO student_answers (attempt_id, question_id, answer_id, created_at, answered_at) 
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$attemptId, $questionId, $answerId]);
            }
        } elseif (in_array($questionType, ['short_answer', 'essay'])) {
            // Save text-based answers (essay and short answer)
            if (isset($_POST['text_answers'][$questionId])) {
                $textAnswer = trim($_POST['text_answers'][$questionId]);
                
                // Insert with student_answer column and explicitly NULL answer_id
                $stmt = $conn->prepare("
                    INSERT INTO student_answers (attempt_id, question_id, answer_id, student_answer, created_at, answered_at) 
                    VALUES (?, ?, NULL, ?, NOW(), NOW())
                ");
                $stmt->execute([$attemptId, $questionId, $textAnswer]);
            }
        }
    }
    
    $conn->commit();
    
    // Clear quiz timer session
    unset($_SESSION['quiz_end_'.$quizId]);
    
    // Redirect to results
    header("Location: ../member/quiz_result.php?attempt_id=" . $attemptId);
    exit();
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Quiz submission error: " . $e->getMessage());
    die("Error submitting quiz: " . $e->getMessage());
}
?>
