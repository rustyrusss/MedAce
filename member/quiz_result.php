<?php
session_start();
require_once '../config/db_conn.php';

// ✅ Only students
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($quiz['title']) ?> - Results</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-teal-50 via-blue-50 to-indigo-100 p-8">
  <div class="max-w-4xl mx-auto bg-white/90 backdrop-blur-xl shadow-xl rounded-2xl p-8 border border-gray-200">
    <!-- Quiz Header -->
    <h1 class="text-3xl font-bold mb-2 text-gray-800"><?= htmlspecialchars($quiz['title']) ?> - Results</h1>
    <p class="mb-6 text-gray-600">Score: 
      <span class="font-semibold text-blue-700">
        <?= $attempt['score'] ?? 0 ?> / <?= count($results) ?>
      </span>
    </p>

    <!-- Questions -->
    <div class="space-y-4">
      <?php foreach ($results as $index => $r): ?>
        <div class="p-5 border rounded-xl shadow-sm <?= $r['is_correct'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
          <p class="font-semibold text-gray-800 mb-2"><?= ($index+1) ?>. <?= htmlspecialchars($r['question_text']) ?></p>
          
          <p class="mb-1">Your Answer: 
            <span class="font-medium <?= $r['is_correct'] ? 'text-green-700' : 'text-red-700' ?>">
              <?= $r['chosen_answer'] ? htmlspecialchars($r['chosen_answer']) : '<em class="text-gray-500">No Answer</em>' ?>
            </span>
          </p>
          
          <p>Correct Answer: 
            <span class="font-medium text-green-700">
              <?= htmlspecialchars($r['correct_answer']) ?>
            </span>
          </p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Back Button -->
    <div class="mt-8 text-center">
      <a href="../member/dashboard.php" 
         class="inline-block bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition font-medium">
        ⬅ Back to Dashboard
      </a>
    </div>
  </div>
</body>
</html>
