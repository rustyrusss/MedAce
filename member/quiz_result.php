<?php
session_start();
require_once '../config/db_conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

$studentId = $_SESSION['user_id'];
$attemptId = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null;

if (!$attemptId) {
    echo "Invalid attempt.";
    exit();
}

// ✅ Fetch attempt
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND student_id = ?");
$stmt->execute([$attemptId, $studentId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    echo "Attempt not found.";
    exit();
}

// ✅ Fetch quiz
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$attempt['quiz_id']]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Fetch student answers with correct answers
$stmt = $conn->prepare("
    SELECT 
        q.question_text,
        sa.answer_id AS chosen_answer_id,
        a_student.answer_text AS chosen_answer,
        a_correct.id AS correct_answer_id,
        a_correct.answer_text AS correct_answer,
        CASE WHEN sa.answer_id = a_correct.id THEN 1 ELSE 0 END AS is_correct
    FROM questions q
    LEFT JOIN student_answers sa 
        ON q.id = sa.question_id AND sa.attempt_id = ?
    LEFT JOIN answers a_student 
        ON sa.answer_id = a_student.id
    LEFT JOIN answers a_correct 
        ON q.id = a_correct.question_id AND a_correct.is_correct = 1
    WHERE q.quiz_id = ?
");
$stmt->execute([$attemptId, $attempt['quiz_id']]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <title><?= htmlspecialchars($quiz['title']) ?> - Results</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
  <div class="max-w-3xl mx-auto bg-white shadow-lg rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-4">Results: <?= htmlspecialchars($quiz['title']) ?></h1>
    <p class="mb-4">Score: <?= $attempt['score'] ?? 0 ?> / <?= count($results) ?></p>

    <?php foreach ($results as $index => $r): ?>
      <div class="mb-4 p-4 border rounded-lg <?= $r['is_correct'] ? 'bg-green-50' : 'bg-red-50' ?>">
        <p class="font-semibold"><?= ($index+1) ?>. <?= htmlspecialchars($r['question_text']) ?></p>
        <p>Your Answer: 
          <span class="font-medium">
            <?= $r['chosen_answer'] ? htmlspecialchars($r['chosen_answer']) : '<em>No Answer</em>' ?>
          </span>
        </p>
        <p>Correct Answer: <span class="font-medium"><?= htmlspecialchars($r['correct_answer']) ?></span></p>
      </div>
    <?php endforeach; ?>

    <!-- Back to Dashboard Button -->
    <div class="mt-6 text-center">
      <a href="../member/dashboard.php" 
         class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg shadow hover:bg-blue-700 transition">
        ⬅ Back to Dashboard
      </a>
    </div>
  </div>
</body>
</html>
