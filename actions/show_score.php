<?php
session_start();
require_once '../config/db_conn.php';

// Redirect if not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? null;

if (!$quiz_id) {
    header("Location: dashboard_student.php");
    exit();
}

// Fetch the quiz attempt score
$stmt = $conn->prepare("SELECT score FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quiz_id, $student_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    header("Location: dashboard_student.php");
    exit();
}

$score = $attempt['score'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Score</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex min-h-screen">

  <aside class="w-64 bg-white shadow-lg p-4">
    <h2 class="text-xl font-bold mb-6">Student Panel</h2>
    <nav class="space-y-3">
      <a href="dashboard_student.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">🏠 Dashboard</a>
      <a href="../actions/logout_action.php" class="block p-2 rounded-lg hover:bg-gray-100 font-medium">🚪 Logout</a>
    </nav>
  </aside>

  <main class="flex-1 p-8">
    <h1 class="text-2xl font-bold mb-4">Your Score</h1>
    <p class="text-lg">You scored <span class="font-semibold text-blue-600"><?= $score ?> out of 10</span> on this quiz.</p>
  </main>
</body>
</html>
