<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../public/index.php");
    exit();
}

$professor_id = $_SESSION['user_id'];
$quiz_id = $_GET['id'] ?? null;

if (!$quiz_id) {
    header("Location: ../professor/dashboard.php");
    exit();
}

// Check if the quiz belongs to this professor
$stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND professor_id = ?");
$stmt->execute([$quiz_id, $professor_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header("Location: ../professor/dashboard.php");
    exit();
}

// Delete the quiz
$stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
$stmt->execute([$quiz_id]);

// Redirect back to the dashboard
header("Location: ../professor/dashboard.php");
exit();
?>
