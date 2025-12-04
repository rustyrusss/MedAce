<?php
/**
 * Quiz Access Control with Module Completion Check
 * This file checks if student completed the required module before allowing quiz access
 */

session_start();
require_once '../config/db_conn.php';
require_once 'module_completion_helper.php';

// Access control - student only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$quizId = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if ($quizId === 0) {
    $_SESSION['error'] = "Invalid quiz ID";
    header("Location: resources.php");
    exit();
}

// Check if student can access this quiz
$accessCheck = canAccessQuiz($conn, $studentId, $quizId);

if (!$accessCheck['can_access']) {
    // Cannot access - show error message
    $_SESSION['error'] = $accessCheck['reason'];
    $_SESSION['blocked_quiz_info'] = [
        'quiz_id' => $quizId,
        'module_id' => $accessCheck['module_id'],
        'module_title' => $accessCheck['module_title'],
        'module_status' => $accessCheck['module_status'] ?? 'not_started',
        'completion_percentage' => $accessCheck['completion_percentage'] ?? 0
    ];
    header("Location: resources.php#quiz-" . $quizId);
    exit();
}

// Student can access - proceed to quiz
header("Location: take_quiz_actual.php?quiz_id=" . $quizId);
exit();