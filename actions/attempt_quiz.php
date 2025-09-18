<?php
session_start();
require_once '../config/db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized access");
    }

    $studentId = $_SESSION['user_id'];
    $quizId = intval($_POST['quiz_id']);

    // 🔎 Check if there's already a pending attempt
    $stmt = $conn->prepare("SELECT id FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND status = 'Pending' LIMIT 1");
    $stmt->execute([$studentId, $quizId]);
    $existingAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAttempt) {
        $attemptId = $existingAttempt['id'];
    } else {
        // ✅ Create a new quiz attempt only if none exists
        $stmt = $conn->prepare("
            INSERT INTO quiz_attempts (student_id, quiz_id, score, status, attempted_at) 
            VALUES (?, ?, 0, 'Pending', NOW())
        ");
        $stmt->execute([$studentId, $quizId]);
        $attemptId = $conn->lastInsertId();
    }

    // ✅ Save answers
    if (isset($_POST['answers']) && is_array($_POST['answers'])) {
        foreach ($_POST['answers'] as $questionId => $answerId) {
            $stmt = $conn->prepare("
                INSERT INTO student_answers (attempt_id, question_id, answer_id, answered_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE answer_id = VALUES(answer_id), answered_at = NOW()
            ");
            $stmt->execute([$attemptId, $questionId, $answerId]);
        }
    }

    // ✅ Calculate score
    $stmt = $conn->prepare("
        SELECT sa.answer_id, a.is_correct
        FROM student_answers sa
        JOIN answers a ON sa.answer_id = a.id
        WHERE sa.attempt_id = ?
    ");
    $stmt->execute([$attemptId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $score = 0;
    foreach ($answers as $ans) {
        if ($ans['is_correct'] == 1) {
            $score++;
        }
    }

    // ✅ Update attempt with score
    $stmt = $conn->prepare("UPDATE quiz_attempts SET score = ?, status = 'Completed' WHERE id = ?");
    $stmt->execute([$score, $attemptId]);

    // ✅ Clear session timer for this quiz
    unset($_SESSION['quiz_end_'.$quizId]);

    // ✅ Redirect to results page
    header("Location: ../member/quiz_result.php?attempt_id=" . $attemptId);
    exit();
}
