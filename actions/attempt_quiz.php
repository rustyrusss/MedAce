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

try {
    $conn->beginTransaction();

    // Fetch questions
    $stmt = $conn->prepare("SELECT id, question_type, points FROM questions WHERE quiz_id = ? ORDER BY id");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $score = 0;
    $totalQuestions = 0; // total possible points

    foreach ($questions as $q) {
        $questionId = $q['id'];
        $type = $q['question_type'];
        $points = (!empty($q['points']) && $q['points'] > 0) ? intval($q['points']) : 1;

        // Count question points
        $totalQuestions += $points;

        // Auto-grade MCQ + T/F only
        if (in_array($type, ['multiple_choice', 'true_false'])) {

            if (isset($_POST['answers'][$questionId])) {
                $chosenAnswer = intval($_POST['answers'][$questionId]);

                $stmt = $conn->prepare("SELECT is_correct FROM answers WHERE id = ? AND question_id = ?");
                $stmt->execute([$chosenAnswer, $questionId]);
                $ans = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($ans && $ans['is_correct'] == 1) {
                    $score += $points;
                }
            }
        }
        // Essay/short answer → stored later (not auto-graded)
    }

    if ($totalQuestions <= 0) {
        $totalQuestions = 1; // avoid division by zero
    }

    // Percentage
    $percentage = ($score / $totalQuestions) * 100;

    // *** FIXED: Only PASSED or FAILED ***
    $finalStatus = ($percentage >= 75) ? 'passed' : 'failed';

    // Save quiz attempt (no more "completed")
    $stmt = $conn->prepare("
        INSERT INTO quiz_attempts
        (quiz_id, student_id, score, total_questions, attempt_number, attempted_at, status)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$quizId, $studentId, $score, $totalQuestions, $attemptNumber, $finalStatus]);

    $attemptId = $conn->lastInsertId();

    // Save student answers
    foreach ($questions as $q) {
        $questionId = $q['id'];
        $type = $q['question_type'];

        if (in_array($type, ['multiple_choice', 'true_false'])) {

            if (isset($_POST['answers'][$questionId])) {
                $answerId = intval($_POST['answers'][$questionId]);

                $stmt = $conn->prepare("
                    INSERT INTO student_answers
                    (attempt_id, question_id, answer_id, created_at, answered_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$attemptId, $questionId, $answerId]);
            }

        } else {
            // Store essay / short answer text
            if (isset($_POST['text_answers'][$questionId])) {
                $text = trim($_POST['text_answers'][$questionId]);

                $stmt = $conn->prepare("
                    INSERT INTO student_answers
                    (attempt_id, question_id, student_answer, created_at, answered_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$attemptId, $questionId, $text]);
            }
        }
    }

    $conn->commit();

    // Clear timer
    unset($_SESSION['quiz_start_' . $quizId]);
    unset($_SESSION['quiz_end_' . $quizId]);

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
